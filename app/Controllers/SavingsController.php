<?php
namespace App\Controllers;

use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Savings Controller
 *
 * Manages savings accounts: deposits, withdrawals, transfers,
 * interest posting, savings goals, statements, and charts.
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
            SELECT sa.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone, m.avatar
            FROM savings_accounts sa
            JOIN members m ON m.id = sa.member_id
            {$whereSql}
            ORDER BY sa.balance DESC, sa.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ", $params);

        $summaryStats = $this->db->fetchOne("
            SELECT 
                COALESCE(SUM(balance),0) AS total_savings,
                COUNT(*) AS total_accounts,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_accounts,
                SUM(CASE WHEN status='dormant' THEN 1 ELSE 0 END) AS dormant_accounts
            FROM savings_accounts
        ");

        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnreadCount();

        $result = [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $limit,
            'last_page' => (int)ceil($total / $limit),
            'from'      => $total > 0 ? ($page - 1) * $limit + 1 : 0,
            'to'        => min($page * $limit, $total)
        ];

        $this->view('savings/index', compact('result','summaryStats','settings','unreadNotifications'));
    }

    /** Show deposit form. */
    public function showDeposit(): void
    {
        $this->auth->requirePermission('savings.deposit');
        $members = $this->db->fetchAll("SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone FROM members WHERE status='active' ORDER BY first_name LIMIT 100");
        $memberId = $this->getQuery('member', null);
        $accountId = $this->getQuery('account', null);
        $member = null;
        if ($memberId) $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [(int)$memberId]);
        elseif ($accountId) $member = $this->db->fetchOne("SELECT m.* FROM savings_accounts sa JOIN members m ON m.id=sa.member_id WHERE sa.id = ?", [(int)$accountId]);

        $this->view('savings/deposit', ['members'=>$members, 'member'=>$member] + $this->getCommonViewData('Record Deposit', 'savings'));
    }

    /** Process deposit. */
    public function deposit(): void
    {
        $this->auth->requirePermission('savings.deposit');
        $this->verifyCsrf();
        $data = $this->getPost();
        $this->validateRequired($data, ['member_id','amount','payment_method']);

        $memberId = (int)$data['member_id'];
        $amount   = (float)$data['amount'];
        if ($amount <= 0) $this->jsonError('Deposit amount must be greater than zero.', null, 422);

        $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' AND status = 'active' LIMIT 1", [$memberId]);
        if (!$account) $this->jsonError('No active savings account found for this member.', null, 404);

        $this->db->beginTransaction();
        try {
            $balanceBefore = (float)$account['balance'];
            $balanceAfter  = $balanceBefore + $amount;
            $this->db->execute("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?", [$amount, $account['id']]);

            $txnRef = 'TXN-' . strtoupper(uniqid());
            $this->db->execute("
                INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, external_ref, description, transaction_date, balance_before, balance_after, status, created_by)
                VALUES (?, 'deposit', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ", [$txnRef, $amount, $memberId, $account['id'], $data['payment_method'], $data['external_ref'] ?? null, $data['description'] ?? 'Savings deposit', $balanceBefore, $balanceAfter, $_SESSION['user_id']]);

            $this->auth->logAudit($_SESSION['user_id'], 'deposit', 'savings', $account['id'], 'SavingsAccount', "Deposit of " . Format::currency($amount) . " to account {$account['account_no']}");
            $this->db->commit();
            $this->jsonSuccess([
                'redirect' => APP_URL . '/savings/' . $account['id'],
                'balance_after' => $balanceAfter,
                'balance_fmt' => Format::currency($balanceAfter),
            ], 'Deposit of ' . Format::currency($amount) . ' recorded successfully!');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Failed to process deposit.', null, 500);
        }
    }

    /** Show withdrawal form. */
    public function showWithdraw(): void
    {
        $this->auth->requirePermission('savings.withdraw');
        $members = $this->db->fetchAll("SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone FROM members WHERE status='active' ORDER BY first_name LIMIT 100");
        $memberId = $this->getQuery('member', null);
        $accountId = $this->getQuery('account', null);
        $member = null;
        if ($memberId) $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [(int)$memberId]);
        elseif ($accountId) $member = $this->db->fetchOne("SELECT m.* FROM savings_accounts sa JOIN members m ON m.id=sa.member_id WHERE sa.id = ?", [(int)$accountId]);

        $this->view('savings/withdraw', ['members'=>$members, 'member'=>$member] + $this->getCommonViewData('Process Withdrawal', 'savings'));
    }

    /**
     * Process withdrawal request (creates pending approval).
     */
    public function withdraw(): void
    {
        $this->auth->requirePermission('savings.withdraw');
        $this->verifyCsrf();
        $data = $this->getPost();
        $this->validateRequired($data, ['member_id','amount','payment_method']);

        $memberId = (int)$data['member_id'];
        $amount   = (float)$data['amount'];
        if ($amount <= 0) $this->jsonError('Withdrawal amount must be greater than zero.', null, 422);

        $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' AND status = 'active' LIMIT 1", [$memberId]);
        if (!$account) $this->jsonError('No active savings account found.', null, 404);

        // Validate available balance (account for loan locks)
        $activeLoanBalance = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')",
            [$memberId]
        );
        $settings = $this->getSettings();
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
        $minRequired = $activeLoanBalance / $multiplier;
        $available = max(0, (float)$account['balance'] - $minRequired);
        if ($amount > $available) {
            $this->jsonError("Withdrawal exceeds available amount. Maximum: " . Format::currency($available), null, 422);
        }

        $this->db->beginTransaction();
        try {
            $txnRef = 'WDR-' . strtoupper(uniqid());
            $this->db->execute("
                INSERT INTO transactions
                    (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method,
                     description, transaction_date, status, created_by)
                VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, ?, 'pending', ?)
            ", [
                $txnRef, $amount, $memberId, $account['id'], $data['payment_method'],
                $data['description'] ?? 'Savings withdrawal request',
                $_SESSION['user_id']
            ]);

            $this->auth->logAudit($_SESSION['user_id'], 'withdrawal_requested', 'savings', $account['id'], 'SavingsAccount',
                "Withdrawal request of " . Format::currency($amount) . " from account {$account['account_no']}");

            $this->notifyFinanceManagers(
                "New Withdrawal Request",
                "Withdrawal of " . Format::currency($amount) . " requested by member #{$memberId}. Reference: {$txnRef}"
            );

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/savings'], 'Withdrawal request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Failed to submit withdrawal request.', null, 500);
        }
    }

    private function notifyFinanceManagers(string $title, string $message): void
    {
        // Get all users with approval permission (no hardcoded role IDs)
        $permissionSlug = 'savings.approve_withdrawal';
        $managers = $this->db->fetchAll("
            SELECT DISTINCT u.id
            FROM users u
            JOIN role_permissions rp ON rp.role_id = u.role_id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE p.slug = ? AND u.status = 'active'
        ", [$permissionSlug]);

        foreach ($managers as $mgr) {
            $this->notifyUser($mgr['id'], 'approval', $title, $message);
        }
    }

    /** Show transfer form. */
    public function showTransfer(): void
    {
        $this->auth->requirePermission('savings.deposit'); // Use deposit permission
        $members = $this->db->fetchAll("SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone FROM members WHERE status='active' ORDER BY first_name LIMIT 100");
        $this->view('savings/transfer', ['members' => $members] + $this->getCommonViewData('Transfer Savings', 'savings'));
    }

    /** Process transfer between two members. */
    public function transfer(): void
    {
        $this->auth->requirePermission('savings.deposit');
        $this->verifyCsrf();
        $data = $this->getPost();
        $this->validateRequired($data, ['from_member_id','to_member_id','amount']);

        $fromId = (int)$data['from_member_id'];
        $toId   = (int)$data['to_member_id'];
        $amount = (float)$data['amount'];
        if ($fromId === $toId) $this->jsonError('Cannot transfer to the same member.', null, 422);
        if ($amount <= 0) $this->jsonError('Amount must be greater than zero.', null, 422);

        $fromAccount = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [$fromId]);
        $toAccount   = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [$toId]);
        if (!$fromAccount) $this->jsonError('Source member has no active savings account.', null, 404);
        if (!$toAccount) $this->jsonError('Destination member has no active savings account.', null, 404);

        // Validate available balance (account for loan locks)
        $activeLoanBalance = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')",
            [$fromId]
        );
        $settings = $this->getSettings();
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
        $minRequired = $activeLoanBalance / $multiplier;
        $available = max(0, (float)$fromAccount['balance'] - $minRequired);
        if ($amount > $available) {
            $this->jsonError("Insufficient available balance. Maximum transferable: " . Format::currency($available), null, 422);
        }

        $this->db->beginTransaction();
        try {
            // Create a common reference token
            $txnRef = 'TRF-' . strtoupper(uniqid());

            // Create pending transfer_out transaction (source)
            $outId = $this->db->insert("
                INSERT INTO transactions
                    (txn_ref, txn_type, amount, member_id, savings_account_id, description,
                     transaction_date, status, created_by, reference_txn_id)
                VALUES (?, 'transfer_out', ?, ?, ?, ?, ?, 'pending', ?, NULL)
            ", [
                $txnRef . '-OUT',
                $amount,
                $fromId,
                $fromAccount['id'],
                $data['description'] ?? "Transfer to member #{$toId}",
                $_SESSION['user_id']
            ]);

            // Create pending transfer_in transaction (destination) linked by reference_txn_id
            $inId = $this->db->insert("
                INSERT INTO transactions
                    (txn_ref, txn_type, amount, member_id, savings_account_id, description,
                     transaction_date, status, created_by, reference_txn_id)
                VALUES (?, 'transfer_in', ?, ?, ?, ?, ?, 'pending', ?, ?)
            ", [
                $txnRef . '-IN',
                $amount,
                $toId,
                $toAccount['id'],
                $data['description'] ?? "Transfer from member #{$fromId}",
                $_SESSION['user_id'],
                $outId   // link to the source transaction
            ]);

            // Update reference_txn_id of the source to point to destination (bidirectional link)
            $this->db->execute("UPDATE transactions SET reference_txn_id = ? WHERE id = ?", [$inId, $outId]);

            $this->auth->logAudit($_SESSION['user_id'], 'transfer_requested', 'savings', null, null,
                "Transfer request of " . Format::currency($amount) . " from member #{$fromId} to #{$toId}");

            // Notify finance managers
            $this->notifyFinanceManagers(
                "New Transfer Request",
                "Transfer of " . Format::currency($amount) . " requested from member #{$fromId} to #{$toId}. Reference: {$txnRef}"
            );

            $this->db->commit();
            $this->jsonSuccess(['txn_ref' => $txnRef], 'Transfer request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Failed to submit transfer request: ' . $e->getMessage(), null, 500);
        }
    }

    /** Post monthly interest to all eligible savings accounts. */
    public function postInterest(): void
    {
        $this->auth->requirePermission('savings.edit');
        $this->verifyCsrf();

        $today = date('Y-m-d');
        $accounts = $this->db->fetchAll("SELECT * FROM savings_accounts WHERE status = 'active' AND (last_interest_posted IS NULL OR last_interest_posted < DATE_SUB(?, INTERVAL 1 MONTH))", [$today]);

        $posted = 0;
        foreach ($accounts as $acc) {
            $interestRate = (float)($acc['interest_rate'] > 0 ? $acc['interest_rate'] : $this->getSettings()['savings_interest_rate'] ?? 0);
            if ($interestRate <= 0) continue;

            $balance = (float)$acc['balance'];
            $monthlyInterest = $balance * ($interestRate / 100) / 12;
            if ($monthlyInterest <= 0) continue;

            $this->db->beginTransaction();
            try {
                $newBalance = $balance + $monthlyInterest;
                $this->db->execute("UPDATE savings_accounts SET balance = balance + ?, interest_accrued = interest_accrued + ?, last_interest_posted = ? WHERE id = ?", [$monthlyInterest, $monthlyInterest, $today, $acc['id']]);

                $txnRef = 'INT-' . strtoupper(uniqid());
                $this->db->execute("
                    INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, description, transaction_date, balance_before, balance_after, status, created_by)
                    VALUES (?, 'interest', ?, ?, ?, 'Monthly interest posting', ?, ?, ?, 'completed', ?)
                ", [$txnRef, $monthlyInterest, $acc['member_id'], $acc['id'], $today, $balance, $newBalance, $_SESSION['user_id']]);

                $this->db->commit();
                $posted++;
            } catch (\Exception $e) {
                $this->db->rollback();
                error_log("Interest posting failed for account {$acc['id']}: " . $e->getMessage());
            }
        }
        $this->jsonSuccess(['posted' => $posted], "Interest posted to {$posted} account(s).");
    }

    /** Show savings account details with chart data. */
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

        // Chart data: last 12 months balance history
        $chartData = $this->db->fetchAll("
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month, balance_after
            FROM transactions
            WHERE savings_account_id = ? AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            ORDER BY transaction_date ASC
        ", [$id]);
        // Aggregate by month (take the last transaction of each month)
        $months = [];
        $balances = [];
        foreach ($chartData as $row) {
            $months[] = $row['month'];
            $balances[] = (float)$row['balance_after'];
        }

        $this->view('savings/show', compact('account','transactions','months','balances') + $this->getCommonViewData('Account — ' . $account['account_no'], 'savings'));
    }

    /** Generate printable statement PDF. */
    public function statement(int $id): void
    {
        $this->auth->requirePermission('savings.view');
        $account = $this->db->fetchOne("SELECT sa.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone FROM savings_accounts sa JOIN members m ON m.id=sa.member_id WHERE sa.id=?", [$id]);
        if (!$account) { $this->flash('error', 'Account not found.'); $this->redirect('/savings'); }

        $from = $this->getQuery('from', date('Y-m-d', strtotime('-1 year')));
        $to   = $this->getQuery('to', date('Y-m-d'));
        $transactions = $this->db->fetchAll("SELECT * FROM transactions WHERE savings_account_id = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date ASC", [$id, $from, $to]);

        // Simple printable HTML (no PDF library – just a print-friendly view)
        $this->view('savings/statement', compact('account','transactions','from','to'), null);
    }

    /** Update savings goal (target amount and date). */
    public function updateGoal(int $id): void
    {
        $this->auth->requirePermission('savings.edit');
        $this->verifyCsrf();
        $data = $this->getPost();
        $targetAmount = !empty($data['target_amount']) ? (float)$data['target_amount'] : null;
        $targetDate   = !empty($data['target_date']) ? $data['target_date'] : null;
        $this->db->execute("UPDATE savings_accounts SET target_amount = ?, target_date = ? WHERE id = ?", [$targetAmount, $targetDate, $id]);
        $this->jsonSuccess(null, 'Savings goal updated.');
    }

    // ── Private helpers ──────────────────────────────────────────

    private function getCommonViewData(string $title, string $active): array
    {
        return [
            'settings' => $this->getSettings(),
            'unreadNotifications' => $this->getUnreadCount(),
            'pageTitle' => $title,
            'activePage' => $active,
            'breadcrumbs' => ['Savings' => APP_URL.'/savings', $title => null]
        ];
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