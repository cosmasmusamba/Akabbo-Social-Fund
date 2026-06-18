<?php
// We should notify both approver and applicant use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Enforce logAudit and (RBAC + IDOR). Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use App\Models\Approval;
use App\Services\NotificationService;
use App\Helpers\Format;

class ApprovalController extends BaseController
{
    private Approval $model;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Approval();
        $this->notif = new NotificationService();
    }

    // ── QUEUE & VIEWING ────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'approvals.view', 'group' => 'groups.view_members', 'personal' => 'members.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view approvals.');
        $scope = $this->resolveDataScope($perms);

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        
        $filters = [
            'status'         => $this->getQuery('status', 'pending'),
            'reference_type' => $this->getQuery('type', ''),
        ];

        // ENFORCE: Apply Data Scope to the Approval Queue
        if ($scope['type'] === 'personal') {
            $filters['requested_by'] = $_SESSION['user_id'];
        } elseif ($scope['type'] === 'group') {
            $filters['scope_group_id'] = $scope['group_id'];
        } elseif ($scope['type'] === 'none') {
            $filters['requested_by'] = -1; // Force empty
        }

        // Approvers see pending items assigned to them or unassigned within their scope
        if ($this->auth->can('approvals.process') && $scope['type'] !== 'personal') {
            $filters['assigned_to_or_null'] = $_SESSION['user_id'];
        }

        $result = $this->model->getQueue($page, $limit, $filters);
        $stats  = $this->model->getStats($scope);

        // ENFORCE: Audit log
        $this->logAudit('approvals_viewed', 'approvals', null, null, "Viewed approval queue (Scope: {$scope['type']})");

        $this->view('approvals/index', array_merge(
            $this->prepareViewData('Approval Queue', 'approvals'),
            ['result' => $result, 'stats' => $stats, 'filters' => $filters]
        ));
    }

    public function show(int $id): void
    {
        $perms = ['global' => 'approvals.view', 'group' => 'groups.view_members', 'personal' => 'members.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view approvals.');
        $scope = $this->resolveDataScope($perms);
        
        $approval = $this->db->fetchOne("
            SELECT a.*, 
                   CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name,
                   CONCAT(ur.first_name, ' ', ur.last_name) AS reviewed_by_name,
                   TIMESTAMPDIFF(HOUR, a.requested_at, NOW()) AS hours_waiting,
                   CASE WHEN a.due_by < NOW() AND a.status='pending' THEN 1 ELSE 0 END AS is_overdue,
                   u.member_id AS requester_member_id
            FROM approvals a
            JOIN users u ON u.id = a.requested_by
            LEFT JOIN users ur ON ur.id = a.reviewed_by
            WHERE a.id = ?
        ", [$id]);

        if (!$approval) {
            $this->flash('error', 'Approval not found.');
            $this->redirect('/approvals');
        }

        // ENFORCE: IDOR / Scope Guard
        if ($scope['type'] === 'personal' && (int)$approval['requested_by'] !== (int)$_SESSION['user_id']) {
            $this->flash('error', 'You do not have permission to view this request.');
            $this->redirect('/approvals');
        }
        
        if ($scope['type'] === 'group') {
            $requesterGroupId = $this->db->fetchColumn("SELECT group_id FROM members WHERE id = ?", [$approval['requester_member_id']]);
            if (!$requesterGroupId || (int)$requesterGroupId !== (int)$scope['group_id']) {
                $this->flash('error', 'You do not have permission to view this request.');
                $this->redirect('/approvals');
            }
        }

        // IDOR / Assignment Check: Prevent viewing if assigned to someone else
        if (!empty($approval['assigned_to']) && (int)$approval['assigned_to'] !== (int)$_SESSION['user_id'] && !$this->auth->isSuperAdmin() && !$this->auth->can('users.delete')) {
            $this->flash('error', 'You do not have permission to view this assigned request.');
            $this->redirect('/approvals');
        }

        $refData = $this->getReferencedRecord($approval['reference_type'], $approval['reference_id']);
        
        $financialContext = null;
        $memberId = is_array($refData) ? ($refData['member_id'] ?? ($refData['from_member_id'] ?? null)) : null;
        
        if ($memberId && in_array($approval['reference_type'], ['withdrawal', 'transfer', 'disbursement'])) {
            $account = $this->db->fetchOne("SELECT balance FROM savings_accounts WHERE member_id = ? AND status='active' LIMIT 1", [$memberId]);
            $activeLoanBalance = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')", [$memberId]);
            
            $settings = $this->getSettings();
            $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
            $minSavings = (float)($settings['min_savings'] ?? 10000);
            $minRequired = $activeLoanBalance / $multiplier;
            
            $currentBalance = (float)($account['balance'] ?? 0);
            $availableBalance = max(0, $currentBalance - $minRequired - $minSavings);
            
            $financialContext = [
                'current_balance'   => $currentBalance,
                'min_savings'       => $minSavings,
                'loan_collateral'   => $minRequired,
                'available_balance' => $availableBalance,
                'requested_amount'  => (float)$approval['amount'],
                'is_sufficient'     => $availableBalance >= (float)$approval['amount']
            ];
        }

        // ENFORCE: Audit log
        $this->logAudit('approval_viewed', 'approvals', $id, 'Approval', "Viewed approval #{$id} ({$approval['reference_type']})");

        $this->view('approvals/show', array_merge(
            $this->prepareViewData('Approval #' . $id, 'approvals', ['Approvals' => APP_URL.'/approvals', '#'.$id => null]),
            ['approval' => $approval, 'refData' => $refData, 'financialContext' => $financialContext]
        ));
    }

    // ── APPROVAL & REJECTION ACTIONS ───────────────────────────────

    public function approve(int $id): void
    {
        $this->auth->requirePermission('approvals.process');
        $this->verifyCsrf();

        $notes = trim($this->getPost()['approval_notes'] ?? '');
        if (empty($notes) || strlen($notes) < 10) {
            $this->jsonError('Approval notes are mandatory and must be at least 10 characters long.', null, 422);
        }

        $approval = $this->model->find($id);
        if (!$approval || $approval['status'] !== 'pending') {
            $this->jsonError('Approval not found or already processed.', null, 400);
        }

        // IDOR / Assignment Check
        if (!empty($approval['assigned_to']) && (int)$approval['assigned_to'] !== (int)$_SESSION['user_id'] && !$this->auth->isSuperAdmin()) {
            $this->jsonError('This request is assigned to another approver.', null, 403);
        }

        if ((int)$approval['requested_by'] === (int)$_SESSION['user_id']) {
            $this->jsonError('You cannot approve your own request.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            $this->model->approve($id, $_SESSION['user_id'], $notes);
            $this->executeApprovedAction($approval, $notes);

            // ENFORCE: Notify Applicant (Requester) via multi-channel dispatch
            $this->notif->dispatch(
                (int)$approval['requested_by'],
                'request_approved', 
                ucfirst(str_replace('_', ' ', $approval['reference_type'])) . ' Request Approved',
                "Your {$approval['reference_type']} request ({$approval['reference_ref']}) has been approved. Reviewer notes: {$notes}"
            );

            // ENFORCE: Notify Approver (Current User) via multi-channel dispatch
            $this->notif->dispatch(
                (int)$_SESSION['user_id'],
                'approval_completed',
                'Approval Action Completed',
                "You have successfully approved {$approval['reference_type']} #{$approval['reference_ref']}."
            );

            $this->logAudit('approval_granted', 'approvals', $id, 'Approval',
                "Approved {$approval['reference_type']} #{$approval['reference_id']}: {$notes}",
                json_encode(['status' => 'pending']),
                json_encode(['status' => 'approved', 'reviewed_by' => $_SESSION['user_id']])
            );

            $this->db->commit();
            $this->jsonSuccess(null, 'Approved successfully. Action has been executed.');
        } catch (\Exception $e) {
            $this->db->rollback();
            
            // ENFORCE: Notify admins of execution failure
            $this->notifyAdmins('approval_execution_failed', 'Approval Execution Failed', 
                "The approval for {$approval['reference_type']} #{$approval['reference_ref']} failed to execute: " . $e->getMessage());
            
            // Notify approver of execution failure
            $this->notif->dispatch(
                (int)$_SESSION['user_id'],
                'approval_execution_failed',
                'Approval Execution Failed',
                "The approval for {$approval['reference_type']} #{$approval['reference_ref']} failed to execute: " . $e->getMessage()
            );

            $this->logAudit('approval_execution_failed', 'approvals', $id, 'Approval',
                "Execution failed for {$approval['reference_type']} #{$approval['reference_id']}: " . $e->getMessage(),
                json_encode(['status' => 'pending']),
                json_encode(['error' => $e->getMessage()])
            );

            $this->jsonError('Approval failed: ' . $e->getMessage(), null, 500);
        }
    }

    public function reject(int $id): void
    {
        $this->auth->requirePermission('approvals.process');
        $this->verifyCsrf();

        $notes = trim($this->getPost()['rejection_notes'] ?? '');
        if (empty($notes) || strlen($notes) < 10) {
            $this->jsonError('Rejection notes are mandatory and must be at least 10 characters long.', null, 422);
        }

        $approval = $this->model->find($id);
        if (!$approval || $approval['status'] !== 'pending') {
            $this->jsonError('Approval not found or already processed.', null, 400);
        }

        // IDOR / Assignment Check
        if (!empty($approval['assigned_to']) && (int)$approval['assigned_to'] !== (int)$_SESSION['user_id'] && !$this->auth->isSuperAdmin()) {
            $this->jsonError('This request is assigned to another approver.', null, 403);
        }

        if ((int)$approval['requested_by'] === (int)$_SESSION['user_id']) {
            $this->jsonError('You cannot reject your own request.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            $this->model->reject($id, $_SESSION['user_id'], $notes);
            $this->executeRejectedAction($approval);

            // ENFORCE: Notify Applicant (Requester) via multi-channel dispatch
            $this->notif->dispatch(
                (int)$approval['requested_by'],
                'request_rejected',
                ucfirst(str_replace('_', ' ', $approval['reference_type'])) . ' Request Rejected',
                "Your {$approval['reference_type']} request ({$approval['reference_ref']}) has been rejected. Reviewer notes: {$notes}"
            );

            // ENFORCE: Notify Approver (Current User) via multi-channel dispatch
            $this->notif->dispatch(
                (int)$_SESSION['user_id'],
                'rejection_completed',
                'Rejection Action Completed',
                "You have successfully rejected {$approval['reference_type']} #{$approval['reference_ref']}."
            );

            $this->logAudit('approval_rejected', 'approvals', $id, 'Approval',
                "Rejected {$approval['reference_type']} #{$approval['reference_id']}: {$notes}",
                json_encode(['status' => 'pending']),
                json_encode(['status' => 'rejected', 'reviewed_by' => $_SESSION['user_id']])
            );

            $this->db->commit();
            $this->jsonSuccess(null, 'Request rejected.');
        } catch (\Exception $e) {
            $this->db->rollback();
            
            // ENFORCE: Notify admins of execution failure
            $this->notifyAdmins('rejection_execution_failed', 'Rejection Execution Failed', 
                "The rejection for {$approval['reference_type']} #{$approval['reference_ref']} failed: " . $e->getMessage());
            
            // Notify approver of execution failure
            $this->notif->dispatch(
                (int)$_SESSION['user_id'],
                'rejection_execution_failed',
                'Rejection Execution Failed',
                "The rejection for {$approval['reference_type']} #{$approval['reference_ref']} failed: " . $e->getMessage()
            );

            $this->logAudit('rejection_execution_failed', 'approvals', $id, 'Approval',
                "Rejection execution failed for {$approval['reference_type']} #{$approval['reference_id']}: " . $e->getMessage(),
                json_encode(['status' => 'pending']),
                json_encode(['error' => $e->getMessage()])
            );

            $this->jsonError('Rejection failed: ' . $e->getMessage(), null, 500);
        }
    }

    public function pendingCount(): void
    {
        $this->auth->requireAuth();
        $count = $this->model->getPendingCount();
        $this->jsonSuccess(['count' => $count]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // EXECUTION ENGINE
    // ──────────────────────────────────────────────────────────────────────────

    private function executeApprovedAction(array $approval, string $notes): void
    {
        $type  = $approval['reference_type'];
        $refId = (int)$approval['reference_id'];
        $uid   = $_SESSION['user_id'];

        match ($type) {
            'withdrawal'         => $this->executeWithdrawal($refId, $uid, $notes),
            'transfer'           => $this->executeTransfer($refId, $notes, $uid),
            'disbursement'       => $this->executeDisbursement($refId, $uid, $notes),
            'reversal'           => $this->executeReversal($refId, $uid, $notes),
            'share_transaction'  => $this->executeShareApproval($refId, $uid, $notes),
            'loan_application'   => $this->db->execute("UPDATE loans SET status='approved', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW() WHERE id=?", [$uid, $notes, $refId]),
            'expense'            => $this->db->execute("UPDATE expenses SET status='approved', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW() WHERE id=?", [$uid, $notes, $refId]),
            'social_fund_waiver' => $this->db->execute("UPDATE social_fund_fee_payments SET status='waived', waived_by=?, waived_reason=?, updated_at=NOW() WHERE id=?", [$uid, $notes, $refId]),
            'transfer_reversal'  => $this->executeTransferReversal($refId, $uid, $notes),
            default => throw new \InvalidArgumentException("Unknown approval type: {$type}")
        };
    }

    // 1. WITHDRAWAL: Approval-Time Validation & Pending Commitment Protection (Audit Sec 11 & 13)
    private function executeWithdrawal(int $txnId, int $uid, string $notes): void
    {
        $txn = $this->db->fetchOne("SELECT * FROM transactions WHERE id = ? AND txn_type = 'withdrawal' AND status = 'pending'", [$txnId]);
        if (!$txn) throw new \RuntimeException("Pending withdrawal transaction not found.");

        $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE id = ?", [$txn['savings_account_id']]);
        if (!$account) throw new \RuntimeException("Savings account not found.");

        $settings = $this->getSettings();
        $minSavings = (float)($settings['min_savings'] ?? 10000);
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);

        $activeLoanBalance = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')",
            [$account['member_id']]
        );
        $minRequired = $activeLoanBalance / $multiplier;

        $pendingWithdrawals = (float)$this->db->fetchColumn("
            SELECT COALESCE(SUM(amount), 0) FROM transactions 
            WHERE member_id = ? AND savings_account_id = ? AND status = 'pending' AND txn_type = 'withdrawal' AND id != ?
        ", [$account['member_id'], $account['id'], $txnId]);

        $pendingTransfers = (float)$this->db->fetchColumn("
            SELECT COALESCE(SUM(amount), 0) FROM fund_transfers 
            WHERE from_member_id = ? AND status = 'pending'
        ", [$account['member_id']]);

        $totalPendingOutflows = $pendingWithdrawals + $pendingTransfers;
        $projectedBalance = (float)$account['balance'] - $totalPendingOutflows - (float)$txn['amount'];
        
        if ($projectedBalance < $minRequired) {
            throw new \RuntimeException("Insufficient available balance after accounting for pending commitments and loan collateral.");
        }
        if ($projectedBalance < $minSavings) {
            throw new \RuntimeException("Withdrawal would drop balance below minimum required savings of " . Format::currency($minSavings));
        }

        $this->db->execute("UPDATE savings_accounts SET balance = balance - ? WHERE id = ?", [(float)$txn['amount'], $account['id']]);
        $this->db->execute(
            "UPDATE transactions SET status='completed', approved_by=?, approved_at=NOW(), approval_notes=?, balance_after = ? WHERE id=?",
            [$uid, $notes, $projectedBalance, $txnId]
        );
    }

    // 2. TRANSFER: Approval-Time Validation & Pending Commitment Protection (Audit Sec 11 & 13)
    private function executeTransfer(int $refId, string $notes, int $uid): void
    {
        $transfer = $this->db->fetchOne("SELECT * FROM fund_transfers WHERE id = ? AND status = 'pending'", [$refId]);
        if (!$transfer) throw new \RuntimeException("Pending transfer record not found.");

        $fromAcc = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' ORDER BY id LIMIT 1", [$transfer['from_member_id']]);
        $toAcc   = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' ORDER BY id LIMIT 1", [$transfer['to_member_id']]);

        if (!$fromAcc) throw new \RuntimeException("Sender has no active savings account.");
        if (!$toAcc) throw new \RuntimeException("Recipient has no active savings account.");

        $amt = (float)$transfer['amount'];
        $settings = $this->getSettings();
        $minSavings = (float)($settings['min_savings'] ?? 10000);
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);

        $activeLoanBalance = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')",
            [$fromAcc['member_id']]
        );
        $minRequired = $activeLoanBalance / $multiplier;

        $pendingWithdrawals = (float)$this->db->fetchColumn("
            SELECT COALESCE(SUM(amount), 0) FROM transactions 
            WHERE member_id = ? AND savings_account_id = ? AND status = 'pending' AND txn_type = 'withdrawal'
        ", [$fromAcc['member_id'], $fromAcc['id']]);

        $pendingTransfers = (float)$this->db->fetchColumn("
            SELECT COALESCE(SUM(amount), 0) FROM fund_transfers 
            WHERE from_member_id = ? AND status = 'pending' AND id != ?
        ", [$fromAcc['member_id'], $refId]);

        $totalPendingOutflows = $pendingWithdrawals + $pendingTransfers;
        $projectedBalance = (float)$fromAcc['balance'] - $totalPendingOutflows - $amt;
        
        if ($projectedBalance < $minRequired) {
            throw new \RuntimeException("Insufficient available balance after accounting for pending commitments and loan collateral.");
        }
        if ($projectedBalance < $minSavings) {
            throw new \RuntimeException("Transfer would drop balance below minimum required savings of " . Format::currency($minSavings));
        }

        $this->db->execute("UPDATE savings_accounts SET balance = balance - ? WHERE id = ?", [$amt, $fromAcc['id']]);
        $this->db->execute("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?", [$amt, $toAcc['id']]);

        $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, balance_before, balance_after, transaction_date, status, created_by) VALUES (?, 'transfer_out', ?, ?, ?, 'internal', ?, ?, ?, CURDATE(), 'completed', ?)",
            ['TRF-OUT-'.strtoupper(uniqid()), $amt, $transfer['from_member_id'], $fromAcc['id'], "Transfer to M#{$transfer['to_member_id']}", (float)$fromAcc['balance'], $projectedBalance, $uid]
        );
        $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, balance_before, balance_after, transaction_date, status, created_by) VALUES (?, 'transfer_in', ?, ?, ?, 'internal', ?, ?, ?, CURDATE(), 'completed', ?)",
            ['TRF-IN-'.strtoupper(uniqid()), $amt, $transfer['to_member_id'], $toAcc['id'], "Transfer from M#{$transfer['from_member_id']}", (float)$toAcc['balance'], (float)$toAcc['balance']+$amt, $uid]
        );

        $this->db->execute("UPDATE fund_transfers SET status='completed', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW() WHERE id=?", [$uid, $notes, $refId]);
    }

    // 3. DISBURSEMENT: Strict Ledger Separation (Audit Sec 1.2 & 4.2)
    private function executeDisbursement(int $loanId, int $uid, string $notes): void
    {
        $loan = $this->db->fetchOne("SELECT * FROM loans WHERE id = ?", [$loanId]);
        if (!$loan) throw new \RuntimeException("Loan record not found.");

        $amt = (float)$loan['principal_amount'];
        
        $this->db->execute("UPDATE loans SET status='active', disbursement_date=CURDATE(), disbursed_by=?, approval_notes=?, updated_at=NOW() WHERE id=?", [$uid, $notes, $loanId]);

        $loanModel = new \App\Models\Loan();
        $loanModel->generateRepaymentSchedule(
            $loanId, $amt, (float)$loan['interest_rate'], (int)$loan['term_months'], 
            $loan['interest_type'], $loan['first_repayment_date'] ?? date('Y-m-d', strtotime('+1 month'))
        );

        $this->db->execute("
            INSERT INTO transactions 
            (txn_ref, txn_type, amount, member_id, loan_id, payment_method, description, transaction_date, status, created_by) 
            VALUES (?, 'loan_disbursement', ?, ?, ?, ?, ?, CURDATE(), 'completed', ?)",
            ['DISB-'.strtoupper(uniqid()), $amt, $loan['member_id'], $loanId, $loan['disbursement_method'] ?? 'bank_transfer', "Disbursement for loan {$loan['loan_no']}", $uid]
        );
    }

    // 4. REVERSAL: Intelligently reverse balance based on original txn type
    private function executeReversal(int $txnId, int $uid, string $notes): void
    {
        $txn = $this->db->fetchOne("SELECT * FROM transactions WHERE id = ? AND status = 'completed'", [$txnId]);
        if (!$txn) throw new \RuntimeException("Original transaction not found or already reversed.");

        $account = $this->db->fetchOne("SELECT id, balance FROM savings_accounts WHERE id = ?", [$txn['savings_account_id']]);
        if (!$account) throw new \RuntimeException("Associated savings account not found.");

        $amt = (float)$txn['amount'];
        $currentBal = (float)$account['balance'];

        // FIX: Explicitly calculate balance_after based on the exact transaction type being reversed
        if (in_array($txn['txn_type'], ['deposit', 'transfer_in', 'loan_disbursement'])) {
            $this->db->execute("UPDATE savings_accounts SET balance = balance - ? WHERE id = ?", [$amt, $account['id']]);
            $balanceAfter = $currentBal - $amt;
        } elseif (in_array($txn['txn_type'], ['withdrawal', 'transfer_out', 'loan_repayment'])) {
            $this->db->execute("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?", [$amt, $account['id']]);
            $balanceAfter = $currentBal + $amt;
        } else {
            throw new \RuntimeException("Cannot reverse transaction type: {$txn['txn_type']}");
        }

        $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, reference_txn_id, payment_method, description, balance_before, balance_after, transaction_date, status, created_by) VALUES (?, 'reversal', ?, ?, ?, ?, 'internal', ?, ?, ?, CURDATE(), 'completed', ?)",
            ['REV-'.strtoupper(uniqid()), $amt, $txn['member_id'], $account['id'], $txnId, "Reversal of {$txn['txn_ref']}: {$notes}", $currentBal, $balanceAfter, $uid]
        );

        $this->db->execute("UPDATE transactions SET status='reversed', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW() WHERE id=?", [$uid, $notes, $txnId]);
    }

    // 5. SHARE TRANSACTION: Update member shares ledger
    private function executeShareApproval(int $txnId, int $uid, string $notes): void
    {
        $txn = $this->db->fetchOne("SELECT * FROM share_transactions WHERE id = ?", [$txnId]);
        if (!$txn) throw new \RuntimeException("Share transaction not found.");

        if ($txn['txn_type'] === 'purchase') {
            $this->db->execute("
                INSERT INTO member_shares (member_id, shares_held, total_invested, status)
                VALUES (?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE 
                shares_held = shares_held + VALUES(shares_held),
                total_invested = total_invested + VALUES(total_invested)
            ", [$txn['member_id'], $txn['shares_qty'], $txn['total_amount']]);
            
            $this->db->execute("UPDATE members SET is_shareholder = 1 WHERE id = ?", [$txn['member_id']]);
        }

        $this->db->execute("UPDATE share_transactions SET status='completed', approved_by=?, approved_at=NOW(), approval_notes=?, updated_at=NOW() WHERE id=?", [$uid, $notes, $txnId]);
    }

    // 6. TRANSFER REVERSAL: Create compensating ledger entries (Audit Sec 7)
    private function executeTransferReversal(int $refId, int $uid, string $notes): void
    {
        $transfer = $this->db->fetchOne("SELECT * FROM fund_transfers WHERE id = ? AND status = 'completed'", [$refId]);
        if (!$transfer) throw new \RuntimeException("Completed transfer record not found for reversal.");

        $fromAcc = $this->db->fetchOne("SELECT id, balance FROM savings_accounts WHERE member_id=? AND status='active' ORDER BY id LIMIT 1", [$transfer['from_member_id']]);
        $toAcc   = $this->db->fetchOne("SELECT id, balance FROM savings_accounts WHERE member_id=? AND status='active' ORDER BY id LIMIT 1", [$transfer['to_member_id']]);

        if (!$fromAcc || !$toAcc) throw new \RuntimeException("Associated savings accounts not found.");

        $amt = (float)$transfer['amount'];

        $this->db->execute("UPDATE savings_accounts SET balance = balance + ? WHERE id=?", [$amt, $fromAcc['id']]);
        $this->db->execute("UPDATE savings_accounts SET balance = balance - ? WHERE id=?", [$amt, $toAcc['id']]);

        $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, balance_before, balance_after, transaction_date, status, created_by) VALUES (?, 'transfer_reversal_in', ?, ?, ?, 'internal', ?, ?, ?, CURDATE(), 'completed', ?)",
            ['REV-IN-'.strtoupper(uniqid()), $amt, $transfer['from_member_id'], $fromAcc['id'], "Reversal of transfer {$transfer['transfer_ref']}: {$notes}", (float)$fromAcc['balance'], (float)$fromAcc['balance']+$amt, $uid]
        );
        $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, balance_before, balance_after, transaction_date, status, created_by) VALUES (?, 'transfer_reversal_out', ?, ?, ?, 'internal', ?, ?, ?, CURDATE(), 'completed', ?)",
            ['REV-OUT-'.strtoupper(uniqid()), $amt, $transfer['to_member_id'], $toAcc['id'], "Reversal of transfer {$transfer['transfer_ref']}: {$notes}", (float)$toAcc['balance'], (float)$toAcc['balance']-$amt, $uid]
        );

        $this->db->execute("UPDATE fund_transfers SET status='reversed', reversal_notes=?, updated_at=NOW() WHERE id=?", [$notes, $refId]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REJECTION ENGINE
    // ──────────────────────────────────────────────────────────────────────────

    private function executeRejectedAction(array $approval): void
    {
        $type  = $approval['reference_type'];
        $refId = (int)$approval['reference_id'];

        $statusMap = [
            'withdrawal'         => 'rejected',
            'transfer'           => 'rejected',
            'loan_application'   => 'rejected',
            'disbursement'       => 'approved', // Reverts loan back to 'approved' state (ready for next attempt)
            'expense'            => 'rejected',
            'reversal'           => 'completed', // Reversal rejected means original txn stays completed
            'share_transaction'  => 'rejected',
            'social_fund_waiver' => 'pending'
        ];

        if (isset($statusMap[$type])) {
            $table = match ($type) {
                'withdrawal', 'reversal' => 'transactions',
                'transfer'               => 'fund_transfers',
                'disbursement'           => 'loans',
                'expense'                => 'expenses',
                'share_transaction'      => 'share_transactions',
                'social_fund_waiver'     => 'social_fund_fee_payments',
            };

            $this->db->execute("UPDATE `{$table}` SET status=?, updated_at=NOW() WHERE id=?", [$statusMap[$type], $refId]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UTILITIES
    // ──────────────────────────────────────────────────────────────────────────

    private function getReferencedRecord(string $type, int $id): ?array
    {
        return match ($type) {
            'withdrawal', 'reversal' => $this->db->fetchOne("SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no FROM transactions t JOIN members m ON m.id=t.member_id WHERE t.id=?", [$id]),
            'transfer' => $this->db->fetchOne("SELECT ft.*, CONCAT(mf.first_name,' ',mf.last_name) AS from_name, mf.member_no AS from_no, CONCAT(mt.first_name,' ',mt.last_name) AS to_name, mt.member_no AS to_no FROM fund_transfers ft JOIN members mf ON mf.id=ft.from_member_id JOIN members mt ON mt.id=ft.to_member_id WHERE ft.id=?", [$id]),
            'expense' => $this->db->fetchOne("SELECT e.*, ec.name AS category_name FROM expenses e JOIN expense_categories ec ON ec.id=e.category_id WHERE e.id=?", [$id]),
            'share_transaction' => $this->db->fetchOne("SELECT st.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no FROM share_transactions st JOIN members m ON m.id=st.member_id WHERE st.id=?", [$id]),
            'social_fund_waiver' => $this->db->fetchOne("SELECT sfp.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no FROM social_fund_fee_payments sfp JOIN members m ON m.id=sfp.member_id WHERE sfp.id=?", [$id]),
            'disbursement', 'loan_application' => $this->db->fetchOne("SELECT l.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, lp.name AS product_name FROM loans l JOIN members m ON m.id=l.member_id JOIN loan_products lp ON lp.id=l.loan_product_id WHERE l.id=?", [$id]),
            default => null
        };
    }

    // ── HELPER: Notify all admins via multi-channel dispatch ─────
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('approvals.view', 'approvals.process') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}