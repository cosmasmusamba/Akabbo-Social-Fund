<?php
// We should notify both approver and applicant use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Ensure to use ApprovalController for DRY and consistence. Enforce logAudit and (RBAC + IDOR). Enforce audit COMPLIANCE_AUDIT.md 15. Service Fees for Account Enquiries, 13. Pending Commitment Protection. Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None). Prevent overdraft if so, processes it a loan with users concent to terms with other loan mechanisms applicable.
namespace App\Controllers;

use App\Helpers\Format;
use App\Services\NotificationService;
use App\Models\Approval;
use Database;

class SavingsController extends BaseController
{
    private NotificationService $notif;
    private Approval $approval;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->notif = new NotificationService();
        $this->approval = new Approval();
    }

    // ── CRUD METHODS ───────────────────────────────────────────────
    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'savings.view', 'group' => 'groups.view_members', 'personal' => 'savings.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view savings.');

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $search   = $this->getQuery('search', '');
        $status   = $this->getQuery('status', '');
        $memberId = $this->getQuery('member', '');

        // ENFORCE: Resolve Scope & Build SQL Condition
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'sa.member_id');

        $where  = ['sa.status != "closed"'];
        $params = [];

        if ($search) {
            $like    = "%{$search}%";
            $where[] = "(sa.account_no LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ?)";
            array_push($params, $like, $like, $like);
        }
        if ($status) { $where[] = 'sa.status = ?'; $params[] = $status; }
        if ($memberId) { $where[] = 'sa.member_id = ?'; $params[] = (int)$memberId; }

        $whereSql = 'WHERE ' . implode(' AND ', $where) . " {$scopeCondition}";
        $offset   = ($page - 1) * $limit;

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM savings_accounts sa JOIN members m ON m.id=sa.member_id {$whereSql}", $params);
        $data  = $this->db->fetchAll("
            SELECT sa.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone, m.avatar
            FROM savings_accounts sa JOIN members m ON m.id = sa.member_id
            {$whereSql} ORDER BY sa.balance DESC, sa.created_at DESC LIMIT {$limit} OFFSET {$offset}", $params);

        $summarySql = "SELECT COALESCE(SUM(balance),0) AS total_savings, COUNT(*) AS total_accounts, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_accounts, SUM(CASE WHEN status='dormant' THEN 1 ELSE 0 END) AS dormant_accounts FROM savings_accounts sa WHERE sa.status != 'closed' {$scopeCondition}";
        $summaryStats = $this->db->fetchOne($summarySql);

        $this->view('savings/index', array_merge(
            $this->prepareViewData('Savings Accounts', 'savings'),
            ['result' => ['data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $limit, 'last_page' => (int)ceil($total/$limit), 'from' => $total > 0 ? ($page - 1) * $limit + 1 : 0, 'to' => min($page * $limit, $total)], 'summaryStats' => $summaryStats]
        ));
    }

    public function showDeposit(): void
    {
        $this->auth->requirePermission('savings.deposit');
        
        // ENFORCE: Centralized Scope Gatekeeper & SQL Builder
        $perms = ['global' => 'savings.deposit', 'group' => 'groups.view_members', 'personal' => 'savings.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to record deposits.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'id'); // 'id' refers to members.id
        
        $members  = $this->db->fetchAll("
            SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone 
            FROM members 
            WHERE status='active' {$scopeCondition} 
            ORDER BY first_name LIMIT 100
        ");
        
        $memberId = $this->getQuery('member', null);
        $accountId = $this->getQuery('account', null);
        $member = null;
        
        if ($memberId) {
            $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [(int)$memberId]);
        } elseif ($accountId) {
            $member = $this->db->fetchOne("SELECT m.* FROM savings_accounts sa JOIN members m ON m.id=sa.member_id WHERE sa.id = ?", [(int)$accountId]);
        }

        // ENFORCE: IDOR Guard
        if ($member && !$this->canAccessMemberRecord($member['id'])) {
            $this->flash('error', 'You do not have permission to access this member.');
            $this->redirect('/savings');
        }

        $this->view('savings/deposit', array_merge(
            $this->prepareViewData('Record Deposit', 'savings', ['Savings' => APP_URL.'/savings', 'Deposit' => null]),
            ['members' => $members, 'member' => $member]
        ));
    }

    public function deposit(): void
    {
        $this->auth->requirePermission('savings.deposit');
        $this->verifyCsrf();
        $data = $this->getPost();
        $this->validateRequired($data, ['member_id','amount','payment_method']);
        $memberId = (int)$data['member_id'];

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($memberId)) {
            $this->jsonError('You do not have permission to deposit for this member.', null, 403);
        }

        $amount   = (float)$data['amount'];
        if ($amount <= 0) $this->jsonError('Deposit amount must be greater than zero.', null, 422);
        
        $member = $this->db->fetchOne("SELECT first_name, user_id FROM members WHERE id = ?", [$memberId]);
        $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' AND status = 'active' LIMIT 1", [$memberId]);
        
        if (!$account) {
            $memberJoinDate = $this->db->fetchColumn("SELECT membership_date FROM members WHERE id = ?", [$memberId]);
            $accountNo = \App\Helpers\SavingsAccountSequence::nextFormatted($memberJoinDate);
            $this->db->execute(
                "INSERT INTO savings_accounts (account_no, member_id, account_type, balance, status, opened_at, created_by, created_at, updated_at)
                 VALUES (?, ?, 'regular', 0.00, 'active', CURDATE(), ?, NOW(), NOW())",
                [$accountNo, $memberId, $_SESSION['user_id']]
            );
            $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' LIMIT 1", [$memberId]);
        }

        $this->db->beginTransaction();
        try {
            $balanceBefore = (float)$account['balance'];
            $grossDeposit = $amount;
            
            $runningBalance = $balanceBefore + $grossDeposit;
            $txnRef = 'TXN-' . strtoupper(uniqid());
            $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, external_ref, description, transaction_date, balance_before, balance_after, status, created_by) VALUES (?, 'deposit', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)", [
                $txnRef, $grossDeposit, $memberId, $account['id'], $data['payment_method'], $data['external_ref'] ?? null, $data['description'] ?? 'Savings deposit', date('Y-m-d'), $balanceBefore, $runningBalance, $_SESSION['user_id']
            ]);

            // COMPLIANCE: Settle Pending Fees (Audit Sec 15)
            $feeService = new \App\Services\ServiceFeeService();
            $settlement = $feeService->settlePendingDebits($memberId, $_SESSION['user_id'], $runningBalance);
            
            foreach ($settlement['ledger_entries'] as $feeLedger) {
                $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, balance_before, balance_after, transaction_date, status, created_by) VALUES (?, ?, ?, ?, ?, 'internal', ?, ?, ?, CURDATE(), 'completed', ?)", [
                    $feeLedger['txn_ref'], $feeLedger['txn_type'], $feeLedger['amount'], $memberId, $account['id'], $feeLedger['description'], $feeLedger['balance_before'], $feeLedger['balance_after'], $_SESSION['user_id']
                ]);
            }
            
            $finalBalance = $settlement['final_balance'] ?? $runningBalance;
            $totalFeesSettled = $settlement['fees_settled'] ?? 0.0;

            $this->db->execute("UPDATE savings_accounts SET balance = ? WHERE id = ?", [$finalBalance, $account['id']]);

            // ENFORCE: Notify Applicant via multi-channel dispatch
            if (!empty($member['user_id'])) {
                $msg = "Dear {$member['first_name']}, a deposit of " . Format::currency($grossDeposit) . " has been successfully credited to your savings account.";
                if ($totalFeesSettled > 0) {
                    $msg .= " Note: " . Format::currency($totalFeesSettled) . " was automatically deducted to settle pending service fees.";
                }
                $msg .= " New balance: " . Format::currency($finalBalance) . ".";
                
                $this->notif->dispatch((int)$member['user_id'], 'deposit_success', 'Deposit Successful', $msg);
            }

            $this->logAudit('deposit', 'savings', $account['id'], 'SavingsAccount',
                "Deposit of " . Format::currency($grossDeposit) . " to account {$account['account_no']}" . ($totalFeesSettled > 0 ? " (Fees settled: " . Format::currency($totalFeesSettled) . ")" : ""),
                json_encode(['balance' => $balanceBefore]), json_encode(['balance' => $finalBalance]));

            $this->db->commit();
            
            $this->jsonSuccess([
                'redirect' => APP_URL . '/savings/' . $account['id'], 
                'balance_after' => $finalBalance, 
                'balance_fmt' => Format::currency($finalBalance)
            ], 'Deposit of ' . Format::currency($grossDeposit) . ' recorded successfully!' . ($totalFeesSettled > 0 ? ' (Pending fees of ' . Format::currency($totalFeesSettled) . ' were also settled).' : ''));
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('deposit_failed', 'Deposit Processing Failed', "Failed to process deposit for member ID {$memberId}: " . $e->getMessage());
            $this->jsonError('Failed to process deposit.', null, 500);
        }
    }

    public function showWithdraw(): void
    {
        $this->auth->requirePermission('savings.withdraw');
        
        // ENFORCE: Centralized Scope Gatekeeper & SQL Builder
        $perms = ['global' => 'savings.withdraw', 'group' => 'groups.view_members', 'personal' => 'savings.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to process withdrawals.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'id');
        
        $members  = $this->db->fetchAll("
            SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone 
            FROM members 
            WHERE status='active' {$scopeCondition} 
            ORDER BY first_name LIMIT 100
        ");
        
        $memberId = $this->getQuery('member', null);
        $accountId = $this->getQuery('account', null);
        
        $member = null;
        $account = null;
        
        if ($accountId) {
            $account = $this->db->fetchOne("SELECT sa.*, m.first_name, m.last_name, m.member_no, m.phone, m.user_id, m.id AS member_id FROM savings_accounts sa JOIN members m ON m.id = sa.member_id WHERE sa.id = ?", [(int)$accountId]);
            if ($account) {
                $member = $account;
                $memberId = $account['member_id'];
            }
        } elseif ($memberId) {
            $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [(int)$memberId]);
            if ($member) {
                $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [(int)$memberId]);
            }
        }

        // ENFORCE: IDOR Guard
        if ($member && !$this->canAccessMemberRecord($member['id'])) {
            $this->flash('error', 'You do not have permission to access this member.');
            $this->redirect('/savings');
        }
        
        $this->view('savings/withdraw', array_merge(
            $this->prepareViewData('Process Withdrawal', 'savings', ['Savings' => APP_URL.'/savings', 'Withdraw' => null]),
            ['members' => $members, 'member' => $member, 'account' => $account]
        ));
    }

    public function withdraw(): void
    {
        $this->auth->requirePermission('savings.withdraw');
        $this->verifyCsrf();
        $data = $this->getPost();
        $this->validateRequired($data, ['member_id','amount','payment_method']);
        
        $memberId = (int)$data['member_id'];

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($memberId)) {
            $this->jsonError('You do not have permission to withdraw for this member.', null, 403);
        }

        // Check for active guarantor obligations (Audit Sec 14)
        $activeGuarantees = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM loan_guarantors lg
            JOIN loans l ON l.id = lg.loan_id
            WHERE lg.guarantor_member_id = ? AND l.status IN ('active', 'disbursed', 'pending') AND lg.status = 'confirmed'
        ", [$memberId]);

        if ($activeGuarantees > 0) {
            $settings = $this->getSettings();
            if (filter_var($settings['restrict_guarantor_withdrawals'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
                $this->jsonError("Action restricted: You have active guarantee obligations. Please contact administration to lift restrictions.", null, 403);
            }
        }

        $amount   = (float)$data['amount'];
        if ($amount <= 0) $this->jsonError('Withdrawal amount must be greater than zero.', null, 422);
        
        $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' AND status = 'active' LIMIT 1", [$memberId]);
        if (!$account) {
            $memberJoinDate = $this->db->fetchColumn("SELECT membership_date FROM members WHERE id = ?", [$memberId]);
            $accountNo = \App\Helpers\SavingsAccountSequence::nextFormatted($memberJoinDate);
            $this->db->execute("INSERT INTO savings_accounts (account_no, member_id, account_type, balance, status, opened_at, created_by, created_at, updated_at) VALUES (?, ?, 'regular', 0.00, 'active', CURDATE(), ?, NOW(), NOW())", [$accountNo, $memberId, $_SESSION['user_id']]);
            $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND account_type = 'regular' LIMIT 1", [$memberId]);
        }

        $activeLoanBalance = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')", [$memberId]);
        $settings = $this->getSettings();
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
        $minRequired = $activeLoanBalance / $multiplier;
        $minSavings = (float)($settings['min_savings'] ?? 10000);
        
        // COMPLIANCE: Pending Commitment Protection (Audit Sec 13)
        $pendingWithdrawals = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id = ? AND status = 'pending' AND txn_type = 'withdrawal'", [$memberId]);
        $pendingTransfers = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM fund_transfers WHERE from_member_id = ? AND status = 'pending'", [$memberId]);
        $totalPending = $pendingWithdrawals + $pendingTransfers;

        $available = max(0, (float)$account['balance'] - $minSavings - $minRequired - $totalPending);

        if ($amount > $available) {
            $this->jsonError("Withdrawal exceeds available amount (accounting for pending requests and collateral). Maximum: " . Format::currency($available), null, 422);
        }

        $member = $this->db->fetchOne("SELECT first_name, user_id FROM members WHERE id = ?", [$memberId]);

        $this->db->beginTransaction();
        try {
            $txnRef = 'WDR-' . strtoupper(uniqid());
            $balanceBefore = (float)$account['balance'];
            $balanceAfter = $balanceBefore - $amount; 
            
            $txnId = $this->db->insert("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, transaction_date, balance_before, balance_after, status, created_by) VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)", [
                $txnRef, $amount, $memberId, $account['id'], $data['payment_method'], $data['description'] ?? 'Savings withdrawal request', date('Y-m-d'), $balanceBefore, $balanceAfter, $_SESSION['user_id']
            ]);

            // DRY: Route through centralized approval engine
            $this->approval->request('withdrawal', $txnId, $txnRef, $amount, $_SESSION['user_id'], 
                "Withdrawal request of " . Format::currency($amount) . " from account {$account['account_no']}. " . ($data['description'] ?? ''));
            
            // ENFORCE: Notify Approvers
            $this->notif->notifyApprovers('withdrawal', $txnRef, $amount, $data['description'] ?? 'Savings withdrawal request');

            // ENFORCE: Notify Applicant via multi-channel dispatch
            if (!empty($member['user_id'])) {
                $this->notif->dispatch((int)$member['user_id'], 'withdrawal_requested', 'Withdrawal Request Submitted',
                    "Dear {$member['first_name']}, your withdrawal request of " . Format::currency($amount) . " has been submitted and is pending approval.");
            }

            $this->logAudit('withdrawal_requested', 'savings', $account['id'], 'SavingsAccount',
                "Withdrawal request of " . Format::currency($amount) . " from account {$account['account_no']}",
                json_encode(['balance' => $balanceBefore, 'status' => 'pending']), json_encode(['balance' => $balanceAfter, 'status' => 'pending']));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/savings'], 'Withdrawal request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('withdrawal_failed', 'Withdrawal Request Failed', "Failed to submit withdrawal request for member ID {$memberId}: " . $e->getMessage());
            $this->jsonError('Failed to submit withdrawal request: ' . $e->getMessage(), null, 500);
        }
    }

    public function showTransfer(): void
    {
        $this->auth->requirePermission('transfers.create');
        
        // ENFORCE: Centralized Scope Gatekeeper & SQL Builder
        $perms = ['global' => 'transfers.create', 'group' => 'groups.view_members', 'personal' => 'savings.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to transfer savings.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 'id');
        
        $members = $this->db->fetchAll("
            SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone 
            FROM members 
            WHERE status='active' {$scopeCondition} 
            ORDER BY first_name LIMIT 100
        ");
        
        $fromMember = null;
        $memberId = $this->getQuery('member');
        
        if ($memberId) {
            $fromMember = $this->db->fetchOne("SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone FROM members WHERE id = ? AND status='active'", [(int)$memberId]);
        }

        // ENFORCE: IDOR Guard
        if ($fromMember && !$this->canAccessMemberRecord($fromMember['id'])) {
            $this->flash('error', 'You do not have permission to access this member.');
            $this->redirect('/savings');
        }

        $this->view('savings/transfer', array_merge(
            $this->prepareViewData('Transfer Savings', 'savings', ['Savings' => APP_URL.'/savings', 'Transfer' => null]),
            ['members' => $members, 'fromMember' => $fromMember]
        ));
    }

    public function transfer(): void
    {
        $this->auth->requirePermission('transfers.create');
        $this->verifyCsrf();
        $data = $this->getPost();
        $this->validateRequired($data, ['from_member_id','to_member_id','amount','description']);

        $fromId = (int)$data['from_member_id'];

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($fromId)) {
            $this->jsonError('You do not have permission to transfer from this member.', null, 403);
        }

        // Check for active guarantor obligations
        $activeGuarantees = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM loan_guarantors lg
            JOIN loans l ON l.id = lg.loan_id
            WHERE lg.guarantor_member_id = ? AND l.status IN ('active', 'disbursed', 'pending') AND lg.status = 'confirmed'
        ", [$fromId]);

        if ($activeGuarantees > 0) {
            $settings = $this->getSettings();
            if (filter_var($settings['restrict_guarantor_withdrawals'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
                $this->jsonError("Action restricted: You have active guarantee obligations. Please contact administration to lift restrictions.", null, 403);
            }
        }
        
        $toId   = (int)$data['to_member_id'];
        $amount = (float)$data['amount'];

        if ($fromId === $toId) $this->jsonError('Cannot transfer to the same member.', null, 422);
        if ($amount <= 0) $this->jsonError('Amount must be greater than zero.', null, 422);
        if (strlen(trim($data['description'])) < 10) {
            $this->jsonError('Transfer description must be at least 10 characters.', null, 422);
        }

        $fromAccount = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [$fromId]);
        $toAccount   = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [$toId]);
        if (!$fromAccount) $this->jsonError('Source member has no active savings account.', null, 404);
        if (!$toAccount) $this->jsonError('Destination member has no active savings account.', null, 404);

        $activeLoanBalance = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')", [$fromId]);
        $settings = $this->getSettings();
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
        $minRequired = $activeLoanBalance / $multiplier;
        $minSavings = (float)($settings['min_savings'] ?? 10000);

        // COMPLIANCE: Pending Commitment Protection (Audit Sec 13)
        $pendingWithdrawals = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id = ? AND status = 'pending' AND txn_type = 'withdrawal'", [$fromId]);
        $pendingTransfers = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM fund_transfers WHERE from_member_id = ? AND status = 'pending'", [$fromId]);
        $totalPending = $pendingWithdrawals + $pendingTransfers;

        $available = max(0, (float)$fromAccount['balance'] - $minSavings - $minRequired - $totalPending);

        if ($amount > $available) {
            $this->jsonError("Insufficient available balance (accounting for pending requests and collateral). Maximum transferable: " . Format::currency($available), null, 422);
        }

        $fromMember = $this->db->fetchOne("SELECT first_name, user_id FROM members WHERE id = ?", [$fromId]);

        $this->db->beginTransaction();
        try {
            $ref = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $transferId = (int)$this->db->insert("INSERT INTO fund_transfers (transfer_ref, from_member_id, to_member_id, amount, description, transfer_date, status, created_by) VALUES (?, ?, ?, ?, ?, CURDATE(), 'pending', ?)", [$ref, $fromId, $toId, $amount, $data['description'], $_SESSION['user_id']]);

            // DRY: Route through centralized approval engine
            $this->approval->request('transfer', $transferId, $ref, $amount, $_SESSION['user_id'], "Transfer of " . Format::currency($amount) . " from member #{$fromId} to member #{$toId}. " . $data['description']);

            // ENFORCE: Notify Approvers
            $this->notif->notifyApprovers('transfer', $ref, $amount, $data['description']);

            // ENFORCE: Notify Applicant (From Member) via multi-channel dispatch
            if (!empty($fromMember['user_id'])) {
                $this->notif->dispatch((int)$fromMember['user_id'], 'transfer_requested', 'Transfer Request Submitted',
                    "Dear {$fromMember['first_name']}, your transfer request of " . Format::currency($amount) . " has been submitted and is pending approval.");
            }

            $this->logAudit('transfer_requested', 'transfers', $transferId, 'FundTransfer', 
                "Transfer {$ref}: " . Format::currency($amount) . " from M#{$fromId} to M#{$toId}", 
                null, json_encode(['from_member' => $fromId, 'to_member' => $toId, 'amount' => $amount, 'status' => 'pending']));

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/transfers'], 'Transfer request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('transfer_failed', 'Transfer Request Failed', "Failed to submit transfer request for member ID {$fromId}: " . $e->getMessage());
            $this->jsonError('Failed to submit transfer request: ' . $e->getMessage(), null, 500);
        }
    }

    public function postInterest(): void
    {
        $this->auth->requirePermission('savings.edit');
        $this->verifyCsrf();
        
        try {
            $model = new \App\Models\SavingsAccount();
            $posted = $model->applyInterest();
            $this->logAudit('interest_posted', 'savings', null, null, "Monthly interest posted to {$posted} account(s)");
            
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('interest_posted', 'Monthly Interest Posted', "Monthly interest has been successfully posted to {$posted} savings account(s).");
            
            $this->jsonSuccess(['posted' => $posted], "Interest posted to {$posted} account(s).");
        } catch (\Exception $e) {
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('interest_post_failed', 'Interest Posting Failed', "Failed to post monthly interest: " . $e->getMessage());
            $this->jsonError('Failed to post interest: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        $account = $this->db->fetchOne("
            SELECT sa.*, m.first_name, m.last_name, m.member_no, m.phone, m.avatar, m.id AS member_id, m.email
            FROM savings_accounts sa JOIN members m ON m.id = sa.member_id WHERE sa.id = ?
        ", [$id]);

        if (!$account) {
            $this->flash('error', 'Account not found.');
            $this->redirect('/savings');
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($account['member_id'])) {
            $this->flash('error', 'You do not have permission to view this account.');
            $this->redirect('/dashboard');
        }

        // COMPLIANCE FIX: Service Fee for Balance Enquiry (Audit Sec 15)
        $user = $this->auth->user();
        $isOwnAccount = !empty($user['member_id']) && (int)$user['member_id'] === $account['member_id'];

        if ($isOwnAccount) {
            $feeService = new \App\Services\ServiceFeeService();
            $result = $feeService->processFee($account['member_id'], 'balance_inquiry', $_SESSION['user_id']);
            
            if (!$result['success']) {
                $this->flash('error', $result['message'] ?? 'Failed to process balance enquiry fee.');
                $this->redirect('/dashboard');
            }
        }

        $transactions = $this->db->fetchAll("SELECT * FROM transactions WHERE savings_account_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 50", [$id]);

        // ENFORCE: Audit log for viewing sensitive financial data
        $this->logAudit('savings_viewed', 'savings', $id, 'SavingsAccount', "Viewed account {$account['account_no']} for member #{$account['member_id']}");

        $this->view('savings/show', array_merge(
            $this->prepareViewData('Account — ' . $account['account_no'], 'savings', ['Savings' => APP_URL.'/savings', $account['account_no'] => null]),
            ['account' => $account, 'transactions' => $transactions]
        ));
    }

    public function statement(int $id): void
    {
        $this->auth->requirePermission('savings.view');
        $account = $this->db->fetchOne("SELECT sa.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone, m.id AS member_id FROM savings_accounts sa JOIN members m ON m.id=sa.member_id WHERE sa.id=?", [$id]);
        
        if (!$account) { 
            $this->flash('error', 'Account not found.'); 
            $this->redirect('/savings'); 
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($account['member_id'])) {
            $this->flash('error', 'You do not have permission to view this statement.');
            $this->redirect('/dashboard');
        }

        // COMPLIANCE FIX: Service Fee for Statement Request (Audit Sec 15)
        $user = $this->auth->user();
        $isOwnAccount = !empty($user['member_id']) && (int)$user['member_id'] === $account['member_id'];

        if ($isOwnAccount) {
            $feeService = new \App\Services\ServiceFeeService();
            $result = $feeService->processFee($account['member_id'], 'statement_request', $_SESSION['user_id']);
            
            if (!$result['success']) {
                $this->flash('error', $result['message'] ?? 'Failed to process statement request fee.');
                $this->redirect('/savings/' . $id);
            }
        }

        $from = $this->getQuery('from', date('Y-m-d', strtotime('-1 year')));
        $to   = $this->getQuery('to', date('Y-m-d'));
        $transactions = $this->db->fetchAll("SELECT * FROM transactions WHERE savings_account_id = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date ASC", [$id, $from, $to]);

        // ENFORCE: Audit log for viewing statement
        $this->logAudit('statement_viewed', 'savings', $id, 'SavingsAccount', "Viewed statement for account {$account['account_no']} from {$from} to {$to}");

        $this->view('savings/statement', [
            'pageTitle' => 'Statement — ' . $account['account_no'], 
            'account' => $account, 
            'transactions' => $transactions, 
            'from' => $from, 
            'to' => $to
        ], null);
    }

    public function updateGoal(int $id): void
    {
        $this->auth->requirePermission('savings.edit');
        $this->verifyCsrf();
        
        $account = $this->db->fetchOne("SELECT member_id FROM savings_accounts WHERE id = ?", [$id]);
        if (!$account) {
            $this->jsonError('Account not found.', null, 404);
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($account['member_id'])) {
            $this->jsonError('You do not have permission to update this account.', null, 403);
        }

        $data = $this->getPost();
        
        $oldGoal = $this->db->fetchOne("SELECT target_amount, target_date FROM savings_accounts WHERE id = ?", [$id]);
        $targetAmount = !empty($data['target_amount']) ? (float)$data['target_amount'] : null;
        $targetDate   = !empty($data['target_date']) ? $data['target_date'] : null;
        
        $this->db->execute("UPDATE savings_accounts SET target_amount = ?, target_date = ? WHERE id = ?", [$targetAmount, $targetDate, $id]);
        
        $this->logAudit('goal_updated', 'savings', $id, 'SavingsAccount', "Savings goal updated", json_encode($oldGoal), json_encode(['target_amount' => $targetAmount, 'target_date' => $targetDate]));
        $this->jsonSuccess(null, 'Savings goal updated.');
    }

    // ── HELPER: Notify all admins via multi-channel dispatch ────────────────────────────────
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('savings.edit', 'savings.view') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}