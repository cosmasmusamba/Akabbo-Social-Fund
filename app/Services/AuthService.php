<?php
namespace App\Services;

use App\Helpers\Security;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Authentication Service
 *
 * Handles login, logout, session management, lockout,
 * password resets, and permission checks.
 */
class AuthService
{
    private Database $db;

    public function __construct()
    {
        // Session MUST be started before any DB calls or output
        $this->startSecureSession();

        $this->db = Database::getInstance();
    }

    // ── Session setup ────────────────────────────────────────────
    private function startSecureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return; // Already started
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

    // ── Login ────────────────────────────────────────────────────
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip    = Security::getClientIp();

        $user = $this->db->fetchOne(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1",
            [$email]
        );

        if (!$user) {
            $this->logAudit(null, 'login_failed', 'auth', null, null,
                "Failed login for unknown email: {$email}", $ip);
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
                return ['success' => false,
                    'message' => "Account locked after {$attempts} failed attempts. Try again in " . (LOCKOUT_DURATION / 60) . " minutes."];
            }
            $this->db->execute(
                "UPDATE users SET failed_login_attempts=? WHERE id=?",
                [$attempts, $user['id']]
            );
            $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
            return ['success' => false, 'message' => "Invalid email or password. {$remaining} attempt(s) remaining."];
        }

        // Rehash if needed
        if (Security::needsRehash($user['password_hash'])) {
            $this->db->execute(
                "UPDATE users SET password_hash=? WHERE id=?",
                [Security::hashPassword($password), $user['id']]
            );
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
            // Non-fatal — session table issue shouldn't block login
            error_log('[AKABBO] Could not record login session: ' . $e->getMessage());
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
            setcookie(SESSION_NAME, '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
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
        if (empty($_SESSION['user'])) {
            return false;
        }
        if ($_SESSION['user']['role_slug'] === 'super_admin') {
            return true;
        }
        return in_array($permissionSlug, $_SESSION['permissions'] ?? [], true);
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
        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE email=? LIMIT 1",
            [strtolower($email)]
        );
        if (!$user) return null;

        $token   = Security::generateToken(32);
        $expires = (new \DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');
        $this->db->execute(
            "UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE id=?",
            [$token, $expires, $user['id']]
        );
        return $token;
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE password_reset_token=? AND password_reset_expires > NOW() LIMIT 1",
            [$token]
        );
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }
        $this->db->execute(
            "UPDATE users SET password_hash=?, password_reset_token=NULL, password_reset_expires=NULL, must_change_password=0 WHERE id=?",
            [Security::hashPassword($newPassword), $user['id']]
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
            $this->db->execute("
                INSERT INTO audit_logs
                    (user_id, user_name, action, module, record_id, record_type,
                     description, ip_address, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $userName,
                    $action,
                    $module,
                    $recordId,
                    $recordType,
                    $description,
                    $ip ?? Security::getClientIp(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    session_id(),
                ]
            );
        } catch (\Exception $e) {
            error_log('[AKABBO AUDIT ERROR] ' . $e->getMessage());
        }
    }

    // ── Private helpers ──────────────────────────────────────────
    private function getUserPermissions(int $userId): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT p.slug
                 FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN users u ON u.role_id = rp.role_id
                 WHERE u.id = ?",
                [$userId]
            );
            return array_column($rows, 'slug');
        } catch (\Exception $e) {
            error_log('[AKABBO] Could not load permissions: ' . $e->getMessage());
            return [];
        }
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
            'avatar'              => $user['avatar'] ?? null,
            'must_change_password'=> (bool)($user['must_change_password'] ?? false),
        ];
    }
}
