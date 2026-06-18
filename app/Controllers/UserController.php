<?php
// We should notify both admins and registered user (dispatch instead of sendToUser) user with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Lets have edit, disable, reactivate user as well. Enforce logAudit and (RBAC + IDOR). NIN and Passport numbers should be masked only showing last 5 characters if logged in user is not owner. Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None) Super admins can modify fellow super admin and self modify.
namespace App\Controllers;

use App\Helpers\Security;
use App\Services\NotificationService;
use Database;

class UserController extends BaseController
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
        $perms = ['global' => 'users.view', 'group' => 'groups.view_members', 'personal' => 'users.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view users.');
        
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();
        $search = $this->getQuery('search', '');
        
        $where  = ['1=1']; 
        $params = [];
        
        if ($search) { 
            $like = "%{$search}%"; 
            $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ?)"; 
            $params = [$like, $like]; 
        }
        
        // ENFORCE: Apply data scope to users list
        $scope = $this->resolveDataScope($perms);
        if ($scope['type'] === 'personal') {
            $where[] = "(u.member_id = ? OR m.id = ?)";
            $params[] = $scope['member_id'];
            $params[] = $scope['member_id'];
        } elseif ($scope['type'] === 'group') {
            $where[] = "(u.member_id IN (SELECT id FROM members WHERE group_id = ? AND deleted_at IS NULL) OR m.id IN (SELECT id FROM members WHERE group_id = ? AND deleted_at IS NULL))";
            $params[] = $scope['group_id'];
            $params[] = $scope['group_id'];
        } elseif ($scope['type'] === 'none') {
            $where[] = "1=0";
        }
        
        $ws = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        
        $total  = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM users u 
            LEFT JOIN members m ON (
                m.id = u.member_id 
                OR (u.nin != '' AND m.national_id = u.nin)
                OR (u.passport_number IS NOT NULL AND u.passport_number != '' AND m.passport_no = u.passport_number)
            ) {$ws}", $params);
        
        $data = $this->db->fetchAll("
            SELECT u.*, r.name AS role_name, 
                   COALESCE(m.avatar, u.avatar) AS display_avatar
            FROM users u 
            JOIN roles r ON r.id = u.role_id 
            LEFT JOIN members m ON (
                m.id = u.member_id 
                OR (u.nin != '' AND m.national_id = u.nin)
                OR (u.passport_number IS NOT NULL AND u.passport_number != '' AND m.passport_no = u.passport_number)
            )
            {$ws} 
            ORDER BY u.created_at DESC 
            LIMIT {$limit} OFFSET {$offset}
        ", $params);
        
        // ENFORCE: Mask sensitive fields for all users in the list
        $data = array_map([$this, 'maskSensitiveFields'], $data);
        
        $result = [
            'data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $limit, 
            'last_page' => (int)ceil($total/$limit), 'from' => $total > 0 ? $offset + 1 : 0, 'to' => min($offset + $limit, $total)
        ];
        
        $roles  = $this->db->fetchAll("SELECT * FROM roles ORDER BY id");
        
        // ENFORCE: Audit log for viewing the list
        $this->logAudit('users_viewed', 'users', null, null, "Viewed users list (Page {$page})");

        $this->view('users/index', array_merge(
            $this->prepareViewData('System Users', 'users'), 
            ['result' => $result, 'roles' => $roles]
        ));
    }

    public function create(): void
    {
        $this->auth->requirePermission('users.create');
        $roles = $this->db->fetchAll("SELECT * FROM roles ORDER BY id");
        $this->view('users/create', array_merge(
            $this->prepareViewData('Create User', 'users', ['Users' => APP_URL.'/users', 'Create' => null]),
            ['roles' => $roles]
        ));
    }

    public function store(): void
    {
        $this->auth->requirePermission('users.create');
        $this->verifyCsrf();
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['first_name', 'last_name', 'email', 'role_id']);
        if ($missing) $this->jsonError('Required fields missing.', array_fill_keys($missing, 'Required.'), 422);
        if ($this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE email=?", [$data['email']])) {
            $this->jsonError('Email already in use.', null, 409);
        }
        
        $tempPass = $_POST['password'] ?? Security::generateToken(8);
        $nin = trim($data['nin'] ?? '');
        $passport = trim($data['passport_number'] ?? '');
        
        $this->db->beginTransaction();
        try {
            $id = (int)$this->db->insert(
                "INSERT INTO users (first_name, last_name, email, phone, nin, passport_number, role_id, password_hash, status, must_change_password, created_by) 
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $data['first_name'], $data['last_name'], strtolower($data['email']), 
                    $data['phone'] ?? null, $nin ?: '', $passport ?: null, (int)$data['role_id'], 
                    Security::hashPassword($tempPass), 'active', 1, $_SESSION['user_id']
                ]
            );

            // Auto-linking logic (NIN -> Passport -> Email)
            $linkedMemberId = null;
            if ($nin) {
                $linkedMemberId = $this->db->fetchColumn("SELECT id FROM members WHERE national_id = ? AND deleted_at IS NULL AND user_id IS NULL LIMIT 1", [$nin]);
            }
            if (!$linkedMemberId && $passport) {
                $linkedMemberId = $this->db->fetchColumn("SELECT id FROM members WHERE passport_no = ? AND deleted_at IS NULL AND user_id IS NULL LIMIT 1", [$passport]);
            }
            if (!$linkedMemberId) {
                $linkedMemberId = $this->db->fetchColumn("SELECT id FROM members WHERE email = ? AND deleted_at IS NULL AND user_id IS NULL LIMIT 1", [strtolower($data['email'])]);
            }

            if ($linkedMemberId) {
                $this->db->execute("UPDATE users SET member_id = ? WHERE id = ?", [$linkedMemberId, $id]);
                $this->db->execute("UPDATE members SET user_id = ? WHERE id = ?", [$id, $linkedMemberId]);
            }

            // ENFORCE: Notify the new user
            $this->notif->dispatch($id, 'account_created', 'Welcome to Akabbo Social Fund',
                "Dear {$data['first_name']}, your system account has been created. Your temporary password is: {$tempPass}. Please log in and change your password immediately."
            );

            // ENFORCE: Notify admins of success
            $linkMsg = $linkedMemberId ? " and automatically linked to member #{$linkedMemberId}" : "";
            $this->notifyAdmins('user_created', 'New User Created', "A new user {$data['email']} was created{$linkMsg}.");

            $this->logAudit('user_created', 'users', $id, 'User', "User {$data['email']} created{$linkMsg}", null, json_encode(['email' => $data['email'], 'role_id' => $data['role_id'], 'linked_member_id' => $linkedMemberId]));
            
            $this->db->commit();
            
            $successMsg = 'User created successfully.';
            if ($linkedMemberId) $successMsg .= " Automatically linked to member record.";
            $successMsg .= " Temporary password: {$tempPass}";
            
            $this->jsonSuccess(['user_id' => $id, 'temp_password' => $tempPass, 'redirect' => APP_URL.'/users'], $successMsg);
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('user_creation_failed', 'User Creation Failed', "Failed to create user {$data['email']}: " . $e->getMessage());
            $this->jsonError('Failed to create user: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        $this->auth->requirePermission('users.view');
        
        $rawUser = $this->db->fetchOne("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?", [$id]);
        if (!$rawUser) { 
            $this->flash('error', 'User not found.'); 
            $this->redirect('/users'); 
        }

        $member = null;
        if (!empty($rawUser['member_id'])) {
            $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ? AND deleted_at IS NULL", [$rawUser['member_id']]);
        } else {
            if (!empty($rawUser['nin'])) $member = $this->db->fetchOne("SELECT * FROM members WHERE national_id = ? AND deleted_at IS NULL LIMIT 1", [$rawUser['nin']]);
            if (!$member && !empty($rawUser['passport_number'])) $member = $this->db->fetchOne("SELECT * FROM members WHERE passport_no = ? AND deleted_at IS NULL LIMIT 1", [$rawUser['passport_number']]);
        }

        // ENFORCE: IDOR Guard using canAccessMemberRecord
        if ($member && !$this->canAccessMemberRecord($member['id'])) {
            $member = null; // Hide member details if no permission
        }

        // ENFORCE: Mask sensitive fields before passing to view
        $user = $this->maskSensitiveFields($rawUser);

        $sessions = $this->db->fetchAll("SELECT * FROM login_sessions WHERE user_id=? ORDER BY login_at DESC LIMIT 10", [$id]);
        $roles = $this->db->fetchAll("SELECT * FROM roles ORDER BY name");
        
        // ENFORCE: Audit log for viewing sensitive user data
        $this->logAudit('user_viewed', 'users', $id, 'User', "Viewed user profile for {$user['email']}");

        $this->view('users/show', array_merge(
            $this->prepareViewData($user['first_name'].' '.$user['last_name'], 'users', ['Users' => APP_URL.'/users', $user['first_name'].' '.$user['last_name'] => null]),
            ['user' => $user, 'member' => $member, 'sessions' => $sessions, 'roles' => $roles]
        ));
    }

    public function edit(int $id): void
    {
        $this->auth->requirePermission('users.edit');
        $rawUser = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$rawUser) { 
            $this->flash('error', 'User not found.'); 
            $this->redirect('/users'); 
        }

        // ENFORCE: Super Admin Protection & Self-Edit Rules
        $targetRole = $this->db->fetchOne("SELECT slug FROM roles WHERE id = ?", [$rawUser['role_id']]);
        $isSuperAdminTarget = ($targetRole['slug'] ?? '') === 'super_admin';
        $isSelf = ($id === (int)$_SESSION['user_id']);

        if ($isSuperAdminTarget && !$this->auth->isSuperAdmin()) {
            $this->flash('error', 'You do not have permission to edit a Super Administrator.');
            $this->redirect('/users');
        }

        // Regular users cannot edit themselves via this page; they must use the Profile page.
        // Super admins CAN edit themselves.
        if ($isSelf && !$this->auth->isSuperAdmin()) {
            $this->flash('error', 'You cannot edit your own account via this page. Please use the Profile page.');
            $this->redirect('/profile');
        }

        $user = $this->maskSensitiveFields($rawUser);
        $roles = $this->db->fetchAll("SELECT * FROM roles ORDER BY name");
        
        $this->view('users/edit', array_merge(
            $this->prepareViewData('Edit: ' . $user['first_name'], 'users', ['Users' => APP_URL.'/users', 'Edit' => null]),
            ['user' => $user, 'roles' => $roles]
        ));
    }

    public function update(int $id): void
    {
        $this->auth->requirePermission('users.edit');
        $this->verifyCsrf();
        $data = $this->getPost();
        $oldData = $this->db->fetchOne("SELECT * FROM users WHERE id=?", [$id]);
        
        if (!$oldData) $this->jsonError('User not found.', null, 404);

        // ENFORCE: Super Admin Protection
        $targetRole = $this->db->fetchOne("SELECT slug FROM roles WHERE id = ?", [$oldData['role_id']]);
        if (($targetRole['slug'] ?? '') === 'super_admin' && !$this->auth->isSuperAdmin()) {
            $this->jsonError('You cannot modify a Super Administrator account.', null, 403);
        }
        
        // Prevent regular users from escalating privileges or changing their own role/status here
        $isSelf = ($id === (int)$_SESSION['user_id']);
        if ($isSelf && !$this->auth->isSuperAdmin()) {
            $this->jsonError('You cannot update your own account via this endpoint. Please use the Profile endpoint.', null, 403);
        }
        
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE users SET first_name=?, last_name=?, phone=?, role_id=?, status=?, updated_at=NOW() WHERE id=?",
                [$data['first_name'], $data['last_name'], $data['phone'] ?? null, (int)$data['role_id'], $data['status'] ?? 'active', $id]
            );

            // ENFORCE: Notify the user
            $this->notif->dispatch($id, 'user_profile_updated', 'Account Information Updated',
                "Dear {$oldData['first_name']}, your system account information or role has been updated by an administrator. If you did not authorize this, please contact support immediately."
            );
            
            // ENFORCE: Notify admins
            $this->notifyAdmins('user_updated', 'User Profile Updated', "The profile for {$oldData['first_name']} {$oldData['last_name']} ({$oldData['email']}) was updated by an administrator.");
            
            $this->logAudit('user_updated', 'users', $id, 'User', "User {$oldData['email']} updated", json_encode($oldData), json_encode($data));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/users'], 'User updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('user_update_failed', 'User Update Failed', "Failed to update user {$oldData['email']}: " . $e->getMessage());
            $this->jsonError('Failed to update user: ' . $e->getMessage(), null, 500);
        }
    }

    public function disable(int $id): void
    {
        $this->auth->requirePermission('users.edit');
        $this->verifyCsrf();
        
        if ($id === (int)$_SESSION['user_id']) {
            $this->jsonError('You cannot disable your own account.', null, 409);
        }
        
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) $this->jsonError('User not found.', null, 404);
        
        // ENFORCE: Super Admin Protection
        $targetRole = $this->db->fetchOne("SELECT slug FROM roles WHERE id = ?", [$user['role_id']]);
        if (($targetRole['slug'] ?? '') === 'super_admin' && !$this->auth->isSuperAdmin()) {
            $this->jsonError('You cannot disable a Super Administrator.', null, 403);
        }

        if (in_array($user['status'], ['suspended', 'inactive'])) {
            $this->jsonError('User is already disabled.', null, 400);
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?", [$id]);

            // ENFORCE: Notify the user
            $this->notif->dispatch($id, 'user_disabled', 'Account Suspended',
                "Dear {$user['first_name']}, your system access has been suspended. Please contact the administrator if you believe this is an error."
            );
            
            // ENFORCE: Notify admins
            $this->notifyAdmins('user_disabled', 'User Account Suspended', "The account for {$user['first_name']} {$user['last_name']} ({$user['email']}) has been suspended by an administrator.");
            
            $this->logAudit('user_disabled', 'users', $id, 'User', "User ID {$id} ({$user['email']}) suspended", json_encode($user), json_encode(['status' => 'suspended']));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/users'], 'User suspended successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('user_disable_failed', 'User Disable Failed', "Failed to suspend user {$user['email']}: " . $e->getMessage());
            $this->jsonError('Failed to suspend user: ' . $e->getMessage(), null, 500);
        }
    }

    public function activate(int $id): void
    {
        $this->auth->requirePermission('users.edit');
        $this->verifyCsrf();
        
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) $this->jsonError('User not found.', null, 404);

        // ENFORCE: Super Admin Protection
        $targetRole = $this->db->fetchOne("SELECT slug FROM roles WHERE id = ?", [$user['role_id']]);
        if (($targetRole['slug'] ?? '') === 'super_admin' && !$this->auth->isSuperAdmin()) {
            $this->jsonError('You cannot modify a Super Administrator account.', null, 403);
        }
        
        if ($user['status'] === 'active') {
            $this->jsonError('User is already active.', null, 400);
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?", [$id]);

            // ENFORCE: Notify the user
            $this->notif->dispatch($id, 'user_activated', 'Account Reactivated',
                "Dear {$user['first_name']}, your system access has been reactivated. You can now log in and access all your services."
            );
            
            // ENFORCE: Notify admins
            $this->notifyAdmins('user_activated', 'User Account Reactivated', "The account for {$user['first_name']} {$user['last_name']} ({$user['email']}) has been reactivated by an administrator.");
            
            $this->logAudit('user_activated', 'users', $id, 'User', "User ID {$id} ({$user['email']}) reactivated", json_encode($user), json_encode(['status' => 'active']));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/users'], 'User reactivated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('user_activate_failed', 'User Activation Failed', "Failed to reactivate user {$user['email']}: " . $e->getMessage());
            $this->jsonError('Failed to reactivate user: ' . $e->getMessage(), null, 500);
        }
    }

    public function delete(int $id): void
    {
        $this->auth->requirePermission('users.delete');
        $this->verifyCsrf();
        
        if ($id === (int)$_SESSION['user_id']) {
            $this->jsonError('You cannot delete your own account.', null, 409);
        }
        
        $oldData = $this->db->fetchOne("SELECT * FROM users WHERE id=?", [$id]);
        if (!$oldData) $this->jsonError('User not found.', null, 404);

        // ENFORCE: Super Admin Protection
        $targetRole = $this->db->fetchOne("SELECT slug FROM roles WHERE id = ?", [$oldData['role_id']]);
        if (($targetRole['slug'] ?? '') === 'super_admin' && !$this->auth->isSuperAdmin()) {
            $this->jsonError('You cannot delete a Super Administrator.', null, 403);
        }
        
        $this->db->beginTransaction();
        try {
            // Soft delete by setting status to inactive
            $this->db->execute("UPDATE users SET status='inactive', updated_at=NOW() WHERE id=?", [$id]);

            // ENFORCE: Notify the user
            $this->notif->dispatch($id, 'user_terminated', 'Account Deactivated',
                "Dear {$oldData['first_name']}, your system access has been deactivated. Please contact the administrator if you believe this is an error."
            );
            
            // ENFORCE: Notify admins
            $this->notifyAdmins('user_terminated', 'User Account Deactivated', "The account for {$oldData['first_name']} {$oldData['last_name']} ({$oldData['email']}) has been deactivated by an administrator.");
            
            $this->logAudit('user_deleted', 'users', $id, 'User', "User ID {$id} ({$oldData['email']}) deactivated", json_encode($oldData), json_encode(['status' => 'inactive']));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/users'], 'User deactivated.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('user_delete_failed', 'User Deletion Failed', "Failed to deactivate user {$oldData['email']}: " . $e->getMessage());
            $this->jsonError('Failed to deactivate user: ' . $e->getMessage(), null, 500);
        }
    }

    public function resetPassword(int $id): void
    {
        $this->auth->requirePermission('users.edit');
        $this->verifyCsrf();
        
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) $this->jsonError('User not found.', null, 404);

        // ENFORCE: Super Admin Protection
        $targetRole = $this->db->fetchOne("SELECT slug FROM roles WHERE id = ?", [$user['role_id']]);
        if (($targetRole['slug'] ?? '') === 'super_admin' && !$this->auth->isSuperAdmin()) {
            $this->jsonError('You cannot reset a Super Administrator password.', null, 403);
        }
        
        $this->db->beginTransaction();
        try {
            $newPass = Security::generateToken(8); // Increased length for better security
            $this->db->execute("UPDATE users SET password_hash=?, must_change_password=1, updated_at=NOW() WHERE id=?", [Security::hashPassword($newPass), $id]);
            
            // ENFORCE: Notify the user
            $this->notif->dispatch($id, 'password_reset_by_admin', 'Password Reset by Administrator',
                "Dear {$user['first_name']}, your password has been reset by an administrator. Your new temporary password is: {$newPass}. Please log in and change it immediately."
            );
            
            // ENFORCE: Notify admins
            $this->notifyAdmins('password_reset_by_admin', 'User Password Reset', "The password for user {$user['email']} was reset by an administrator.");
            
            $this->logAudit('password_reset', 'users', $id, 'User', "Password reset for user ID {$id} ({$user['email']})");
            
            $this->db->commit();
            $this->jsonSuccess(['temp_password' => $newPass], 'Password reset. New temporary password: ' . $newPass);
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('password_reset_failed', 'Password Reset Failed', "Failed to reset password for user {$user['email']}: " . $e->getMessage());
            $this->jsonError('Failed to reset password: ' . $e->getMessage(), null, 500);
        }
    }

    // ── PROFILE METHODS ────────────────────────────────────────────

    public function profile(): void
    {
        $rawUser = $this->db->fetchOne("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$_SESSION['user_id']]);
        if (!$rawUser) {
            $this->flash('error', 'User not found.');
            $this->redirect('/login');
        }
        
        $member = null;
        if (!empty($rawUser['member_id'])) {
            $member = $this->db->fetchOne("SELECT * FROM members WHERE id = ? AND deleted_at IS NULL", [$rawUser['member_id']]);
        } else {
            if (!empty($rawUser['nin'])) $member = $this->db->fetchOne("SELECT * FROM members WHERE national_id = ? AND deleted_at IS NULL LIMIT 1", [$rawUser['nin']]);
            if (!$member && !empty($rawUser['passport_number'])) $member = $this->db->fetchOne("SELECT * FROM members WHERE passport_no = ? AND deleted_at IS NULL LIMIT 1", [$rawUser['passport_number']]);
            if (!$member && !empty($rawUser['email'])) $member = $this->db->fetchOne("SELECT * FROM members WHERE email = ? AND deleted_at IS NULL LIMIT 1", [$rawUser['email']]);
        }

        // ENFORCE: IDOR Guard using canAccessMemberRecord (Should always pass for own profile, but good for consistency)
        if ($member && !$this->canAccessMemberRecord($member['id'])) {
            $member = null; 
        }

        // Masking won't apply here because it's the user's own profile, but kept for consistency
        $user = $this->maskSensitiveFields($rawUser); 

        $savingsAccounts = $member ? $this->db->fetchAll("SELECT * FROM savings_accounts WHERE member_id = ? AND status != 'closed'", [$member['id']]) : [];
        $loans = $member ? $this->db->fetchAll("SELECT l.*, lp.name AS product_name FROM loans l JOIN loan_products lp ON lp.id = l.loan_product_id WHERE l.member_id = ? AND l.deleted_at IS NULL ORDER BY l.created_at DESC", [$member['id']]) : [];
        $transactions = $member ? $this->db->fetchAll("SELECT * FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 20", [$member['id']]) : [];
        $shares = $member ? $this->db->fetchOne("SELECT * FROM member_shares WHERE member_id = ?", [$member['id']]) : null;
        $shareTxns = $member ? $this->db->fetchAll("SELECT * FROM share_transactions WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 20", [$member['id']]) : [];

        $sessions = $this->db->fetchAll("SELECT * FROM login_sessions WHERE user_id = ? ORDER BY login_at DESC LIMIT 5", [$_SESSION['user_id']]);
        $settings = array_column($this->db->fetchAll("SELECT `key`, `value` FROM settings"), 'value', 'key');
        $unreadNotifications = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);

        $pageTitle = 'My Profile'; 
        $activePage = 'profile'; 
        $breadcrumbs = ['Profile' => null];
        
        $this->view('users/profile', compact(
            'user', 'member', 'savingsAccounts', 'loans', 'transactions', 'shares', 'shareTxns', 
            'sessions', 'settings', 'pageTitle', 'activePage', 'breadcrumbs', 'unreadNotifications'
        ));
    }

    public function updateProfile(): void
    {
        $this->verifyCsrf();
        $data = $this->getPost(['first_name', 'last_name', 'phone']);
        $oldData = $this->db->fetchOne("SELECT first_name, last_name, phone FROM users WHERE id=?", [$_SESSION['user_id']]);
        
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE users SET first_name=?, last_name=?, phone=?, updated_at=NOW() WHERE id=?",
                [$data['first_name'], $data['last_name'], $data['phone'] ?? null, $_SESSION['user_id']]
            );
            
            // Update session data
            $_SESSION['user']['name'] = $data['first_name'] . ' ' . $data['last_name'];
            $_SESSION['user']['first_name'] = $data['first_name'];
            
            // ENFORCE: Notify the user
            $this->notif->dispatch($_SESSION['user_id'], 'profile_updated', 'Profile Updated',
                "Dear {$data['first_name']}, your profile information has been successfully updated. If you did not make this change, please contact support immediately."
            );
            
            $this->logAudit('profile_updated', 'users', $_SESSION['user_id'], 'User', "Profile updated", json_encode($oldData), json_encode($data));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/profile'], 'Profile updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('profile_update_failed', 'Profile Update Failed', "Failed to update profile for user ID {$_SESSION['user_id']}: " . $e->getMessage());
            $this->jsonError('Failed to update profile: ' . $e->getMessage(), null, 500);
        }
    }

    public function showChangePassword(): void
    {
        $this->view('users/change-password', $this->prepareViewData('Change Password', 'profile', ['Profile' => APP_URL.'/profile', 'Change Password' => null]));
    }

    public function changePassword(): void
    {
        $this->verifyCsrf();
        $data = $this->getPost(['current_password', 'new_password', 'confirm_password']);
        $user = $this->db->fetchOne("SELECT password_hash, first_name FROM users WHERE id=?", [$_SESSION['user_id']]);
        
        if (!Security::verifyPassword($_POST['current_password'] ?? '', $user['password_hash'])) {
            $this->jsonError('Current password is incorrect.', null, 401);
        }
        if (($data['new_password'] ?? '') !== ($data['confirm_password'] ?? '')) {
            $this->jsonError('New passwords do not match.', null, 422);
        }
        if (strlen($data['new_password'] ?? '') < 8) {
            $this->jsonError('Password must be at least 8 characters.', null, 422);
        }
        
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE users SET password_hash=?, must_change_password=0, updated_at=NOW() WHERE id=?",
                [Security::hashPassword($data['new_password']), $_SESSION['user_id']]
            );
            
            // ENFORCE: Notify the user
            $this->notif->dispatch($_SESSION['user_id'], 'password_changed', 'Password Changed Successfully',
                "Dear {$user['first_name']}, your account password has been successfully changed. If you did not make this change, please contact support immediately."
            );

            $this->logAudit('password_changed', 'users', $_SESSION['user_id'], 'User', 'Password changed');
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL . '/profile'], 'Password changed successfully!');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('password_change_failed', 'Password Change Failed', "Failed to change password for user ID {$_SESSION['user_id']}: " . $e->getMessage());
            $this->jsonError('Failed to change password: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

    /**
     * ENFORCE: Mask Sensitive Fields (NIN & Passport)
     * NIN and Passport numbers are masked only showing last 5 characters if logged in user is not owner.
     */
    private function maskSensitiveFields(array $user): array 
    {
        // Only mask if the record being viewed is NOT the currently logged-in user
        if (($user['id'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0)) {
            if (!empty($user['nin'])) {
                $len = strlen($user['nin']);
                $user['nin'] = $len > 5 ? str_repeat('*', $len - 5) . substr($user['nin'], -5) : str_repeat('*', $len);
            }
            if (!empty($user['passport_number'])) {
                $len = strlen($user['passport_number']);
                $user['passport_number'] = $len > 5 ? str_repeat('*', $len - 5) . substr($user['passport_number'], -5) : str_repeat('*', $len);
            }
        }
        return $user;
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
            WHERE p.slug IN ('users.edit', 'users.delete', 'users.view') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}