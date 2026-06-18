<?php
// We should notify both approver and applicant use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Ensure to use ApprovalController for DRY and consistence. Enforce logAudit and (RBAC + IDOR). Enforce audit COMPLIANCE_AUDIT.md 3. Fund Transfers. Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None). Prevent overdraft if so, processes it a loan with users concent to terms with other loan mechanisms applicable.
namespace App\Controllers;

use App\Helpers\Format;
use App\Models\Approval;
use App\Services\NotificationService;
use Database;

class FundTransferController extends BaseController
{
    private Approval $approval;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->approval = new Approval();
        $this->notif = new NotificationService();
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'transfers.view', 'group' => 'groups.view_members', 'personal' => 'transfers.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view transfers.');
        $scope = $this->resolveDataScope($perms);

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'status' => $this->getQuery('status', ''),
            'search' => $this->getQuery('search', ''),
        ];

        // ENFORCE: Pass scope to model to filter data at the database level
        $result = $this->modelTransferList($page, $limit, $filters, $scope);
        
        // ENFORCE: Apply scope to stats as well
        $statsWhere = $this->getTransferScopeCondition($scope);
        $stats  = $this->db->fetchOne("
            SELECT 
                SUM(status='pending') AS pending,
                SUM(status='completed') AS completed,
                SUM(status='rejected') AS rejected,
                COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) AS total_transferred
            FROM fund_transfers ft
            {$statsWhere}
        ");

        // ENFORCE: Audit log for viewing
        $this->logAudit('transfers_viewed', 'fund_transfers', null, null, "Viewed transfers list");

        $this->view('transfers/index', array_merge(
            $this->prepareViewData('Fund Transfers', 'transfers'),
            ['result' => $result, 'stats' => $stats, 'filters' => $filters]
        ));
    }

    public function create(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'transfers.create', 'group' => 'groups.view_members', 'personal' => 'transfers.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to create transfers.');
        $scope = $this->resolveDataScope($perms);
        
        // ENFORCE: Scope the member dropdown
        $scopeCondition = $this->buildScopeCondition($scope, 'id');
        $members = $this->db->fetchAll("SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone FROM members WHERE status='active' AND deleted_at IS NULL {$scopeCondition} ORDER BY first_name LIMIT 100");
        
        $this->view('transfers/create', array_merge(
            $this->prepareViewData('New Fund Transfer', 'transfers', ['Transfers' => APP_URL.'/transfers', 'New' => null]),
            ['members' => $members]
        ));
    }

    public function store(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'transfers.create', 'group' => 'groups.view_members', 'personal' => 'transfers.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to create transfers.');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['from_member_id', 'to_member_id', 'amount', 'description']);
        if ($missing) {
            $this->jsonError('Required fields missing.', array_fill_keys($missing, 'Required'), 422);
        }

        $fromId = (int)$data['from_member_id'];
        $toId   = (int)$data['to_member_id'];
        $amount = (float)$data['amount'];

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($fromId)) {
            $this->jsonError('You do not have permission to transfer funds for this member.', null, 403);
        }

        // COMPLIANCE (Sec 3): No self-transfers
        if ($fromId === $toId) {
            $this->jsonError('Cannot transfer to the same member.', null, 422);
        }
        if ($amount <= 0) {
            $this->jsonError('Amount must be greater than zero.', null, 422);
        }
        // COMPLIANCE (Sec 3): Mandatory transfer description (min 10 chars)
        if (strlen(trim($data['description'] ?? '')) < 10) {
            $this->jsonError('Transfer description must be at least 10 characters.', null, 422);
        }

        $fromAccount = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [$fromId]);
        $toAccount   = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [$toId]);
        
        if (!$fromAccount) $this->jsonError('Source member has no active savings account.', null, 404);
        if (!$toAccount) $this->jsonError('Destination member has no active savings account.', null, 404);

        // COMPLIANCE (Sec 3 & Sec 13): Loan collateral protection & Pending Commitment Protection
        $activeLoanBalance = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')", [$fromId]
        );
        $settings = $this->getSettings();
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
        $minRequired = $activeLoanBalance / $multiplier;
        
        $pendingTransfers = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM fund_transfers WHERE from_member_id = ? AND status = 'pending'", [$fromId]);
        $pendingWithdrawals = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id = ? AND status = 'pending' AND txn_type = 'withdrawal'", [$fromId]);
        
        $minSavings = (float)($settings['min_savings'] ?? 10000);
        $available = max(0, (float)$fromAccount['balance'] - $minRequired - $minSavings - $pendingTransfers - $pendingWithdrawals);

        if ($amount > $available) {
            $this->jsonError("Insufficient available balance (accounting for pending requests and collateral). Maximum transferable: " . Format::currency($available), null, 422);
        }

        $fromMember = $this->db->fetchOne("SELECT user_id, first_name, last_name, member_no FROM members WHERE id = ?", [$fromId]);
        $toMember = $this->db->fetchOne("SELECT user_id, first_name, last_name, member_no FROM members WHERE id = ?", [$toId]);

        $this->db->beginTransaction();
        try {
            $ref = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            
            $transferId = (int)$this->db->insert("
                INSERT INTO fund_transfers (transfer_ref, from_member_id, to_member_id, amount, description, transfer_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, CURDATE(), 'pending', ?)
            ", [$ref, $fromId, $toId, $amount, $data['description'], $_SESSION['user_id']]);

            // DRY: Route through centralized Approval engine
            $this->approval->request('transfer', $transferId, $ref, $amount, $_SESSION['user_id'],
                "Transfer of " . Format::currency($amount) . " from member #{$fromId} to member #{$toId}. " . $data['description']);

            // ENFORCE: Notify Applicant (Sender) via multi-channel dispatch
            if (!empty($fromMember['user_id'])) {
                $this->notif->dispatch(
                    (int)$fromMember['user_id'],
                    'transfer_approval_request',
                    'Transfer Request Submitted',
                    "Dear {$fromMember['first_name']}, your transfer request of " . Format::currency($amount) . " to {$toMember['first_name']} has been submitted and is pending approval."
                );
            }

            // ENFORCE: Notify Approvers via multi-channel dispatch
            $this->notifyApproversViaDispatch('transfer', $ref, $amount, $data['description']);

            // ENFORCE: Audit Log
            $this->logAudit('transfer_requested', 'fund_transfers', $transferId, 'FundTransfer', 
                "Transfer {$ref}: " . Format::currency($amount) . " from M#{$fromId} to M#{$toId}", 
                null, json_encode(['from_member' => $fromId, 'to_member' => $toId, 'amount' => $amount, 'status' => 'pending']));

            // ENFORCE: Notify admins of success
            $this->notifyAdmins('transfer_requested', 'New Transfer Request', "A new transfer request {$ref} for " . Format::currency($amount) . " was submitted by {$fromMember['first_name']} {$fromMember['last_name']}.");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/transfers'], 'Transfer request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('transfer_creation_failed', 'Transfer Creation Failed', "Failed to create transfer for member {$fromMember['member_no']}: " . $e->getMessage());
            $this->jsonError('Failed to submit transfer request: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'transfers.view', 'group' => 'groups.view_members', 'personal' => 'transfers.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view transfers.');
        
        $transfer = $this->db->fetchOne("
            SELECT ft.*,
                   CONCAT(mf.first_name,' ',mf.last_name) AS from_name, mf.member_no AS from_no, mf.phone AS from_phone, mf.id AS from_member_id,
                   CONCAT(mt.first_name,' ',mt.last_name) AS to_name, mt.member_no AS to_no, mt.phone AS to_phone, mt.id AS to_member_id,
                   CONCAT(u.first_name,' ',u.last_name) AS created_by_name
            FROM fund_transfers ft
            JOIN members mf ON mf.id = ft.from_member_id
            JOIN members mt ON mt.id = ft.to_member_id
            JOIN users u ON u.id = ft.created_by
            WHERE ft.id = ?", [$id]
        );
        if (!$transfer) { 
            $this->flash('error', 'Transfer not found.'); 
            $this->redirect('/transfers'); 
        }

        // ENFORCE: IDOR Guard (User must have access to either the sender or the receiver)
        $canViewSender = $this->canAccessMemberRecord((int)$transfer['from_member_id']);
        $canViewReceiver = $this->canAccessMemberRecord((int)$transfer['to_member_id']);

        if (!$canViewSender && !$canViewReceiver) {
            $this->flash('error', 'You do not have permission to view this transfer.');
            $this->redirect('/dashboard');
        }

        // ENFORCE: Audit log for viewing sensitive financial data
        $this->logAudit('transfer_viewed', 'fund_transfers', $id, 'FundTransfer', "Viewed transfer {$transfer['transfer_ref']}");

        $approval = $this->approval->getForRecord('transfer', $id);
        $this->view('transfers/show', array_merge(
            $this->prepareViewData('Transfer ' . ($transfer['transfer_ref'] ?? ''), 'transfers', ['Transfers' => APP_URL.'/transfers', 'Details' => null]),
            ['transfer' => $transfer, 'approval' => $approval]
        ));
    }

    public function requestReversal(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'transfers.reverse', 'group' => 'groups.view_members', 'personal' => 'transfers.reverse_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to reverse transfers.');
        $this->verifyCsrf();
        
        $transfer = $this->db->fetchOne("
            SELECT ft.*, m.user_id, m.first_name, m.member_no 
            FROM fund_transfers ft 
            JOIN members m ON m.id = ft.from_member_id 
            WHERE ft.id=? AND ft.status='completed'", [$id]
        );
        if (!$transfer) {
            $this->jsonError('Only completed transfers can be reversed.', null, 400);
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord((int)$transfer['from_member_id'])) {
            $this->jsonError('You do not have permission to reverse this transfer.', null, 403);
        }
        
        $notes = trim($this->getPost()['reversal_notes'] ?? '');
        if (strlen($notes) < 10) {
            $this->jsonError('Reversal notes must be at least 10 characters.', null, 422);
        }

        $this->db->beginTransaction();
        try {
            $ref = 'REV-TRF-' . strtoupper(uniqid());
            
            // DRY: Route through the centralized Approval engine
            $this->approval->request('transfer_reversal', $id, $ref, (float)$transfer['amount'], $_SESSION['user_id'], $notes);
            
            // ENFORCE: Notify Applicant (Sender) via multi-channel dispatch
            if (!empty($transfer['user_id'])) {
                $this->notif->dispatch(
                    (int)$transfer['user_id'],
                    'transfer_approval_request', 
                    'Transfer Reversal Requested',
                    "Dear {$transfer['first_name']}, your request to reverse transfer {$transfer['transfer_ref']} has been submitted for approval."
                );
            }

            // ENFORCE: Notify Approvers via multi-channel dispatch
            $this->notifyApproversViaDispatch('transfer_reversal', $ref, (float)$transfer['amount'], $notes);

            // ENFORCE: Audit Log
            $this->logAudit('transfer_reversal_requested', 'fund_transfers', $id, 'FundTransfer',
                "Reversal requested for {$transfer['transfer_ref']}: {$notes}", 
                json_encode($transfer), 
                json_encode(['status' => 'reversal_pending'])
            );

            // ENFORCE: Notify admins of success
            $this->notifyAdmins('transfer_reversal_requested', 'Transfer Reversal Requested', "A reversal request {$ref} for transfer {$transfer['transfer_ref']} was submitted.");
                
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/transfers/' . $id], 'Transfer reversal request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('transfer_reversal_failed', 'Transfer Reversal Failed', "Failed to request reversal for {$transfer['transfer_ref']}: " . $e->getMessage());
            $this->jsonError('Failed to request reversal: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

    /**
     * ENFORCE: Generate Scope Condition for Transfers (Checks both sender and receiver)
     */
    private function getTransferScopeCondition(array $scope): string 
    {
        if ($scope['type'] === 'personal') {
            $mid = (int)$scope['member_id'];
            return "WHERE (ft.from_member_id = {$mid} OR ft.to_member_id = {$mid})";
        } elseif ($scope['type'] === 'group') {
            $gid = (int)$scope['group_id'];
            return "WHERE (ft.from_member_id IN (SELECT id FROM members WHERE group_id = {$gid} AND deleted_at IS NULL) OR ft.to_member_id IN (SELECT id FROM members WHERE group_id = {$gid} AND deleted_at IS NULL))";
        } elseif ($scope['type'] === 'none') {
            return "WHERE 1=0";
        }
        return ""; // Global
    }

    /**
     * ENFORCE: Dispatch to Approvers via multi-channel
     */
    private function notifyApproversViaDispatch(string $referenceType, string $referenceRef, float $amount, string $notes): void
    {
        $approvers = $this->db->fetchAll(
            "SELECT DISTINCT u.id FROM users u 
             JOIN role_permissions rp ON rp.role_id = u.role_id 
             JOIN permissions p ON p.id = rp.permission_id 
             WHERE p.slug = 'approvals.process' AND u.status = 'active'"
        );

        $label = ($referenceType === 'transfer_reversal') ? 'Transfer Reversal' : 'Fund Transfer';
        $title = "New {$label} Approval Request";
        $message = "A new {$label} request ({$referenceRef}) for " . Format::currency($amount) . " requires your approval. Notes: {$notes}";

        foreach ($approvers as $approver) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$approver['id'], 'transfer_approval_request', $title, $message);
        }
    }

    /**
     * ENFORCE: Notify all admins via multi-channel dispatch
     */
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('transfers.view', 'transfers.create', 'transfers.reverse') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }

    private function modelTransferList(int $page, int $limit, array $filters, array $scope): array
    {
        $where = []; 
        $params = [];
        
        // ENFORCE: Apply Data Scope
        if ($scope['type'] === 'personal') {
            $where[] = "(ft.from_member_id = ? OR ft.to_member_id = ?)";
            $params[] = $scope['member_id'];
            $params[] = $scope['member_id'];
        } elseif ($scope['type'] === 'group') {
            $where[] = "(ft.from_member_id IN (SELECT id FROM members WHERE group_id = ? AND deleted_at IS NULL) OR ft.to_member_id IN (SELECT id FROM members WHERE group_id = ? AND deleted_at IS NULL))";
            $params[] = $scope['group_id'];
            $params[] = $scope['group_id'];
        } elseif ($scope['type'] === 'none') {
            $where[] = "1=0";
        }

        if (!empty($filters['status'])) { 
            $where[] = 'ft.status = ?'; 
            $params[] = $filters['status']; 
        }
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = '(ft.transfer_ref LIKE ? OR CONCAT(mf.first_name, " ", mf.last_name) LIKE ? OR mf.member_no LIKE ? OR CONCAT(mt.first_name, " ", mt.last_name) LIKE ? OR mt.member_no LIKE ?)';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }
        
        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $limit;

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM fund_transfers ft JOIN members mf ON mf.id=ft.from_member_id {$ws}", $params);
        $data = $this->db->fetchAll("
            SELECT ft.*, 
                   CONCAT(mf.first_name,' ',mf.last_name) AS from_name, mf.member_no AS from_no,
                   CONCAT(mt.first_name,' ',mt.last_name) AS to_name, mt.member_no AS to_no
            FROM fund_transfers ft
            JOIN members mf ON mf.id = ft.from_member_id
            JOIN members mt ON mt.id = ft.to_member_id
            {$ws} ORDER BY ft.created_at DESC LIMIT {$limit} OFFSET {$offset}
        ", $params);

        return [
            'data' => $data, 
            'total' => $total, 
            'page' => $page,
            'per_page' => $limit, 
            'last_page' => (int)ceil($total / $limit),
            'from' => $total > 0 ? $offset + 1 : 0, 
            'to' => min($offset + $limit, $total)
        ];
    }
}