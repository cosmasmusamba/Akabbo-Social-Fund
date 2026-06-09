<?php
namespace App\Controllers;

use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Savings Controller
 *
 * Manages savings accounts: deposits, withdrawals, interest computation,
 * and account summary views.
 */
class SavingsController extends BaseController
{
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db = Database::getInstance();
    }

    /** Paginated savings accounts list. */
    public function index(): void
    {
        $this->auth->requirePermission('savings.view');

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $search   = $this->getQuery('search', '');
        $status   = $this->getQuery('status', '');
        $memberId = $this->getQuery('member', '');

        $where  = ['sa.status != "closed"'];
        $params = [];

        if ($search) {
            $like    = "%{$search}%";
            $where[] = "(sa.account_no LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ?)";
            array_push($params, $like, $like, $like);
        }
        if ($status) { $where[] = 'sa.status = ?'; $params[] = $status; }
        if ($memberId) { $where[] = 'sa.member_id = ?'; $params[] = (int)$memberId; }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM savings_accounts sa JOIN members m ON m.id=sa.member_id {$whereSql}", $params);
        $data  = $this->db->fetchAll("
            SELECT sa.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone
            FROM savings_accounts sa
            JOIN members m ON m.id = sa.member_id
            {$whereSql}
            ORDER BY sa.balance DESC, sa.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ", $params);

        $totals = $this->db->fetchOne("SELECT COALESCE(SUM(balance),0) AS total_savings, COUNT(*) AS total_accounts FROM savings_accounts WHERE status='active'");
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $result      = ['data'=>$data,'total'=>$total,'page'=>$page,'per_page'=>$limit,'last_page'=>(int)ceil($total/$limit)];
        $pageTitle   = 'Savings Accounts';
        $activePage  = 'savings';
        $breadcrumbs = ['Savings' => null];

        $this->view('savings/index', compact('result','totals','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Show deposit form. */
    public function showDeposit(): void
    {
        $this->auth->requirePermission('savings.deposit');

        $members  = $this->db->fetchAll("SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone FROM members WHERE status='active' ORDER BY first_name LIMIT 100");
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle  = 'Record Deposit';
        $activePage = 'savings';
        $breadcrumbs = ['Savings' => APP_URL.'/savings', 'Deposit' => null];

        $this->view('savings/deposit', compact('members','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Process a deposit. */
    public function deposit(): void
    {
        $this->auth->requirePermission('savings.deposit');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['member_id','amount','payment_method','transaction_date']);
        if ($missing) $this->jsonError('Please fill all required fields.', array_fill_keys($missing, 'Required.'), 422);

        $memberId = (int)$data['member_id'];
        $amount   = (float)$data['amount'];

        if ($amount <= 0) $this->jsonError('Deposit amount must be greater than zero.', null, 422);

        // Get or validate savings account
        $account = $this->db->fetchOne(
            "SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' AND status = 'active' LIMIT 1",
            [$memberId]
        );
        if (!$account) $this->jsonError('No active savings account found for this member.', null, 404);

        $this->db->beginTransaction();
        try {
            $balanceBefore = (float)$account['balance'];
            $balanceAfter  = $balanceBefore + $amount;

            // Update account balance
            $this->db->execute("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?", [$amount, $account['id']]);

            // Record transaction
            $txnRef = 'TXN-' . strtoupper(uniqid());
            $this->db->execute("
                INSERT INTO transactions
                    (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, external_ref,
                     description, transaction_date, balance_before, balance_after, status, created_by)
                VALUES (?, 'deposit', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ", [
                $txnRef, $amount, $memberId, $account['id'],
                $data['payment_method'], $data['external_ref'] ?? null,
                $data['description'] ?? 'Savings deposit',
                $data['transaction_date'], $balanceBefore, $balanceAfter,
                $_SESSION['user_id'],
            ]);

            $this->auth->logAudit($_SESSION['user_id'], 'deposit', 'savings', $account['id'], 'SavingsAccount',
                "Deposit of " . Format::currency($amount) . " to account {$account['account_no']}");

            $this->db->commit();

            $this->jsonSuccess([
                'txn_ref'       => $txnRef,
                'balance_after' => $balanceAfter,
                'balance_fmt'   => Format::currency($balanceAfter),
            ], 'Deposit of ' . Format::currency($amount) . ' recorded successfully!');

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[DEPOSIT ERROR] ' . $e->getMessage());
            $this->jsonError('Failed to process deposit. Please try again.', null, 500);
        }
    }

    /** Show withdrawal form. */
    public function showWithdraw(): void
    {
        $this->auth->requirePermission('savings.withdraw');

        $members  = $this->db->fetchAll("SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone FROM members WHERE status='active' ORDER BY first_name LIMIT 100");
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle  = 'Process Withdrawal';
        $activePage = 'savings';
        $breadcrumbs = ['Savings' => APP_URL.'/savings', 'Withdraw' => null];

        $this->view('savings/withdraw', compact('members','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    /** Process a withdrawal. */
    public function withdraw(): void
    {
        $this->auth->requirePermission('savings.withdraw');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['member_id','amount','payment_method','transaction_date']);
        if ($missing) $this->jsonError('Please fill all required fields.', array_fill_keys($missing, 'Required.'), 422);

        $memberId = (int)$data['member_id'];
        $amount   = (float)$data['amount'];

        if ($amount <= 0) $this->jsonError('Withdrawal amount must be greater than zero.', null, 422);

        $account = $this->db->fetchOne(
            "SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' AND status = 'active' LIMIT 1",
            [$memberId]
        );
        if (!$account) $this->jsonError('No active savings account found.', null, 404);

        // Check active loan obligations
        $activeLoanBalance = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')",
            [$memberId]
        );
        $settings   = $this->getSettings();
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
        $minRequired = $activeLoanBalance / $multiplier;
        $available   = max(0, (float)$account['balance'] - $minRequired);

        if ($amount > $available) {
            $this->jsonError(
                "Withdrawal exceeds available amount. Maximum withdrawable: " . Format::currency($available) .
                " (balance locked as loan security: " . Format::currency($minRequired) . ").",
                null, 422
            );
        }

        $this->db->beginTransaction();
        try {
            $balanceBefore = (float)$account['balance'];
            $balanceAfter  = $balanceBefore - $amount;

            $this->db->execute("UPDATE savings_accounts SET balance = balance - ? WHERE id = ?", [$amount, $account['id']]);

            $txnRef = 'TXN-' . strtoupper(uniqid());
            $this->db->execute("
                INSERT INTO transactions
                    (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method,
                     description, transaction_date, balance_before, balance_after, status, created_by)
                VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ", [
                $txnRef, $amount, $memberId, $account['id'],
                $data['payment_method'],
                $data['description'] ?? 'Savings withdrawal',
                $data['transaction_date'], $balanceBefore, $balanceAfter,
                $_SESSION['user_id'],
            ]);

            $this->auth->logAudit($_SESSION['user_id'], 'withdrawal', 'savings', $account['id'], 'SavingsAccount',
                "Withdrawal of " . Format::currency($amount) . " from account {$account['account_no']}");

            $this->db->commit();
            $this->jsonSuccess([
                'txn_ref'       => $txnRef,
                'balance_after' => $balanceAfter,
                'balance_fmt'   => Format::currency($balanceAfter),
            ], 'Withdrawal of ' . Format::currency($amount) . ' processed successfully!');

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('[WITHDRAWAL ERROR] ' . $e->getMessage());
            $this->jsonError('Failed to process withdrawal.', null, 500);
        }
    }

    /** Show savings account detail. */
    public function show(int $id): void
    {
        $this->auth->requirePermission('savings.view');

        $account = $this->db->fetchOne("
            SELECT sa.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone, m.id AS member_id
            FROM savings_accounts sa
            JOIN members m ON m.id = sa.member_id
            WHERE sa.id = ?
        ", [$id]);

        if (!$account) { $this->flash('error', 'Account not found.'); $this->redirect('/savings'); }

        $transactions = $this->db->fetchAll("
            SELECT * FROM transactions WHERE savings_account_id = ? ORDER BY transaction_date DESC, created_at DESC LIMIT 50
        ", [$id]);

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $pageTitle  = 'Account — ' . $account['account_no'];
        $activePage = 'savings';
        $breadcrumbs = ['Savings' => APP_URL.'/savings', $account['account_no'] => null];

        $this->view('savings/show', compact('account','transactions','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    private function getSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
        return array_column($rows, 'value', 'key');
    }

    private function getUnreadCount(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    }
}
