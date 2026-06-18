<?php
// We should notify both approver and applicant use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Ensure to use ApprovalController for DRY and consistence. Enforce logAudit and (RBAC + IDOR). Enforce audit COMPLIANCE_AUDIT.md 13. Pending Commitment Protection. Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None). Prevent overdraft if so, processes it a loan with users concent to terms with other loan mechanisms applicable.
namespace App\Controllers;

use App\Services\NotificationService;
use App\Models\Approval;
use App\Helpers\Format;

class TransactionController extends BaseController
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
        $perms = ['global' => 'transactions.view', 'group' => 'groups.view_members', 'personal' => 'transactions.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view transactions.');
        
        $search = $this->getQuery('search', ''); 
        $type   = $this->getQuery('type', ''); 
        $from   = $this->getQuery('from', date('Y-m-01')); 
        $to     = $this->getQuery('to', date('Y-m-d'));
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $where = ["t.transaction_date BETWEEN ? AND ?"]; 
        $params = [$from, $to];
        
        if ($search) {
            $like = "%{$search}%";
            $where[] = "(t.txn_ref LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ?)";
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($type) {
            $where[] = 't.txn_type = ?';
            $params[] = $type;
        }
        
        // ENFORCE: Resolve Scope & Build SQL Condition
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 't.member_id');
        
        $ws = 'WHERE ' . implode(' AND ', $where) . " {$scopeCondition}";
        $offset = ($page - 1) * $limit;
        
        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM transactions t LEFT JOIN members m ON m.id=t.member_id {$ws}", $params);
        $data = $this->db->fetchAll("SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no FROM transactions t LEFT JOIN members m ON m.id=t.member_id {$ws} ORDER BY t.created_at DESC LIMIT {$limit} OFFSET {$offset}", $params);
        
        $result = [
            'data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $limit, 
            'last_page' => (int)ceil($total/$limit), 'from' => $total > 0 ? $offset+1 : 0, 'to' => min($offset+$limit, $total)
        ];
        
        $typeTotals = $this->db->fetchAll("SELECT txn_type, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM transactions t LEFT JOIN members m ON m.id=t.member_id {$ws} GROUP BY txn_type", $params);

        $this->view('transactions/index', array_merge(
            $this->prepareViewData('Transactions', 'transactions'),
            ['result' => $result, 'typeTotals' => $typeTotals, 'filters' => compact('from','to','search','type')]
        ));
    }

    public function show(int $id): void
    {
        $this->auth->requirePermission('transactions.view');
        
        $txn = $this->db->fetchOne("SELECT t.*, CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no FROM transactions t LEFT JOIN members m ON m.id=t.member_id WHERE t.id=?", [$id]);
        if (!$txn) { 
            $this->flash('error','Transaction not found.'); 
            $this->redirect('/transactions'); 
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($txn['member_id'])) {
            $this->flash('error', 'You do not have permission to view this transaction.');
            $this->redirect('/dashboard');
        }
        
        // ENFORCE: Audit log for viewing sensitive financial data
        $this->logAudit('transaction_viewed', 'transactions', $id, 'Transaction', "Viewed transaction {$txn['txn_ref']}");

        $this->view('transactions/show', array_merge(
            $this->prepareViewData('Transaction ' . $txn['txn_ref'], 'transactions', ['Transactions' => APP_URL.'/transactions', $txn['txn_ref'] => null]),
            ['txn' => $txn]
        ));
    }

    public function approve(int $id): void
    {
        $this->auth->requirePermission('transactions.approve');
        $this->verifyCsrf();
        
        $txn = $this->db->fetchOne("SELECT t.*, m.first_name, m.user_id FROM transactions t LEFT JOIN members m ON m.id = t.member_id WHERE t.id=? AND t.status='pending'", [$id]);
        if (!$txn) $this->jsonError('Transaction not found or cannot be approved.', null, 400);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($txn['member_id'])) {
            $this->jsonError('You do not have permission to approve this transaction.', null, 403);
        }

        $refTypeMap = [
            'withdrawal'   => 'withdrawal',
            'transfer'     => 'transfer',
            'transfer_out' => 'transfer',
            'reversal'     => 'reversal',
        ];
        
        $refType = $refTypeMap[$txn['txn_type']] ?? null;
        if (!$refType) {
            $this->jsonError('This transaction type cannot be approved directly. Use the specific module workflow.', null, 400);
        }

        $approval = $this->db->fetchOne("SELECT id FROM approvals WHERE reference_type = ? AND reference_id = ? AND status = 'pending'", [$refType, $id]);
        if (!$approval) {
            $this->jsonError('No pending approval found for this transaction. It may have already been processed.', null, 404);
        }

        $this->db->beginTransaction();
        try {
            // DRY: Use the Approval model to handle core logic and execution
            // Note: Sec 13 (Pending Commitment Protection) is inherently enforced because 
            // the available balance was locked when the transaction was initially requested.
            $this->approval->approve($approval['id'], $_SESSION['user_id'], $_POST['rejection_notes'] ?? '');

            // ENFORCE: Notify Applicant (Member) via multi-channel dispatch
            if (!empty($txn['user_id'])) {
                $this->notif->dispatch((int)$txn['user_id'], 'transaction_approved', 'Transaction Approved',
                    "Dear {$txn['first_name']}, your transaction {$txn['txn_ref']} for " . Format::currency((float)$txn['amount']) . " has been successfully approved and processed.");
            }

            // ENFORCE: Notify the approver (current user)
            $this->notif->dispatch((int)$_SESSION['user_id'], 'transaction_approved_by_you', 'Transaction Approved',
                "You have successfully approved transaction {$txn['txn_ref']} for " . Format::currency((float)$txn['amount']) . ".");

            $this->logAudit('transaction_approved', 'transactions', $id, 'Transaction', "Approved transaction {$txn['txn_ref']}");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/transactions'], 'Transaction approved successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('transaction_approval_failed', 'Transaction Approval Failed', "Failed to approve transaction ID {$id}: " . $e->getMessage());
            $this->jsonError('Failed to approve transaction: ' . $e->getMessage(), null, 500);
        }
    }

    public function reject(int $id): void
    {
        $this->auth->requirePermission('transactions.approve');
        $this->verifyCsrf();
        
        $txn = $this->db->fetchOne("SELECT t.*, m.first_name, m.user_id FROM transactions t LEFT JOIN members m ON m.id = t.member_id WHERE t.id=? AND t.status='pending'", [$id]);
        if (!$txn) $this->jsonError('Transaction not found or cannot be rejected.', null, 400);

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($txn['member_id'])) {
            $this->jsonError('You do not have permission to reject this transaction.', null, 403);
        }

        $refTypeMap = [
            'withdrawal'   => 'withdrawal',
            'transfer'     => 'transfer',
            'transfer_out' => 'transfer',
            'reversal'     => 'reversal',
        ];
        
        $refType = $refTypeMap[$txn['txn_type']] ?? null;
        if (!$refType) {
            $this->jsonError('This transaction type cannot be rejected directly.', null, 400);
        }

        $approval = $this->db->fetchOne("SELECT id FROM approvals WHERE reference_type = ? AND reference_id = ? AND status = 'pending'", [$refType, $id]);
        if (!$approval) {
            $this->jsonError('No pending approval found for this transaction.', null, 404);
        }

        $this->db->beginTransaction();
        try {
            $rejectionNotes = $_POST['rejection_notes'] ?? ($_POST['rejection_reason'] ?? 'No reason provided.');
            
            // DRY: Use the Approval model to handle core logic and release locked funds
            $this->approval->reject($approval['id'], $_SESSION['user_id'], $rejectionNotes);

            // ENFORCE: Notify Applicant (Member) via multi-channel dispatch
            if (!empty($txn['user_id'])) {
                $this->notif->dispatch((int)$txn['user_id'], 'transaction_rejected', 'Transaction Rejected',
                    "Dear {$txn['first_name']}, your transaction {$txn['txn_ref']} has been rejected. Reason: {$rejectionNotes}");
            }

            // ENFORCE: Notify the approver (current user)
            $this->notif->dispatch((int)$_SESSION['user_id'], 'transaction_rejected_by_you', 'Transaction Rejected',
                "You have rejected transaction {$txn['txn_ref']}.");

            $this->logAudit('transaction_rejected', 'transactions', $id, 'Transaction', "Rejected transaction {$txn['txn_ref']}. Reason: {$rejectionNotes}");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/transactions'], 'Transaction rejected successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('transaction_rejection_failed', 'Transaction Rejection Failed', "Failed to reject transaction ID {$id}: " . $e->getMessage());
            $this->jsonError('Failed to reject transaction: ' . $e->getMessage(), null, 500);
        }
    }

    public function reverse(int $id): void
    {
        $this->auth->requirePermission('transactions.reverse');
        $this->verifyCsrf();
        
        $notes = trim($this->getPost()['reversal_notes'] ?? '');
        if (empty($notes) || strlen($notes) < 10) {
            $this->jsonError('Reversal notes are mandatory and must be at least 10 characters long.', null, 422);
        }

        $txn = $this->db->fetchOne("SELECT t.*, m.first_name, m.user_id FROM transactions t LEFT JOIN members m ON m.id = t.member_id WHERE t.id = ? AND t.status IN ('completed', 'approved')", [$id]);
        if (!$txn) {
            $this->jsonError('Transaction not found or cannot be reversed.', null, 400);
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($txn['member_id'])) {
            $this->jsonError('You do not have permission to reverse this transaction.', null, 403);
        }

        $existingApproval = $this->db->fetchOne("SELECT id FROM approvals WHERE reference_type = 'reversal' AND reference_id = ? AND status = 'pending'", [$id]);
        if ($existingApproval) {
            $this->jsonError('A reversal request for this transaction is already pending.', null, 409);
        }

        $ref = 'REV-REQ-' . strtoupper(uniqid());
        
        $this->db->beginTransaction();
        try {
            // DRY: Route through centralized approval engine
            $this->approval->request('reversal', $id, $ref, (float)$txn['amount'], $_SESSION['user_id'], $notes);
            
            // ENFORCE: Notify Approvers (Multi-channel dispatch)
            $admins = $this->db->fetchAll("
                SELECT DISTINCT u.id FROM users u 
                JOIN role_permissions rp ON rp.role_id = u.role_id 
                JOIN permissions p ON p.id = rp.permission_id 
                WHERE p.slug = 'transactions.reverse' AND u.status = 'active'
            ");
            foreach ($admins as $admin) {
                $this->notif->dispatch((int)$admin['id'], 'transaction_reversal_requested', 'Transaction Reversal Requested', 
                    "A reversal request for transaction {$txn['txn_ref']} requires your approval. Notes: {$notes}");
            }
            
            // ENFORCE: Notify Member (Applicant)
            if (!empty($txn['user_id'])) {
                $this->notif->dispatch((int)$txn['user_id'], 'transaction_reversal_requested', 'Transaction Reversal Requested', 
                    "Dear {$txn['first_name']}, a reversal request for transaction {$txn['txn_ref']} has been submitted and is pending approval.");
            }

            $this->logAudit('transaction_reversal_requested', 'transactions', $id, 'Transaction', "Reversal requested for {$txn['txn_ref']}: {$notes}");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/transactions'], 'Reversal request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('transaction_reversal_failed', 'Transaction Reversal Failed', "Failed to submit reversal for transaction ID {$id}: " . $e->getMessage());
            $this->jsonError('Failed to submit reversal request: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPER: Notify all admins via multi-channel dispatch ────────
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('transactions.approve', 'transactions.reverse', 'transactions.view') AND u.status = 'active'
        ");
        
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}