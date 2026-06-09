<?php
namespace App\Controllers;

use App\Models\Share;
use App\Models\Approval;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND — Share Controller
 *
 * Manages share issuance, purchases, transfers, dividends,
 * and shareholder loan-privilege integration.
 */
class ShareController extends BaseController
{
    private Share    $model;
    private Approval $approval;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model    = new Share();
        $this->approval = new Approval();
        $this->db       = Database::getInstance();
    }

    // ── Shareholder list ─────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requirePermission('shares.view');
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = ['search' => $this->getQuery('search', '')];
        $result  = $this->model->getShareholdersList($page, $limit, $filters);
        $stats   = $this->model->getSummaryStats();
        $config  = $this->model->getConfig();
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Shares & Shareholders';
        $activePage  = 'shares';
        $breadcrumbs = ['Shares' => null];

        $this->view('shares/index', compact(
            'result','stats','config','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    // ── Issue / purchase shares ───────────────────────────────────
    public function create(): void
    {
        $this->auth->requirePermission('shares.manage');
        $members  = $this->db->fetchAll(
            "SELECT id, member_no, first_name, last_name FROM members WHERE status='active' AND deleted_at IS NULL ORDER BY first_name"
        );
        $config   = $this->model->getConfig();
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Issue Shares';
        $activePage  = 'shares';
        $breadcrumbs = ['Shares' => APP_URL.'/shares', 'Issue' => null];

        $this->view('shares/create', compact(
            'members','config','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function store(): void
    {
        $this->auth->requirePermission('shares.manage');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['member_id','shares_qty','transaction_date','notes']);
        if ($missing) {
            $this->jsonError('Required fields missing.', array_fill_keys($missing, 'Required'), 422);
        }
        if (empty(trim($data['notes']))) {
            $this->jsonError('Notes are mandatory for share transactions.', null, 422);
        }

        $memberId  = (int)$data['member_id'];
        $qty       = (int)$data['shares_qty'];
        $config    = $this->model->getConfig();
        $parValue  = (float)$config['par_value'];
        $total     = $qty * $parValue;

        $member = $this->db->fetchOne("SELECT * FROM members WHERE id=? AND deleted_at IS NULL", [$memberId]);
        if (!$member) $this->jsonError('Member not found.', null, 404);

        // Validate share limits
        $existing = $this->model->getByMember($memberId);
        $currentShares = $existing ? (int)$existing['shares_held'] : 0;
        if (($currentShares + $qty) > (int)$config['max_shares_per_member']) {
            $this->jsonError("Member would exceed maximum of {$config['max_shares_per_member']} shares.", null, 422);
        }

        $this->db->beginTransaction();
        try {
            $ref = $this->model->generateRef();

            // Create share transaction record
            $txnId = (int)$this->db->insert("
                INSERT INTO share_transactions
                    (txn_ref, member_id, txn_type, shares_qty, par_value, total_amount,
                     notes, payment_method, transaction_date, status, created_by)
                VALUES (?, ?, 'purchase', ?, ?, ?, ?, ?, ?, 'pending', ?)
            ", [$ref, $memberId, $qty, $parValue, $total,
                $data['notes'], $data['payment_method'] ?? 'cash',
                $data['transaction_date'], $_SESSION['user_id']]);

            // Create approval request (shares always require approval)
            $approvalId = $this->approval->request(
                'share_transaction', $txnId, $ref, $total,
                $_SESSION['user_id'],
                "Share purchase: {$qty} shares @ " . Format::currency($parValue) . " each. " . $data['notes']
            );

            $this->auth->logAudit($_SESSION['user_id'], 'share_purchase_requested', 'shares',
                $txnId, 'ShareTransaction', "Share purchase request {$ref} for member ID {$memberId}: {$qty} shares");

            $this->db->commit();
            $this->jsonSuccess(
                ['txn_id' => $txnId, 'redirect' => APP_URL . '/shares'],
                "Share purchase request {$ref} submitted for approval."
            );

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Failed to create share transaction: ' . $e->getMessage(), null, 500);
        }
    }

    // ── Approve share transaction ─────────────────────────────────
    public function approveTransaction(int $id): void
    {
        $this->auth->requirePermission('shares.approve');
        $this->verifyCsrf();

        $data  = $this->getPost();
        $notes = trim($data['approval_notes'] ?? '');
        if (empty($notes)) {
            $this->jsonError('Approval notes are mandatory.', null, 422);
        }

        $txn = $this->db->fetchOne("SELECT * FROM share_transactions WHERE id=?", [$id]);
        if (!$txn || $txn['status'] !== 'pending') {
            $this->jsonError('Transaction not found or already processed.', null, 400);
        }

        $this->db->beginTransaction();
        try {
            // Update transaction status
            $this->db->execute("
                UPDATE share_transactions
                SET status='completed', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW()
                WHERE id=?
            ", [$_SESSION['user_id'], $notes, $id]);

            // Update or create member_shares record
            $existing = $this->model->getByMember($txn['member_id']);
            if ($existing) {
                $this->db->execute("
                    UPDATE member_shares
                    SET shares_held    = shares_held + ?,
                        total_invested = total_invested + ?,
                        updated_at     = NOW()
                    WHERE member_id = ?
                ", [$txn['shares_qty'], $txn['total_amount'], $txn['member_id']]);
            } else {
                $this->db->execute("
                    INSERT INTO member_shares (member_id, shares_held, total_invested, share_date, status, created_by)
                    VALUES (?, ?, ?, ?, 'active', ?)
                ", [$txn['member_id'], $txn['shares_qty'], $txn['total_amount'],
                    $txn['transaction_date'], $_SESSION['user_id']]);
            }

            // Update member is_shareholder flag
            $this->db->execute("
                UPDATE members SET is_shareholder=1, shares_held=(
                    SELECT COALESCE(SUM(shares_qty),0) FROM share_transactions
                    WHERE member_id=? AND txn_type='purchase' AND status='completed'
                ) WHERE id=?
            ", [$txn['member_id'], $txn['member_id']]);

            // Mark approval as done
            $approvalRecord = $this->approval->getForRecord('share_transaction', $id);
            if ($approvalRecord) {
                $this->approval->approve($approvalRecord['id'], $_SESSION['user_id'], $notes);
            }

            // Record savings deduction transaction if payment method is deduction
            if ($txn['payment_method'] === 'deduction') {
                $savAcc = $this->db->fetchOne(
                    "SELECT id, balance FROM savings_accounts WHERE member_id=? AND status='active' ORDER BY id LIMIT 1",
                    [$txn['member_id']]
                );
                if ($savAcc && (float)$savAcc['balance'] >= (float)$txn['total_amount']) {
                    $newBal = (float)$savAcc['balance'] - (float)$txn['total_amount'];
                    $this->db->execute("UPDATE savings_accounts SET balance=? WHERE id=?", [$newBal, $savAcc['id']]);
                    $txnRef = 'TXN-' . strtoupper(uniqid());
                    $this->db->execute("
                        INSERT INTO transactions
                            (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method,
                             description, balance_before, balance_after, transaction_date, status, created_by)
                        VALUES (?, 'withdrawal', ?, ?, ?, 'internal', ?, ?, ?, ?, 'completed', ?)
                    ", [$txnRef, $txn['total_amount'], $txn['member_id'], $savAcc['id'],
                        "Share purchase deduction: {$txn['txn_ref']}",
                        $savAcc['balance'], $newBal, date('Y-m-d'), $_SESSION['user_id']]);
                }
            }

            $this->auth->logAudit($_SESSION['user_id'], 'share_transaction_approved', 'shares',
                $id, 'ShareTransaction', "Approved share transaction {$txn['txn_ref']}: {$notes}");

            $this->db->commit();
            $this->jsonSuccess(null, "Share transaction {$txn['txn_ref']} approved successfully.");

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Approval failed: ' . $e->getMessage(), null, 500);
        }
    }

    // ── Reject share transaction ──────────────────────────────────
    public function rejectTransaction(int $id): void
    {
        $this->auth->requirePermission('shares.approve');
        $this->verifyCsrf();

        $notes = trim($this->getPost()['rejection_notes'] ?? '');
        if (empty($notes)) {
            $this->jsonError('Rejection notes are mandatory.', null, 422);
        }

        $txn = $this->db->fetchOne("SELECT * FROM share_transactions WHERE id=?", [$id]);
        if (!$txn || $txn['status'] !== 'pending') {
            $this->jsonError('Transaction not found or already processed.', null, 400);
        }

        $this->db->execute("
            UPDATE share_transactions
            SET status='rejected', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW()
            WHERE id=?
        ", [$_SESSION['user_id'], $notes, $id]);

        $approvalRecord = $this->approval->getForRecord('share_transaction', $id);
        if ($approvalRecord) {
            $this->approval->reject($approvalRecord['id'], $_SESSION['user_id'], $notes);
        }

        $this->auth->logAudit($_SESSION['user_id'], 'share_transaction_rejected', 'shares',
            $id, 'ShareTransaction', "Rejected {$txn['txn_ref']}: {$notes}");

        $this->jsonSuccess(null, "Share transaction {$txn['txn_ref']} rejected.");
    }

    // ── Member share profile ──────────────────────────────────────
    public function memberShares(int $memberId): void
    {
        $this->auth->requirePermission('shares.view');

        $member    = $this->db->fetchOne("SELECT * FROM members WHERE id=? AND deleted_at IS NULL", [$memberId]);
        if (!$member) { $this->flash('error','Member not found.'); $this->redirect('/shares'); }

        $holding   = $this->model->getByMember($memberId);
        $txns      = $this->model->getTransactions(1, 50, ['member_id' => $memberId]);
        $config    = $this->model->getConfig();
        $privileges = $this->model->getLoanPrivileges($memberId, (float)$this->getSettings()['loan_interest_rate'] ?? 10, 3);
        $settings  = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Shares — ' . $member['first_name'] . ' ' . $member['last_name'];
        $activePage  = 'shares';
        $breadcrumbs = ['Shares' => APP_URL.'/shares', $member['first_name'] => null];

        $this->view('shares/member', compact(
            'member','holding','txns','config','privileges','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    // ── Share analytics/reports ───────────────────────────────────
    public function report(): void
    {
        $this->auth->requirePermission('shares.view');

        $stats    = $this->model->getSummaryStats();
        $monthly  = $this->model->getMonthlyReport();
        $config   = $this->model->getConfig();
        $txns     = $this->model->getTransactions(1, 50, ['status' => $this->getQuery('status', '')]);
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Share Analytics';
        $activePage  = 'shares';
        $breadcrumbs = ['Shares' => APP_URL.'/shares', 'Analytics' => null];

        $this->view('shares/report', compact(
            'stats','monthly','config','txns','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    // ── Share config update ───────────────────────────────────────
    public function updateConfig(): void
    {
        $this->auth->requirePermission('shares.manage');
        $this->verifyCsrf();

        $data = $this->getPost();
        $this->model->updateConfig([...$data, 'updated_by' => $_SESSION['user_id']]);
        $this->auth->logAudit($_SESSION['user_id'], 'share_config_updated', 'shares', 1, 'ShareConfig', 'Share configuration updated');
        $this->jsonSuccess(null, 'Share configuration updated successfully.');
    }

    private function getSettings(): array
    {
        return array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"), 'value', 'key');
    }
    private function getUnread(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$_SESSION['user_id']]);
    }
}