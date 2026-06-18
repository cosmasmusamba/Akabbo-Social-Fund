<?php
// We should notify admins use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Enforce logAudit and (RBAC + IDOR). Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use Database;
use App\Services\NotificationService;

class TrashController extends BaseController
{
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->notif = new NotificationService();
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'trash.view', 'group' => 'groups.view_members', 'personal' => 'trash.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view trash.');
        
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $type   = $this->getQuery('type', '');
        $where  = ['t.restored_at IS NULL', 't.permanently_deleted_at IS NULL'];
        $params = [];
        
        if ($type) { 
            $where[] = 't.record_type = ?'; 
            $params[] = $type; 
        }
        
        // ENFORCE: Apply data scope to trash list
        // NOTE: This assumes your `trash` table has a `member_id` column for scoping.
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->buildScopeCondition($scope, 't.member_id');
        
        $ws = 'WHERE ' . implode(' AND ', $where) . " {$scopeCondition}";
        $offset = ($page - 1) * $limit;
        
        $total  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM trash t {$ws}", $params);
        $data   = $this->db->fetchAll("
            SELECT t.*, CONCAT(u.first_name,' ',u.last_name) AS deleted_by_name 
            FROM trash t 
            LEFT JOIN users u ON u.id = t.deleted_by 
            {$ws} 
            ORDER BY t.deleted_at DESC 
            LIMIT {$limit} OFFSET {$offset}
        ", $params);
        
        $result = [
            'data' => $data, 
            'total' => $total, 
            'page' => $page, 
            'per_page' => $limit, 
            'last_page' => (int)ceil($total / $limit),
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $limit, $total)
        ];

        // ENFORCE: Audit log for viewing trash
        $this->logAudit('trash_viewed', 'trash', null, null, "Viewed trash list (Page {$page})");

        $this->view('trash/index', array_merge(
            $this->prepareViewData('Trash', 'trash'),
            ['result' => $result, 'type' => $type]
        ));
    }

    public function restore(string $type, int $id): void
    {
        // ENFORCE: RBAC & Scope
        $perms = ['global' => 'trash.restore', 'group' => 'groups.view_members', 'personal' => 'trash.restore_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to restore records.');
        $this->verifyCsrf();
        
        $item = $this->db->fetchOne("SELECT * FROM trash WHERE record_type = ? AND record_id = ? AND restored_at IS NULL AND permanently_deleted_at IS NULL", [$type, $id]);
        if (!$item) {
            $this->jsonError('Record not found in trash or already restored.', null, 404);
        }

        $table = $this->resolveTable($type);
        if (!$table) {
            $this->jsonError('Unknown record type.', null, 400);
        }

        // ENFORCE: IDOR Guard - Fetch the actual soft-deleted record to verify ownership
        $record = $this->db->fetchOne("SELECT * FROM `{$table}` WHERE id = ?", [$id]);
        if (!$record) {
            $this->jsonError('Original record not found in database.', null, 404);
        }

        // ENFORCE: IDOR using canAccessMemberRecord (if record is linked to a member)
        if (!empty($record['member_id']) && !$this->canAccessMemberRecord((int)$record['member_id'])) {
            $this->jsonError('You do not have permission to restore this record.', null, 403);
        }

        // ENFORCE: Data Scope for non-member records (e.g., groups, expenses)
        $scope = $this->resolveDataScope($perms);
        if ($scope['type'] === 'none') {
            $this->jsonError('You do not have permission to restore this record.', null, 403);
        }
        if ($scope['type'] === 'group' && empty($record['member_id']) && !empty($record['group_id']) && (int)$record['group_id'] !== (int)$scope['group_id']) {
            $this->jsonError('You do not have permission to restore records outside your group.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            // Restore the record
            $this->db->execute("UPDATE `{$table}` SET deleted_at = NULL, updated_at = NOW() WHERE id = ?", [$id]);
            
            // Mark as restored in trash
            $this->db->execute("UPDATE trash SET restored_at = NOW(), restored_by = ? WHERE id = ?", [$_SESSION['user_id'], $item['id']]);
            
            $this->db->commit();
            
            // ENFORCE: Audit Log
            $this->logAudit('record_restored', 'trash', $id, ucfirst($type), "Restored {$type} ID {$id} from trash", json_encode($item), json_encode(['restored_by' => $_SESSION['user_id']]));
            
            // ENFORCE: Notify admins via multi-channel dispatch
            $this->notifyAdmins(
                'trash_record_restored', 
                'Record Restored from Trash', 
                "A {$type} record (ID: {$id}) was restored from the trash by an administrator."
            );
            
            $this->jsonSuccess(['redirect' => APP_URL . '/trash'], ucfirst($type) . ' restored successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins(
                'trash_restore_failed', 
                'Trash Restore Failed', 
                "Failed to restore {$type} record (ID: {$id}). Error: " . $e->getMessage()
            );
            
            $this->jsonError('Failed to restore record: ' . $e->getMessage(), null, 500);
        }
    }

    public function destroy(string $type, int $id): void
    {
        // ENFORCE: RBAC & Scope
        $perms = ['global' => 'trash.destroy', 'group' => 'groups.view_members', 'personal' => 'trash.destroy_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to permanently delete records.');
        $this->verifyCsrf();
        
        $item = $this->db->fetchOne("SELECT * FROM trash WHERE record_type = ? AND record_id = ? AND permanently_deleted_at IS NULL", [$type, $id]);
        if (!$item) {
            $this->jsonError('Record not found in trash or already permanently deleted.', null, 404);
        }

        $table = $this->resolveTable($type);
        if (!$table) {
            $this->jsonError('Unknown record type.', null, 400);
        }

        // ENFORCE: IDOR Guard - Fetch the actual soft-deleted record
        $record = $this->db->fetchOne("SELECT * FROM `{$table}` WHERE id = ?", [$id]);
        if (!$record) {
            $this->jsonError('Original record not found in database.', null, 404);
        }

        // ENFORCE: IDOR using canAccessMemberRecord
        if (!empty($record['member_id']) && !$this->canAccessMemberRecord((int)$record['member_id'])) {
            $this->jsonError('You do not have permission to permanently delete this record.', null, 403);
        }

        // ENFORCE: Data Scope for non-member records
        $scope = $this->resolveDataScope($perms);
        if ($scope['type'] === 'none') {
            $this->jsonError('You do not have permission to permanently delete this record.', null, 403);
        }
        if ($scope['type'] === 'group' && empty($record['member_id']) && !empty($record['group_id']) && (int)$record['group_id'] !== (int)$scope['group_id']) {
            $this->jsonError('You do not have permission to permanently delete records outside your group.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            // Permanently delete the record
            $this->db->execute("DELETE FROM `{$table}` WHERE id = ?", [$id]);
            
            // Mark as permanently deleted in trash (Track who performed the final deletion)
            $this->db->execute("UPDATE trash SET permanently_deleted_at = NOW(), deleted_by = ? WHERE id = ?", [$_SESSION['user_id'], $item['id']]);
            
            $this->db->commit();
            
            // ENFORCE: Audit Log
            $this->logAudit('record_permanently_deleted', 'trash', $id, ucfirst($type), "Permanently deleted {$type} ID {$id} from trash", json_encode($item), null);
            
            // ENFORCE: Notify admins via multi-channel dispatch
            $this->notifyAdmins(
                'trash_record_destroyed', 
                'Record Permanently Deleted', 
                "A {$type} record (ID: {$id}) was permanently deleted from the trash by an administrator."
            );
            
            $this->jsonSuccess(['redirect' => APP_URL . '/trash'], ucfirst($type) . ' permanently deleted.');
        } catch (\Exception $e) {
            $this->db->rollback();
            
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins(
                'trash_destroy_failed', 
                'Trash Permanent Deletion Failed', 
                "Failed to permanently delete {$type} record (ID: {$id}). Error: " . $e->getMessage()
            );
            
            $this->jsonError('Failed to permanently delete record: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

    /**
     * ENFORCE: Resolve table name from record type (DRY)
     */
    private function resolveTable(string $type): ?string
    {
        $tableMap = [
            // Core entities
            'member' => 'members', 'members' => 'members',
            'user' => 'users', 'users' => 'users',
            'loan' => 'loans', 'loans' => 'loans',
            'transaction' => 'transactions', 'transactions' => 'transactions',
            'savings' => 'savings_accounts', 'savings_account' => 'savings_accounts', 'savings_accounts' => 'savings_accounts',
            'group' => 'savings_groups', 'savings_group' => 'savings_groups', 'savings_groups' => 'savings_groups',
            'expense' => 'expenses', 'expenses' => 'expenses',
            'share_transaction' => 'share_transactions', 'share_transactions' => 'share_transactions',
            'fund_transfer' => 'fund_transfers', 'fund_transfers' => 'fund_transfers',
            'approval' => 'approvals', 'approvals' => 'approvals',
            'document' => 'documents', 'documents' => 'documents',
            'notification' => 'notifications', 'notifications' => 'notifications',
            'audit_log' => 'audit_logs', 'audit_logs' => 'audit_logs',
            'expense_category' => 'expense_categories', 'expense_categories' => 'expense_categories',
            'loan_product' => 'loan_products', 'loan_products' => 'loan_products',
            'loan_guarantor' => 'loan_guarantors', 'loan_guarantors' => 'loan_guarantors',
            'loan_repayment_schedule' => 'loan_repayment_schedules', 'loan_repayment_schedules' => 'loan_repayment_schedules',
            'member_share' => 'member_shares', 'member_shares' => 'member_shares',
            'permission' => 'permissions', 'permissions' => 'permissions',
            'role' => 'roles', 'roles' => 'roles',
            'setting' => 'settings', 'settings' => 'settings',
            'share_config' => 'share_config', // singular matches
            'social_fund_fee' => 'social_fund_fees', 'social_fund_fees' => 'social_fund_fees',
            'social_fund_fee_payment' => 'social_fund_fee_payments', 'social_fund_fee_payments' => 'social_fund_fee_payments',
            'user_permission' => 'user_permissions', 'user_permissions' => 'user_permissions',
        ];
        
        return $tableMap[$type] ?? null;
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
            WHERE p.slug IN ('trash.view', 'trash.restore', 'trash.destroy') AND u.status = 'active'
        ");
        
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}