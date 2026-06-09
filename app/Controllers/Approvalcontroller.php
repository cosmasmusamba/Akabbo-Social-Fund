<?php
namespace App\Controllers;

use App\Models\Approval;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND — Approval Controller
 *
 * Central workflow hub for all financial approval actions.
 * Every approve/reject action MUST include mandatory notes.
 */
class ApprovalController extends BaseController
{
    private Approval $model;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Approval();
        $this->db    = Database::getInstance();
    }

    // ── Approval queue (pending items) ───────────────────────────
    public function index(): void
    {
        $this->auth->requirePermission('approvals.view');
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'status'         => $this->getQuery('status', 'pending'),
            'reference_type' => $this->getQuery('type', ''),
        ];

        // Non-super-admin sees only items assigned to them or unassigned
        if (!$this->auth->can('users.delete')) {
            $filters['assigned_to'] = $_SESSION['user_id'];
        }

        $result  = $this->model->getQueue($page, $limit, $filters);
        $stats   = $this->model->getStats();
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Approval Queue';
        $activePage  = 'approvals';
        $breadcrumbs = ['Approvals' => null];

        $this->view('approvals/index', compact(
            'result','stats','filters','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    // ── Single approval detail ────────────────────────────────────
    public function show(int $id): void
    {
        $this->auth->requirePermission('approvals.view');

        $approval = $this->model->find($id);
        if (!$approval) { $this->flash('error','Approval not found.'); $this->redirect('/approvals'); }

        // Attach the referenced record details
        $refData  = $this->getReferencedRecord($approval['reference_type'], $approval['reference_id']);
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Approval #' . $id;
        $activePage  = 'approvals';
        $breadcrumbs = ['Approvals' => APP_URL.'/approvals', '#'.$id => null];

        $this->view('approvals/show', compact(
            'approval','refData','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    // ── Approve ──────────────────────────────────────────────────
    public function approve(int $id): void
    {
        $this->auth->requirePermission('approvals.process');
        $this->verifyCsrf();

        $notes = trim($this->getPost()['approval_notes'] ?? '');
        if (empty($notes)) {
            $this->jsonError('Approval notes are mandatory. Please state your reason for approving.', null, 422);
        }

        $approval = $this->model->find($id);
        if (!$approval || $approval['status'] !== 'pending') {
            $this->jsonError('Approval not found or already processed.', null, 400);
        }

        // Prevent self-approval
        if ((int)$approval['requested_by'] === (int)$_SESSION['user_id']) {
            $this->jsonError('You cannot approve your own request.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            $this->model->approve($id, $_SESSION['user_id'], $notes);

            // Execute the underlying action
            $this->executeApprovedAction($approval, $notes);

            $this->auth->logAudit($_SESSION['user_id'], 'approval_granted', 'approvals', $id, 'Approval',
                "Approved {$approval['reference_type']} #{$approval['reference_id']}: {$notes}");

            $this->db->commit();
            $this->jsonSuccess(null, 'Approved successfully. Action has been executed.');

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Approval failed: ' . $e->getMessage(), null, 500);
        }
    }

    // ── Reject ───────────────────────────────────────────────────
    public function reject(int $id): void
    {
        $this->auth->requirePermission('approvals.process');
        $this->verifyCsrf();

        $notes = trim($this->getPost()['rejection_notes'] ?? '');
        if (empty($notes)) {
            $this->jsonError('Rejection notes are mandatory. Please state your reason for rejecting.', null, 422);
        }

        $approval = $this->model->find($id);
        if (!$approval || $approval['status'] !== 'pending') {
            $this->jsonError('Approval not found or already processed.', null, 400);
        }

        if ((int)$approval['requested_by'] === (int)$_SESSION['user_id']) {
            $this->jsonError('You cannot reject your own request.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            $this->model->reject($id, $_SESSION['user_id'], $notes);

            // Mark the referenced record as rejected
            $this->executeRejectedAction($approval, $notes);

            $this->auth->logAudit($_SESSION['user_id'], 'approval_rejected', 'approvals', $id, 'Approval',
                "Rejected {$approval['reference_type']} #{$approval['reference_id']}: {$notes}");

            $this->db->commit();
            $this->jsonSuccess(null, 'Request rejected. Initiator will be notified.');

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Rejection failed: ' . $e->getMessage(), null, 500);
        }
    }

    // ── AJAX: pending count for navbar badge ─────────────────────
    public function pendingCount(): void
    {
        $this->auth->requireAuth();
        $count = $this->model->getPendingCount();
        $this->jsonSuccess(['count' => $count]);
    }

    // ── Execute approved financial action ─────────────────────────
    private function executeApprovedAction(array $approval, string $notes): void
    {
        $type = $approval['reference_type'];
        $refId = (int)$approval['reference_id'];

        switch ($type) {

            case 'withdrawal':
                // Complete the pending withdrawal transaction
                $txn = $this->db->fetchOne("SELECT * FROM transactions WHERE id=?", [$refId]);
                if (!$txn) throw new \RuntimeException("Transaction not found.");

                $savAcc = $this->db->fetchOne(
                    "SELECT * FROM savings_accounts WHERE id=? AND status='active'", [$txn['savings_account_id']]
                );
                if (!$savAcc) throw new \RuntimeException("Savings account not found.");

                if ((float)$savAcc['balance'] < (float)$txn['amount']) {
                    throw new \RuntimeException("Insufficient balance for withdrawal.");
                }

                $newBal = (float)$savAcc['balance'] - (float)$txn['amount'];
                $this->db->execute("UPDATE savings_accounts SET balance=?, updated_at=NOW() WHERE id=?",
                    [$newBal, $savAcc['id']]);

                $this->db->execute("
                    UPDATE transactions SET status='completed', approved_by=?, approved_at=NOW(),
                    approval_notes=?, balance_after=?, updated_at=NOW() WHERE id=?
                ", [$_SESSION['user_id'], $notes, $newBal, $refId]);
                break;

            case 'transfer':
                $transfer = $this->db->fetchOne("SELECT * FROM fund_transfers WHERE id=?", [$refId]);
                if (!$transfer) throw new \RuntimeException("Transfer not found.");

                // Debit sender
                $fromAcc = $this->db->fetchOne(
                    "SELECT * FROM savings_accounts WHERE member_id=? AND status='active' ORDER BY id LIMIT 1",
                    [$transfer['from_member_id']]
                );
                if (!$fromAcc || (float)$fromAcc['balance'] < (float)$transfer['amount']) {
                    throw new \RuntimeException("Sender has insufficient balance.");
                }

                // Credit receiver
                $toAcc = $this->db->fetchOne(
                    "SELECT * FROM savings_accounts WHERE member_id=? AND status='active' ORDER BY id LIMIT 1",
                    [$transfer['to_member_id']]
                );
                if (!$toAcc) throw new \RuntimeException("Recipient savings account not found.");

                $fromNew = (float)$fromAcc['balance'] - (float)$transfer['amount'];
                $toNew   = (float)$toAcc['balance']   + (float)$transfer['amount'];

                $this->db->execute("UPDATE savings_accounts SET balance=?, updated_at=NOW() WHERE id=?",
                    [$fromNew, $fromAcc['id']]);
                $this->db->execute("UPDATE savings_accounts SET balance=?, updated_at=NOW() WHERE id=?",
                    [$toNew, $toAcc['id']]);

                // Record debit & credit transactions
                foreach ([
                    [$transfer['from_member_id'], $fromAcc['id'], 'withdrawal', $fromAcc['balance'], $fromNew, "Transfer out to M#{$transfer['to_member_id']}: {$transfer['description']}"],
                    [$transfer['to_member_id'],   $toAcc['id'],   'deposit',    $toAcc['balance'],   $toNew,   "Transfer in from M#{$transfer['from_member_id']}: {$transfer['description']}"],
                ] as [$mid, $accId, $ttype, $bal, $newBal2, $desc]) {
                    $this->db->execute("
                        INSERT INTO transactions
                            (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method,
                             description, balance_before, balance_after, transaction_date, status, created_by)
                        VALUES (?, ?, ?, ?, ?, 'internal', ?, ?, ?, ?, 'completed', ?)
                    ", ['TXN-'.strtoupper(uniqid()), $ttype, $transfer['amount'], $mid, $accId,
                        $desc, $bal, $newBal2, $transfer['transfer_date'], $_SESSION['user_id']]);
                }

                $this->db->execute("
                    UPDATE fund_transfers SET status='completed', approved_by=?, approved_at=NOW(),
                    approval_notes=?, updated_at=NOW() WHERE id=?
                ", [$_SESSION['user_id'], $notes, $refId]);
                break;

            case 'disbursement':
                // Mark loan as active and create disbursement transaction
                $loan = $this->db->fetchOne("SELECT * FROM loans WHERE id=?", [$refId]);
                if (!$loan) throw new \RuntimeException("Loan not found.");

                $this->db->execute("
                    UPDATE loans SET status='active', disbursement_date=CURDATE(),
                    disbursed_by=?, approval_notes=?, updated_at=NOW() WHERE id=?
                ", [$_SESSION['user_id'], $notes, $refId]);

                $this->db->execute("
                    INSERT INTO transactions
                        (txn_ref, txn_type, amount, member_id, loan_id, payment_method,
                         description, transaction_date, status, created_by)
                    VALUES (?, 'loan_disbursement', ?, ?, ?, ?, ?, CURDATE(), 'completed', ?)
                ", ['TXN-'.strtoupper(uniqid()), $loan['principal_amount'], $loan['member_id'],
                    $refId, $loan['disbursement_method'],
                    "Loan disbursement: {$loan['loan_no']}. {$notes}", $_SESSION['user_id']]);
                break;

            case 'expense':
                $this->db->execute("
                    UPDATE expenses SET status='approved', approved_by=?, approved_at=NOW(),
                    approval_notes=?, updated_at=NOW() WHERE id=?
                ", [$_SESSION['user_id'], $notes, $refId]);
                break;

            case 'reversal':
                $this->db->execute("
                    UPDATE transactions SET status='reversed', approved_by=?, approved_at=NOW(),
                    approval_notes=?, updated_at=NOW() WHERE id=?
                ", [$_SESSION['user_id'], $notes, $refId]);
                break;
        }
    }

    // ── Execute rejected action ───────────────────────────────────
    private function executeRejectedAction(array $approval, string $notes): void
    {
        $type  = $approval['reference_type'];
        $refId = (int)$approval['reference_id'];

        $tableMap = [
            'withdrawal'  => ['transactions',  'rejected'],
            'transfer'    => ['fund_transfers', 'rejected'],
            'disbursement'=> ['loans',          'approved'],  // revert to approved state
            'expense'     => ['expenses',        'rejected'],
            'reversal'    => ['transactions',   'completed'], // keep original
        ];

        if (isset($tableMap[$type])) {
            [$table, $status] = $tableMap[$type];
            $this->db->execute(
                "UPDATE `{$table}` SET status=?, updated_at=NOW() WHERE id=?",
                [$status, $refId]
            );
        }
    }

    // ── Load referenced record for display ───────────────────────
    private function getReferencedRecord(string $type, int $id): ?array
    {
        return match ($type) {
            'withdrawal', 'reversal' => $this->db->fetchOne(
                "SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no
                 FROM transactions t LEFT JOIN members m ON m.id=t.member_id WHERE t.id=?", [$id]
            ),
            'transfer' => $this->db->fetchOne(
                "SELECT ft.*, CONCAT(mf.first_name,' ',mf.last_name) AS from_name,
                         CONCAT(mt.first_name,' ',mt.last_name) AS to_name
                 FROM fund_transfers ft
                 JOIN members mf ON mf.id=ft.from_member_id
                 JOIN members mt ON mt.id=ft.to_member_id
                 WHERE ft.id=?", [$id]
            ),
            'disbursement' => $this->db->fetchOne(
                "SELECT l.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, lp.name AS product_name
                 FROM loans l JOIN members m ON m.id=l.member_id JOIN loan_products lp ON lp.id=l.loan_product_id
                 WHERE l.id=?", [$id]
            ),
            'expense' => $this->db->fetchOne(
                "SELECT e.*, ec.name AS category_name
                 FROM expenses e JOIN expense_categories ec ON ec.id=e.category_id WHERE e.id=?", [$id]
            ),
            'share_transaction' => $this->db->fetchOne(
                "SELECT st.*, CONCAT(m.first_name,' ',m.last_name) AS member_name
                 FROM share_transactions st JOIN members m ON m.id=st.member_id WHERE st.id=?", [$id]
            ),
            default => null,
        };
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