<?php
namespace App\Controllers;
use App\Services\AuthService;
use App\Helpers\Security;

/**
 * AKABBO SOCIAL FUND - Auth Controller
 * Handles login, logout, password reset flows.
 */
class AuthController extends BaseController
{
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
        $data   = $this->getPost(['email','password','remember']);
        $result = $this->auth->login($data['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            if (!empty($data['remember'])) {
                setcookie('akabbo_remember', $_SESSION['user_id'], time() + 604800, '/', '', false, true);
            }
            $redirect = $_SESSION['intended_url'] ?? (APP_URL . '/dashboard');
            unset($_SESSION['intended_url']);
            $this->jsonSuccess(['redirect' => $redirect], $result['message']);
        } else {
            $this->jsonError($result['message'], null, 401);
        }
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->redirect('/login');
    }

    public function showForgotPassword(): void
    {
        $csrfField = Security::csrfField();
        $this->view('auth/forgot-password', compact('csrfField'), null);
    }

    public function forgotPassword(): void
    {
        $this->verifyCsrf();
        $email = $this->getPost(['email'])['email'] ?? '';
        $token = $this->auth->initiatePasswordReset($email);
        // Always respond success to prevent email enumeration
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
        $result = $this->auth->resetPassword($data['token'] ?? '', $data['password']);
        if ($result['success']) {
            $this->jsonSuccess(['redirect' => APP_URL.'/login'], $result['message']);
        } else {
            $this->jsonError($result['message'], null, 400);
        }
    }
}
