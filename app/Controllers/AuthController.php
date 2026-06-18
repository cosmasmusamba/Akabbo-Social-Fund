<?php
// Enforce logAudit and (RBAC + IDOR). Notify user via dispatch for all actions.
// NOTE: Data scoping (Global/Group/Personal) is inherently handled by the Auth system, 
// as users can only interact with their own credentials and sessions.
namespace App\Controllers;

use App\Services\AuthService;
use App\Services\NotificationService;
use App\Helpers\Security;

/**
 * AKABBO SOCIAL FUND - Auth Controller
 * Handles login, logout, password reset flows with strict audit and notification enforcement.
 */
class AuthController extends BaseController
{
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->notif = new NotificationService();
    }

    // ── LOGIN FLOW ─────────────────────────────────────────────────

    public function showLogin(): void
    {
        if ($this->auth->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        Security::generateCsrfToken();
        $settings = [];
        try {
            $db = \Database::getInstance();
            $rows = $db->fetchAll("SELECT `key`,`value` FROM settings WHERE `key` IN ('org_name','org_tagline')");
            foreach ($rows as $r) $settings[$r['key']] = $r['value'];
        } catch (\Exception $e) {}
        
        $csrfField = Security::csrfField();
        $this->view('auth/login', compact('settings','csrfField'), null);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $data   = $this->getPost(['email', 'password', 'remember']);
        $email  = strtolower(trim($data['email'] ?? ''));
        
        // ENFORCE: Pre-check if account is locked to prevent brute force
        $userRecord = $this->db->fetchOne("SELECT id, first_name, failed_login_attempts, locked_until FROM users WHERE email = ?", [$email]);
        if ($userRecord && !empty($userRecord['locked_until']) && strtotime($userRecord['locked_until']) > time()) {
            $this->logAudit('login_blocked_locked', 'auth', $userRecord['id'], 'User', "Login blocked: Account locked until {$userRecord['locked_until']}");
            $this->jsonError('Your account has been temporarily locked due to too many failed attempts. Please try again later or contact support.', null, 423);
        }

        $result = $this->auth->login($email, $_POST['password'] ?? '');

        if ($result['success']) {
            $user = $result['user'];
            
            // ENFORCE: Audit Login Success
            $this->logAudit('login_success', 'auth', $user['id'], 'User', 'User logged in successfully');

            // ENFORCE: Notify user of new login via multi-channel dispatch
            $this->notif->dispatch(
                (int)$user['id'], 
                'login_success', 
                'New Login Detected', 
                "Dear {$user['first_name']}, a new login to your account was detected from IP " . Security::getClientIp() . ". If this was not you, please change your password immediately."
            );

            if (!empty($data['remember'])) {
                setcookie('akabbo_remember', $_SESSION['user_id'], time() + 604800, '/', '', false, true);
            }
            
            $redirect = $_SESSION['intended_url'] ?? (APP_URL . '/dashboard');
            unset($_SESSION['intended_url']);

            if ($this->isAjax()) {
                $this->jsonSuccess(['redirect' => $redirect], $result['message']);
            } else {
                $this->redirect($redirect);
            }
        } else {
            // ENFORCE: Audit Login Failure
            $this->logAudit('login_failed', 'auth', $userRecord['id'] ?? null, 'User', "Failed login attempt for email: {$email}");

            // ENFORCE: Notify user of failed login attempt (Security Alert)
            if ($userRecord) {
                $this->notif->dispatch(
                    (int)$userRecord['id'],
                    'login_failed',
                    'Failed Login Attempt',
                    "Dear {$userRecord['first_name']}, someone attempted to log into your account with an incorrect password from IP " . Security::getClientIp() . ". If this was not you, please secure your account."
                );
            }

            if ($this->isAjax()) {
                $this->jsonError($result['message'], null, 401);
            } else {
                $this->flash('error', $result['message']);
                $this->redirect('/login');
            }
        }
    }

    // ── LOGOUT FLOW ────────────────────────────────────────────────

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['user']['name'] ?? 'Unknown';
        
        // ENFORCE: Audit Logout before destroying session
        if ($userId) {
            $this->logAudit('logout', 'auth', $userId, 'User', "User '{$userName}' logged out");
        }

        $this->auth->logout();
        
        if ($this->isAjax()) {
            $this->jsonSuccess(['redirect' => APP_URL . '/login'], 'Logged out successfully.');
        } else {
            $this->redirect('/login');
        }
    }

    // ── PASSWORD RESET FLOW ────────────────────────────────────────

    public function showForgotPassword(): void
    {
        $csrfField = Security::csrfField();
        $this->view('auth/forgot-password', compact('csrfField'), null);
    }

    public function forgotPassword(): void
    {
        $this->verifyCsrf();
        $email = strtolower(trim($this->getPost(['email'])['email'] ?? ''));
        
        // ENFORCE: Audit the request regardless of whether the user exists
        $this->logAudit('password_reset_requested', 'auth', null, 'User', "Password reset requested for email: {$email}");

        $token = $this->auth->initiatePasswordReset($email);

        // ENFORCE: Notify user if account exists (using dispatch for multi-channel)
        if ($token) {
            $user = $this->db->fetchOne("SELECT id, first_name FROM users WHERE email = ?", [$email]);
            if ($user) {
                $resetLink = APP_URL . "/auth/reset-password/{$token}";
                $this->notif->dispatch(
                    (int)$user['id'],
                    'password_reset_request',
                    'Password Reset Request',
                    "Dear {$user['first_name']}, a password reset was requested for your account. Click here to reset: {$resetLink}. If you did not request this, please ignore this message."
                );
            }
        }

        // Always respond success to prevent email enumeration (Security Best Practice)
        $this->jsonSuccess(null, 'If an account exists for that email, a password reset link has been sent.');
    }

    public function showResetPassword(string $token): void
    {
        $csrfField = Security::csrfField();
        $this->view('auth/reset-password', compact('token','csrfField'), null);
    }

    public function resetPassword(): void
    {
        $this->verifyCsrf();
        $data = $this->getPost(['token','password','password_confirm']);
        
        if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) {
            $this->jsonError('Passwords do not match.', null, 422);
        }
        if (strlen($data['password'] ?? '') < 8) {
            $this->jsonError('Password must be at least 8 characters.', null, 422);
        }

        // ENFORCE: IDOR/Security: Fetch user ID using the valid token BEFORE resetting it
        $user = $this->db->fetchOne(
            "SELECT u.id, u.first_name, u.email FROM users u WHERE u.password_reset_token = ? AND u.password_reset_expires > NOW()", 
            [$data['token'] ?? '']
        );

        if (!$user) {
            // ENFORCE: Audit invalid/expired token attempts
            $this->logAudit('password_reset_invalid_token', 'auth', null, 'User', "Invalid or expired token used for password reset");
            $this->jsonError('The password reset link is invalid or has expired.', null, 400);
        }

        $result = $this->auth->resetPassword($data['token'], $data['password']);
        
        if ($result['success']) {
            // ENFORCE: Audit Password Reset Success
            $this->logAudit('password_reset_success', 'auth', $user['id'], 'User', 'Password reset successfully');
            
            // ENFORCE: Notify user of password change via multi-channel dispatch
            $this->notif->dispatch(
                (int)$user['id'],
                'password_changed',
                'Password Changed Successfully',
                "Dear {$user['first_name']}, your account password has been successfully changed. If you did not make this change, please contact support immediately."
            );

            $this->jsonSuccess(['redirect' => APP_URL.'/login'], $result['message']);
        } else {
            // ENFORCE: Audit Password Reset Failure
            $this->logAudit('password_reset_failed', 'auth', $user['id'], 'User', "Password reset failed: " . $result['message']);
            $this->jsonError($result['message'], null, 400);
        }
    }
}