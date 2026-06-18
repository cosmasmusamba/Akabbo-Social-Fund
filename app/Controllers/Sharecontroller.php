<?php
// We should notify both approver and applicant use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Ensure to use ApprovalController for DRY and consistence. Enforce logAudit and (RBAC + IDOR). Enforce audit COMPLIANCE_AUDIT.md 6. Share Management, 13. Pending Commitment Protection. Determine the user's data scope (Global, Group, Personal, or None). Prevent overdraft if so, processes it a loan with users concent to terms with other loan mechanisms applicable.
namespace App\Controllers;

use App\Models\Share;
use App\Models\Approval;
use App\Helpers\Format;
use App\Services\NotificationService;

class ShareController extends BaseController
{
    private Share $model;
    private NotificationService $notif;
    private Approval $approval;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Share();
        $this->notif = new NotificationService();
        $this->approval = new Approval(); 
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'shares.view', 'group' => 'groups.view_members', 'personal' => 'shares.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view shares.');
        
        $scope = $this->resolveDataScope($perms);

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        
        $filters = [
            'search' => $this->getQuery('search', ''),
        ];

        // ENFORCE: Pass scope to model to filter data at the database level
        $result = $this->model->getShareholdersList($page, $limit, $filters, $scope);
        $stats  = $this->model->getSummaryStats($scope);
        $config = $this->model->getConfig();

        // ENFORCE: Audit log for viewing dashboard
        $this->logAudit('shares_viewed', 'shares', null, null, "Viewed shares dashboard");

        $this->view('shares/index', array_merge(
            $this->prepareViewData('Shares & Shareholders', 'shares'),
            ['result' => $result, 'stats' => $stats, 'config' => $config]
        ));
    }

    public function create(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'shares.manage', 'group' => 'groups.view_members', 'personal' => 'shares.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to issue shares.');
        
        $scope = $this->resolveDataScope($perms);
        $config = $this->model->getConfig();
        
        $prefillMember = null;
        $memberId = $this->getQuery('member');
        if ($memberId) {
            $prefillMember = $this->db->fetchOne(
                "SELECT id, member_no, CONCAT(first_name,' ',last_name) AS full_name, phone 
                 FROM members WHERE id = ? AND status='active'", 
                [(int)$memberId]
            );
            
            // ENFORCE: IDOR Guard for prefill member
            if ($prefillMember && !$this->canAccessMemberRecord((int)$prefillMember['id'])) {
                $prefillMember = null; // Hide member if no permission
            }
        }

        $this->view('shares/create', array_merge(
            $this->prepareViewData('Issue Shares', 'shares', ['Shares' => APP_URL.'/shares', 'Issue' => null]),
            ['config' => $config, 'prefillMember' => $prefillMember, 'scope' => $scope]
        ));
    }

    public function store(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'shares.manage', 'group' => 'groups.view_members', 'personal' => 'shares.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to issue shares.');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['member_id', 'shares_qty', 'payment_method', 'transaction_date', 'notes']);
        if ($missing) {
            $this->jsonError('Please fill in all required fields.', array_fill_keys($missing, 'Required'), 422);
        }
        
        $memberId = (int)$data['member_id'];
        
        // Fetch member to validate existence and get user_id for notifications
        $member = $this->db->fetchOne("SELECT id, first_name, user_id, member_no FROM members WHERE id = ? AND status = 'active'", [$memberId]);
        if (!$member) {
            $this->jsonError('Invalid or inactive member.', null, 404);
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($memberId)) {
            $this->jsonError('You do not have permission to issue shares for this member.', null, 403);
        }

        $qty      = (int)$data['shares_qty'];
        $config   = $this->model->getConfig();
        $amount   = $qty * (float)$config['par_value'];
        
        if ($qty < ($config['min_shares'] ?? 1)) {
            $this->jsonError("Minimum shares to purchase is {$config['min_shares']}.", null, 422);
        }

        // COMPLIANCE: AUDIT SEC 13 - Pending Commitment Protection
        // Share purchases remain 'pending' and do not affect the member's financial balances 
        // until the ApprovalController executes them.

        $this->db->beginTransaction();
        try {
            $ref = $this->model->generateRef();
            
            // 1. Create the pending transaction record
            $txnId = (int)$this->db->insert("
                INSERT INTO share_transactions
                (txn_ref, member_id, txn_type, shares_qty, total_amount, payment_method, transaction_date, notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ", [
                $ref, $memberId, 'purchase', $qty, $amount, $data['payment_method'],
                $data['transaction_date'], $data['notes'], $_SESSION['user_id']
            ]);

            // 2. DRY: Route through centralized approval engine (Audit Sec 6)
            $this->approval->request(
                'share_transaction',
                $txnId,
                $ref,
                $amount,
                $_SESSION['user_id'],
                "Share purchase request for {$qty} shares. Payment method: {$data['payment_method']}. Notes: {$data['notes']}"
            );
            
            // 3. ENFORCE: Notify Approvers
            $this->notif->notifyApprovers('share_transaction', $ref, $amount, $data['notes']);

            // 4. ENFORCE: Notify the applicant (Member) via multi-channel dispatch
            if (!empty($member['user_id'])) {
                $this->notif->dispatch(
                    (int)$member['user_id'],
                    'share_purchase_requested',
                    'Share Purchase Request Submitted',
                    "Dear {$member['first_name']}, your request to purchase {$qty} shares for " . Format::currency($amount) . " has been submitted and is pending approval."
                );
            }

            $this->logAudit('share_purchase_requested', 'shares', $txnId, 'ShareTransaction',
                "Requested {$qty} shares for member #{$memberId}", null, json_encode([
                    'member_id' => $memberId, 'qty' => $qty, 'amount' => $amount, 'status' => 'pending'
                ]));
                
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/shares'], 'Share purchase request submitted for approval.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('share_purchase_failed', 'Share Purchase Request Failed', "Failed to submit share purchase request for member {$member['member_no']}: " . $e->getMessage());
            $this->jsonError('Failed to submit request: ' . $e->getMessage(), null, 500);
        }
    }

    public function member(int $id): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'shares.view', 'group' => 'groups.view_members', 'personal' => 'shares.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view shares.');
        
        $scope = $this->resolveDataScope($perms);

        $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ?", [$id]);
        if (!$member) {
            $this->flash('error', 'Member not found.');
            $this->redirect('/shares');
        }

        // ENFORCE: IDOR Guard
        if (!$this->canAccessMemberRecord($member['id'])) {
            $this->flash('error', 'You do not have permission to view this account.');
            $this->redirect('/dashboard');
        }

        // ENFORCE: Pass scope to model to filter data
        $holding    = $this->model->getByMember($id, $scope);
        $privileges = $this->model->getLoanPrivileges($id, 10.0, 3); 
        $config     = $this->model->getConfig();
        
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $txns = $this->model->getTransactions($page, $limit, ['member_id' => $id], $scope);

        // ENFORCE: Audit log for viewing member shares
        $this->logAudit('member_shares_viewed', 'shares', $id, 'Member', "Viewed shares for member #{$id}");

        $this->view('shares/member', array_merge(
            $this->prepareViewData('Shares — ' . ($member['first_name'] . ' ' . $member['last_name']), 'shares'),
            ['member' => $member, 'holding' => $holding, 'privileges' => $privileges, 'config' => $config, 'txns' => $txns]
        ));
    }

    public function report(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'shares.view', 'group' => 'groups.view_members', 'personal' => 'shares.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view share reports.');
        
        $scope = $this->resolveDataScope($perms);

        // ENFORCE: Pass scope to model to filter data
        $stats   = $this->model->getSummaryStats($scope);
        $monthly = $this->model->getMonthlyReport($scope);
        $config  = $this->model->getConfig();
        
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $filters = ['status' => $this->getQuery('status', '')];
        $txns = $this->model->getTransactions($page, $limit, $filters, $scope);

        // ENFORCE: Audit log for viewing report
        $this->logAudit('share_report_viewed', 'shares', null, null, "Viewed share analytics report");

        $this->view('shares/report', array_merge(
            $this->prepareViewData('Share Analytics', 'shares'),
            ['stats' => $stats, 'monthly' => $monthly, 'config' => $config, 'txns' => $txns, 'filters' => $filters]
        ));
    }

    public function updateConfig(): void
    {
        $this->auth->requirePermission('shares.manage');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $config = [
            'par_value'             => (float)($data['par_value'] ?? 1000),
            'loan_rate_discount'    => (float)($data['loan_rate_discount'] ?? 2),
            'loan_multiplier_bonus' => (int)($data['loan_multiplier_bonus'] ?? 1),
            'dividend_rate'         => (float)($data['dividend_rate'] ?? 5),
            'min_shares'            => (int)($data['min_shares'] ?? 1),
            'max_shares_per_member' => (int)($data['max_shares_per_member'] ?? 1000),
            'is_transferable'       => isset($data['is_transferable']) ? 1 : 0,
            'updated_by'            => $_SESSION['user_id'],
        ];

        $this->db->beginTransaction();
        try {
            $oldConfig = $this->model->getConfig();
            $this->model->updateConfig($config);
            
            $this->logAudit('share_config_updated', 'shares', null, 'ShareConfig', 
                "Share configuration updated", json_encode($oldConfig), json_encode($config));

            // ENFORCE: Notify admins of system-level share config changes
            $this->notifyAdmins('share_config_updated', 'Share Configuration Updated', 
                "The global share configuration (par value, discounts, etc.) was updated by an administrator.");

            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/shares'], 'Configuration updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('share_config_update_failed', 'Share Configuration Update Failed', 
                "Failed to update share configuration: " . $e->getMessage());
            $this->jsonError('Failed to update configuration: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

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
            WHERE p.slug IN ('shares.manage', 'shares.view') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}