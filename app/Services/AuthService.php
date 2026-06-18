<?php
namespace App\Services;

use App\Helpers\Security;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Authentication Service
 *
 * Handles login, logout, session management, lockout,
 * password resets, permission checks, and automatic member linking.
 */
class AuthService
{
    private Database $db;
    private NotificationService $notif;

    public function __construct()
    {
        $this->startSecureSession();
        $this->db = Database::getInstance();
        $this->notif = new NotificationService();
    }

    // ── Session setup ────────────────────────────────────────────
    private function startSecureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => SESSION_SECURE,
            'httponly' => SESSION_HTTPONLY,
            'samesite' => SESSION_SAMESITE,
        ]);
        session_start();
    }

    // ─ Login ────────────────────────────────────────────────────
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip    = Security::getClientIp();
        
        $user = $this->db->fetchOne(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name 
             FROM users u 
             JOIN roles r ON r.id = u.role_id 
             WHERE u.email = ? LIMIT 1",
            [$email]
        );

        if (!$user) {
            $this->logAudit(null, 'login_failed', 'auth', null, null, "Failed login for unknown email: {$email}", $ip);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Account locked check
        if ($user['status'] === 'locked') {
            if ($user['locked_until'] && new \DateTime() < new \DateTime($user['locked_until'])) {
                return ['success' => false, 'message' => 'Account is temporarily locked. Please try again later.'];
            }
            // Lock expired — auto-unlock
            $this->db->execute(
                "UPDATE users SET status='active', failed_login_attempts=0, locked_until=NULL WHERE id=?",
                [$user['id']]
            );
            $user['status'] = 'active';
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is ' . $user['status'] . '. Contact the administrator.'];
        }

        // Password check
        if (!Security::verifyPassword($password, $user['password_hash'])) {
            $attempts = (int)$user['failed_login_attempts'] + 1;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $until = (new \DateTime())->modify('+' . LOCKOUT_DURATION . ' seconds')->format('Y-m-d H:i:s');
                $this->db->execute(
                    "UPDATE users SET failed_login_attempts=?, status='locked', locked_until=? WHERE id=?",
                    [$attempts, $until, $user['id']]
                );
                
                // ENFORCE: Audit log for lockout
                $this->logAudit($user['id'], 'account_locked', 'auth', $user['id'], 'User', "Account locked after {$attempts} failed attempts", $ip);
                
                // ENFORCE: Notify user via multi-channel dispatch
                $this->notif->dispatch(
                    $user['id'],
                    'account_locked',
                    'Account Temporarily Locked',
                    "Your account has been temporarily locked due to {$attempts} failed login attempts. It will be unlocked in " . (LOCKOUT_DURATION / 60) . " minutes. If this wasn't you, please contact support immediately."
                );

                return ['success' => false, 'message' => "Account locked after {$attempts} failed attempts. Try again in " . (LOCKOUT_DURATION / 60) . " minutes."];
            }
            $this->db->execute("UPDATE users SET failed_login_attempts=? WHERE id=?", [$attempts, $user['id']]);
            $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
            return ['success' => false, 'message' => "Invalid email or password. {$remaining} attempt(s) remaining."];
        }

        // Rehash if needed
        if (Security::needsRehash($user['password_hash'])) {
            $this->db->execute("UPDATE users SET password_hash=? WHERE id=?", [Security::hashPassword($password), $user['id']]);
        }

        // Success — reset attempts, record login
        $sessionId = session_id();
        $this->db->execute(
            "UPDATE users SET failed_login_attempts=0, last_login=NOW(), last_login_ip=?, locked_until=NULL WHERE id=?",
            [$ip, $user['id']]
        );

        try {
            $this->db->execute(
                "INSERT INTO login_sessions (user_id, session_id, ip_address, user_agent, login_at, last_activity, status)
                 VALUES (?, ?, ?, ?, NOW(), NOW(), 'active')",
                [$user['id'], $sessionId, $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
            );
        } catch (\Exception $e) {
            error_log('[AKABBO] Could not record login session: ' . $e->getMessage());
        }

        // Auto-link member if not already linked
        if (empty($user['member_id'])) {
            $linkedMemberId = $this->findMemberIdByCredentials($user);
            if ($linkedMemberId) {
                $this->db->execute("UPDATE users SET member_id = ? WHERE id = ?", [$linkedMemberId, $user['id']]);
                $this->db->execute("UPDATE members SET user_id = ? WHERE id = ?", [$user['id'], $linkedMemberId]);
                $user['member_id'] = $linkedMemberId;
            }
        }

        // This ensures the top bar/sidebar shows the member's avatar, not the empty user avatar
        if (!empty($user['member_id'])) {
            $memberAvatar = $this->db->fetchColumn(
                "SELECT avatar FROM members WHERE id = ? AND avatar IS NOT NULL AND avatar != '' LIMIT 1",
                [$user['member_id']]
            );
            if ($memberAvatar) {
                $user['avatar'] = $memberAvatar;
            }
        }

        session_regenerate_id(true);
        $this->setSession($user);
        $_SESSION['permissions'] = $this->getUserPermissions($user['id']);
        
        $this->logAudit($user['id'], 'login', 'auth', null, null, 'User logged in', $ip);
        return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
    }

    // ── Logout ───────────────────────────────────────────────────
    public function logout(): void
    {
        $userId    = $_SESSION['user_id'] ?? null;
        $sessionId = session_id();
        
        if ($sessionId) {
            try {
                $this->db->execute(
                    "UPDATE login_sessions SET status='logged_out', logout_at=NOW() WHERE session_id=?",
                    [$sessionId]
                );
            } catch (\Exception $e) {
                error_log('[AKABBO] logout session update failed: ' . $e->getMessage());
            }
        }
        
        if ($userId) {
            $this->logAudit($userId, 'logout', 'auth', null, null, 'User logged out');
        }
        
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(SESSION_NAME, '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── Auth check ───────────────────────────────────────────────
    public function isAuthenticated(): bool
    {
        if (empty($_SESSION['user_id']) || empty($_SESSION['login_time'])) {
            return false;
        }
        $lastActivity = $_SESSION['last_activity'] ?? 0;
        if (time() - $lastActivity > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public function user(): ?array
    {
        return $this->isAuthenticated() ? ($_SESSION['user'] ?? null) : null;
    }

    public function can(string $permissionSlug): bool
    {
        if (empty($_SESSION['user'])) return false;
        if ($this->isSuperAdmin()) return true;

        $userId = $_SESSION['user_id'];
        
        // STEP 1: Check user-specific permissions (highest priority)
        $userPerm = $this->db->fetchOne(
            "SELECT up.is_allowed FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ? AND p.slug = ?",
            [$userId, $permissionSlug]
        );
        if ($userPerm !== null) return (bool)$userPerm['is_allowed'];

        // STEP 2: Check role-based permissions
        $roleId = $_SESSION['user']['role_id'];
        $rolePerm = $this->db->fetchOne(
            "SELECT rp.permission_id FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.slug = ?",
            [$roleId, $permissionSlug]
        );
        
        return $rolePerm !== null;
    }

    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->can($p)) return true;
        }
        return false;
    }

    public function requireAuth(string $redirect = '/login'): void
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: ' . APP_URL . $redirect);
            exit;
        }
    }

    public function requirePermission(string $permissionSlug): void
    {
        $this->requireAuth();
        if (!$this->can($permissionSlug)) {
            $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
                exit;
            }
            http_response_code(403);
            header('Location: ' . APP_URL . '/dashboard?error=unauthorized');
            exit;
        }
    }

    // ── Password reset ───────────────────────────────────────────
    public function initiatePasswordReset(string $email): ?string
    {
        $user = $this->db->fetchOne("SELECT id, first_name FROM users WHERE email=? LIMIT 1", [strtolower($email)]);
        if (!$user) return null;
        
        $token   = Security::generateToken(32);
        $expires = (new \DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');
        $this->db->execute(
            "UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE id=?",
            [$token, $expires, $user['id']]
        );
        
        // ENFORCE: Audit log for reset initiation
        $this->logAudit($user['id'], 'password_reset_initiated', 'auth', $user['id'], 'User', "Password reset initiated for {$email}", Security::getClientIp());
        
        return $token;
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        $user = $this->db->fetchOne(
            "SELECT id, first_name, email FROM users WHERE password_reset_token=? AND password_reset_expires > NOW() LIMIT 1",
            [$token]
        );
        if (!$user) return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        
        $this->db->execute(
            "UPDATE users SET password_hash=?, password_reset_token=NULL, password_reset_expires=NULL, must_change_password=0 WHERE id=?",
            [Security::hashPassword($newPassword), $user['id']]
        );
        
        // ENFORCE: Audit log for reset completion
        $this->logAudit($user['id'], 'password_reset_completed', 'auth', $user['id'], 'User', "Password reset completed for {$user['email']}", Security::getClientIp());
        
        // ENFORCE: Notify user via multi-channel dispatch
        $this->notif->dispatch(
            $user['id'],
            'password_reset_completed',
            'Password Changed Successfully',
            "Dear {$user['first_name']}, your password has been successfully reset. If you did not make this change, please contact support immediately."
        );
        
        return ['success' => true, 'message' => 'Password reset successfully.'];
    }

    // ── Audit logging ────────────────────────────────────────────
    public function logAudit(
        ?int   $userId,
        string $action,
        string $module,
        ?int   $recordId   = null,
        ?string $recordType = null,
        string $description = '',
        ?string $ip         = null
    ): void {
        try {
            $userName = $_SESSION['user']['name'] ?? null;
            $this->db->execute(
                "INSERT INTO audit_logs (user_id, user_name, action, module, record_id, record_type, description, ip_address, user_agent, session_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId, $userName, $action, $module, $recordId, $recordType, $description,
                    $ip ?? Security::getClientIp(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    session_id()
                ]
            );
        } catch (\Exception $e) {
            error_log('[AKABBO AUDIT ERROR] ' . $e->getMessage());
        }
    }

    // ── Private helpers ──────────────────────────────────────────
    
    /**
     * Find member ID using strict priority: NIN → Passport → Email.
     * FIX: Added `AND user_id IS NULL` to prevent overwriting existing links.
     */
    private function findMemberIdByCredentials(array $user): ?int
    {
        // Priority 1: National ID (NIN)
        if (!empty($user['nin'])) {
            $id = $this->db->fetchColumn(
                "SELECT id FROM members WHERE national_id = ? AND deleted_at IS NULL AND user_id IS NULL LIMIT 1", 
                [$user['nin']]
            );
            if ($id) return (int)$id;
        }

        // Priority 2: Passport Number
        if (!empty($user['passport_number'])) {
            $id = $this->db->fetchColumn(
                "SELECT id FROM members WHERE passport_no = ? AND deleted_at IS NULL AND user_id IS NULL LIMIT 1", 
                [$user['passport_number']]
            );
            if ($id) return (int)$id;
        }

        // Priority 3: Email (Fallback)
        if (!empty($user['email'])) {
            $id = $this->db->fetchColumn(
                "SELECT id FROM members WHERE email = ? AND deleted_at IS NULL AND user_id IS NULL LIMIT 1", 
                [$user['email']]
            );
            if ($id) return (int)$id;
        }

        return null;
    }

    private function getUserPermissions(int $userId): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN users u ON u.role_id = rp.role_id WHERE u.id = ?",
                [$userId]
            );
            return array_column($rows, 'slug');
        } catch (\Exception $e) {
            error_log('[AKABBO] Could not load permissions: ' . $e->getMessage());
            return [];
        }
    }

    public function isSuperAdmin(): bool
    {
        return !empty($_SESSION['user']['is_super_admin']);
    }

    private function setSession(array $user): void
    {
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user'] = [
            'id'                  => $user['id'],
            'name'                => trim($user['first_name'] . ' ' . $user['last_name']),
            'first_name'          => $user['first_name'],
            'last_name'           => $user['last_name'],
            'email'               => $user['email'],
            'role_id'             => $user['role_id'],
            'role_slug'           => $user['role_slug'],
            'role_name'           => $user['role_name'],
            'is_super_admin'      => (bool)($user['is_super_admin'] ?? false),
            'member_id'           => (int)($user['member_id'] ?? 0),
            'avatar'              => $user['avatar'] ?? null,
            'must_change_password'=> (bool)($user['must_change_password'] ?? false),
        ];
    }
}