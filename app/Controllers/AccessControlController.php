<?php
// We should notify both admins and member/user use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Enforce logAudit and (RBAC + IDOR). Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use Database;
use App\Services\NotificationService;

/**
 * AKABBO SOCIAL FUND - Access Control Controller
 * 
 * NOTE: Access Control is strictly a GLOBAL administrative function. 
 * There is no Group or Personal scope for managing system-wide permissions and roles.
 */
class AccessControlController extends BaseController
{
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        // ENFORCE: RBAC Guard (Strictly Global Scope)
        $this->auth->requireAuth();
        $perms = ['global' => 'permissions.manage'];
        $this->requireScopeAccess($perms, 'You do not have permission to manage access control.');
        
        $this->notif = new NotificationService();
    }

    // ── READ: Main Page ──────────────────────────────────────────
    public function index(): void
    {
        $users = $this->db->fetchAll("
            SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, r.name AS role_name, r.slug AS role_slug
            FROM users u JOIN roles r ON r.id = u.role_id WHERE u.status = 'active' ORDER BY u.first_name, u.last_name
        ");
        $permissions = $this->db->fetchAll("SELECT id, module, action, slug, scope, description FROM permissions ORDER BY module, action");
        $roles = $this->db->fetchAll("
            SELECT r.id, r.name, r.slug, r.description, r.is_system, COUNT(rp.permission_id) AS perm_count
            FROM roles r LEFT JOIN role_permissions rp ON rp.role_id = r.id GROUP BY r.id ORDER BY r.name
        ");

        // ENFORCE: Audit log for viewing sensitive access control data
        $this->logAudit('access_control_viewed', 'access_control', null, null, "Viewed access control dashboard");

        $this->view('access-control/index', array_merge(
            $this->prepareViewData('Access Control', 'access-control'),
            ['users' => $users, 'permissions' => $permissions, 'roles' => $roles]
        ));
    }

    // ── READ: User Permissions (AJAX) ────────────────────────────
    public function getUserPermissions(int $userId): void
    {
        // ENFORCE: IDOR Guard
        $user = $this->db->fetchOne("SELECT id FROM users WHERE id = ?", [$userId]);
        if (!$user) $this->jsonError('User not found.', null, 404);

        // FIX: Corrected the JOIN condition (was incorrectly joining rp.role_id = p.id)
        $rolePerms = $this->db->fetchAll("
            SELECT p.slug 
            FROM permissions p 
            JOIN role_permissions rp ON rp.permission_id = p.id 
            JOIN users u ON u.role_id = rp.role_id 
            WHERE u.id = ?
        ", [$userId]);
        
        $userPerms = $this->db->fetchAll("
            SELECT p.slug, up.is_allowed 
            FROM user_permissions up 
            JOIN permissions p ON p.id = up.permission_id 
            WHERE up.user_id = ?
        ", [$userId]);
        
        $this->jsonSuccess([
            'role_permissions' => array_column($rolePerms, 'slug'), 
            'user_permissions' => array_column($userPerms, 'is_allowed', 'slug')
        ]);
    }

    // ── READ: Role Permissions (AJAX) ────────────────────────────
    public function getRolePermissions(int $roleId): void
    {
        $role = $this->db->fetchOne("SELECT id FROM roles WHERE id = ?", [$roleId]);
        if (!$role) $this->jsonError('Role not found.', null, 404);

        $perms = $this->db->fetchAll("SELECT p.id FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?", [$roleId]);
        $this->jsonSuccess(['permissions' => array_column($perms, 'id')]);
    }

    // ── UPDATE: User Overrides (AJAX) ────────────────────────────
    public function update(): void
    {
        $this->verifyCsrf();
        $userId = (int)($_POST['user_id'] ?? 0);
        $permissions = json_decode($_POST['permissions'] ?? '[]', true) ?? [];

        $user = $this->db->fetchOne("SELECT first_name, email FROM users WHERE id = ?", [$userId]);
        if (!$user) $this->jsonError('User not found.', null, 404);

        $this->db->beginTransaction();
        try {
            $oldPerms = $this->db->fetchAll("SELECT p.slug, up.is_allowed FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?", [$userId]);
            
            $this->db->execute("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);
            foreach ($permissions as $slug => $action) {
                if ($action === 'allow' || $action === 'deny') {
                    $perm = $this->db->fetchOne("SELECT id FROM permissions WHERE slug = ?", [$slug]);
                    if ($perm) {
                        $this->db->execute("INSERT INTO user_permissions (user_id, permission_id, is_allowed) VALUES (?, ?, ?)", [$userId, $perm['id'], $action === 'allow' ? 1 : 0]);
                    }
                }
            }
            
            $this->logAudit('user_permissions_updated', 'access_control', $userId, 'User', "User overrides updated", json_encode($oldPerms), json_encode($permissions));
            
            // ENFORCE: Notify the affected user
            $this->notif->dispatch($userId, 'access_control_updated', 'Your Account Access Updated', 
                "Dear {$user['first_name']}, your specific access permissions have been updated by an administrator. Please log out and log back in for changes to take effect."
            );
            
            // ENFORCE: Notify all admins
            $this->notifyAdmins('admin_user_perms_changed', 'User Access Updated', "User overrides for {$user['first_name']} were updated by an administrator.");

            $this->db->commit();
            $this->jsonSuccess(null, 'User access updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('user_perms_update_failed', 'User Access Update Failed', "Failed to update user overrides for {$user['email']}: " . $e->getMessage());
            $this->jsonError('Failed to update: ' . $e->getMessage(), null, 500);
        }
    }

    // ── UPDATE: Role Permissions (AJAX) ──────────────────────────
    public function updateRolePermissions(): void
    {
        $this->verifyCsrf();
        $roleId = (int)($_POST['role_id'] ?? 0);
        $permIds = json_decode($_POST['permission_ids'] ?? '[]', true) ?? [];

        $role = $this->db->fetchOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
        if (!$role) $this->jsonError('Role not found.', null, 404);

        $this->db->beginTransaction();
        try {
            $oldPerms = $this->db->fetchColumn("SELECT GROUP_CONCAT(permission_id) FROM role_permissions WHERE role_id = ?", [$roleId]);
            
            $this->db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            foreach ($permIds as $permId) {
                $this->db->execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleId, (int)$permId]);
            }
            
            $this->logAudit('role_permissions_updated', 'access_control', $roleId, 'Role', "Role permissions updated", $oldPerms, json_encode($permIds));
            
            // ENFORCE: Notify all users assigned to this role
            $users = $this->db->fetchAll("SELECT id, first_name FROM users WHERE role_id = ? AND status = 'active'", [$roleId]);
            foreach ($users as $u) {
                $this->notif->dispatch((int)$u['id'], 'role_permissions_updated', 'Your Role Permissions Updated', 
                    "Dear {$u['first_name']}, the permissions for your role ({$role['name']}) have been updated. Please log out and log back in."
                );
            }
            
            // ENFORCE: Notify all admins
            $this->notifyAdmins('admin_role_perms_changed', 'Role Permissions Updated', "Permissions for role '{$role['name']}' were updated by an administrator.");

            $this->db->commit();
            $this->jsonSuccess(null, 'Role permissions updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('role_perms_update_failed', 'Role Permissions Update Failed', "Failed to update permissions for role '{$role['name']}': " . $e->getMessage());
            $this->jsonError('Failed to update: ' . $e->getMessage(), null, 500);
        }
    }

    // ── CREATE/UPDATE: Permission (AJAX) ────────────────────────
    public function savePermission(): void
    {
        $this->verifyCsrf();
        $id = (int)($_POST['permission_id'] ?? 0);
        $module = trim($_POST['module'] ?? '');
        $action = trim($_POST['action'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scope = $_POST['scope'] ?? 'global';

        if (!$module || !$action || !$slug) $this->jsonError('Module, Action, and Slug are required.', null, 422);

        // ENFORCE: Wrapped in transaction for data integrity
        $this->db->beginTransaction();
        try {
            $oldData = $id > 0 ? $this->db->fetchOne("SELECT * FROM permissions WHERE id = ?", [$id]) : null;
            if ($id > 0 && !$oldData) {
                $this->db->rollback();
                $this->jsonError('Permission not found.', null, 404);
            }
            
            if ($id > 0) {
                $this->db->execute("UPDATE permissions SET module=?, action=?, slug=?, description=?, scope=? WHERE id=?", [$module, $action, $slug, $description, $scope, $id]);
                $this->logAudit('permission_updated', 'access_control', $id, 'Permission', "Permission {$slug} updated", json_encode($oldData), json_encode($_POST));
            } else {
                $this->db->execute("INSERT INTO permissions (module, action, slug, description, scope) VALUES (?, ?, ?, ?, ?)", [$module, $action, $slug, $description, $scope]);
                $newId = (int)$this->db->fetchColumn("SELECT LAST_INSERT_ID()");
                $this->logAudit('permission_created', 'access_control', $newId, 'Permission', "Permission {$slug} created", null, json_encode($_POST));
            }
            
            $this->notifyAdmins('admin_permission_changed', 'System Permission Changed', "A system permission ({$slug}) was created/updated by an administrator.");
            
            $this->db->commit();
            $this->jsonSuccess(null, $id > 0 ? 'Permission updated successfully.' : 'Permission created successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('permission_save_failed', 'Permission Save Failed', "Failed to save permission ({$slug}): " . $e->getMessage());
            $this->jsonError('Failed to save: ' . $e->getMessage(), null, 500);
        }
    }

    // ── DELETE: Permission (AJAX) ────────────────────────────────
    public function deletePermission(): void
    {
        $this->verifyCsrf();
        $id = (int)($_POST['permission_id'] ?? 0);

        // ENFORCE: Wrapped in transaction
        $this->db->beginTransaction();
        try {
            $perm = $this->db->fetchOne("SELECT slug FROM permissions WHERE id = ?", [$id]);
            if (!$perm) {
                $this->db->rollback();
                $this->jsonError('Permission not found.', null, 404);
            }

            $assigned = $this->db->fetchColumn("SELECT COUNT(*) FROM role_permissions WHERE permission_id = ?", [$id]);
            if ($assigned > 0) {
                $this->db->rollback();
                $this->jsonError('Cannot delete. This permission is assigned to one or more roles.', null, 409);
            }
            
            $this->db->execute("DELETE FROM permissions WHERE id = ?", [$id]);
            
            $this->logAudit('permission_deleted', 'access_control', $id, 'Permission', "Permission {$perm['slug']} deleted", json_encode($perm), null);
            $this->notifyAdmins('admin_permission_deleted', 'System Permission Deleted', "A system permission ({$perm['slug']}) was deleted by an administrator.");

            $this->db->commit();
            $this->jsonSuccess(null, 'Permission deleted successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('permission_delete_failed', 'Permission Delete Failed', "Failed to delete permission: " . $e->getMessage());
            $this->jsonError('Failed to delete: ' . $e->getMessage(), null, 500);
        }
    }

    // ── CREATE/UPDATE: Role (AJAX) ───────────────────────────────
    public function saveRole(): void
    {
        $this->verifyCsrf();
        $id = (int)($_POST['role_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $permissionIds = json_decode($_POST['permission_ids'] ?? '[]', true) ?? [];

        if (!$name || !$slug) $this->jsonError('Name and Slug are required.', null, 422);

        $this->db->beginTransaction();
        try {
            if ($id > 0) {
                $role = $this->db->fetchOne("SELECT is_system, name FROM roles WHERE id = ?", [$id]);
                if (!$role || $role['is_system']) {
                    $this->db->rollback();
                    $this->jsonError('System roles cannot be modified.', null, 403);
                }

                $oldData = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
                $this->db->execute("UPDATE roles SET name=?, slug=?, description=? WHERE id=?", [$name, $slug, $description, $id]);
                
                $this->db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
                foreach ($permissionIds as $permId) {
                    $this->db->execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$id, (int)$permId]);
                }
                
                $this->logAudit('role_updated', 'access_control', $id, 'Role', "Role {$name} updated", json_encode($oldData), json_encode($_POST));
                
                // ENFORCE: Notify users in this role
                $users = $this->db->fetchAll("SELECT id, first_name FROM users WHERE role_id = ? AND status = 'active'", [$id]);
                foreach ($users as $u) {
                    $this->notif->dispatch((int)$u['id'], 'role_updated', 'Your Role Updated', "Dear {$u['first_name']}, your role details have been updated.");
                }
            } else {
                $this->db->execute("INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)", [$name, $slug, $description]);
                $newRoleId = (int)$this->db->fetchColumn("SELECT LAST_INSERT_ID()");
                
                foreach ($permissionIds as $permId) {
                    $this->db->execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$newRoleId, (int)$permId]);
                }
                $this->logAudit('role_created', 'access_control', $newRoleId, 'Role', "Role {$name} created", null, json_encode($_POST));
            }
            
            $this->notifyAdmins('admin_role_changed', 'System Role Changed', "A system role ({$name}) was created/updated by an administrator.");
            
            $this->db->commit();
            $this->jsonSuccess(null, $id > 0 ? 'Role updated successfully.' : 'Role created successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('role_save_failed', 'Role Save Failed', "Failed to save role ({$name}): " . $e->getMessage());
            $this->jsonError('Failed to save: ' . $e->getMessage(), null, 500);
        }
    }

    // ── DELETE: Role (AJAX) ──────────────────────────────────────
    public function deleteRole(): void
    {
        $this->verifyCsrf();
        $id = (int)($_POST['role_id'] ?? 0);

        // ENFORCE: Wrapped in transaction
        $this->db->beginTransaction();
        try {
            $role = $this->db->fetchOne("SELECT is_system, name FROM roles WHERE id = ?", [$id]);
            if (!$role) {
                $this->db->rollback();
                $this->jsonError('Role not found.', null, 404);
            }
            if ($role['is_system']) {
                $this->db->rollback();
                $this->jsonError('System roles cannot be deleted.', null, 403);
            }
            
            $users = $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ?", [$id]);
            if ($users > 0) {
                $this->db->rollback();
                $this->jsonError("Cannot delete. {$users} user(s) are currently assigned to this role.", null, 409);
            }

            $this->db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
            $this->db->execute("DELETE FROM roles WHERE id = ?", [$id]);
            
            $this->logAudit('role_deleted', 'access_control', $id, 'Role', "Role {$role['name']} deleted", json_encode($role), null);
            $this->notifyAdmins('admin_role_deleted', 'System Role Deleted', "A system role ({$role['name']}) was deleted by an administrator.");

            $this->db->commit();
            $this->jsonSuccess(null, 'Role deleted successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('role_delete_failed', 'Role Delete Failed', "Failed to delete role: " . $e->getMessage());
            $this->jsonError('Failed to delete: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPER: Notify all admins via multi-channel dispatch ─────
    private function notifyAdmins(string $type, string $title, string $message): void 
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug = 'permissions.manage' AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}