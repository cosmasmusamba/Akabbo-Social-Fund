<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\Security;

/**
 * AKABBO SOCIAL FUND
 * Base Controller
 */
abstract class BaseController
{
    protected AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    // ── View rendering ───────────────────────────────────────────
    protected function view(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $data['auth']      = $this->auth;
        $data['user']      = $this->auth->user();
        $data['csrfField'] = Security::csrfField();

        extract($data, EXTR_SKIP);

        $viewFile = VIEWS_PATH . DIRECTORY_SEPARATOR . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $view) . '.php';

        if (!file_exists($viewFile)) {
            $this->abort(500, "View not found: {$view}");
        }

        if ($layout) {
            $layoutFile = LAYOUTS_PATH . DIRECTORY_SEPARATOR . $layout . '.php';
            if (!file_exists($layoutFile)) {
                $this->abort(500, "Layout not found: {$layout}");
            }
            ob_start();
            include $viewFile;
            $content = ob_get_clean();
            include $layoutFile;
        } else {
            include $viewFile;
        }
    }

    // ── JSON responses ───────────────────────────────────────────
    protected function jsonSuccess(mixed $data = null, string $message = 'Success', int $code = 200): void
    {
        $this->sendJson(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    protected function jsonError(string $message = 'An error occurred', mixed $errors = null, int $code = 400): void
    {
        $this->sendJson(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    private function sendJson(array $payload, int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── Redirect ─────────────────────────────────────────────────
    protected function redirect(string $url, int $code = 302): void
    {
        if (!str_starts_with($url, 'http')) {
            $url = APP_URL . $url;
        }
        if (!headers_sent()) {
            header('Location: ' . $url, true, $code);
        }
        exit;
    }

    protected function redirectWith(string $type, string $message, string $back = '/dashboard'): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        $referer = $_SERVER['HTTP_REFERER'] ?? (APP_URL . $back);
        $this->redirect($referer);
    }

    // ── Input helpers ────────────────────────────────────────────
    protected function getPost(array $fields = [], bool $sanitize = true): array
    {
        $data = empty($fields) ? $_POST : array_intersect_key($_POST, array_flip($fields));
        return $sanitize ? Security::sanitizeArray($data) : $data;
    }

    protected function getQuery(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? Security::sanitize($value) : $value;
    }

    // ── CSRF ─────────────────────────────────────────────────────
    protected function verifyCsrf(): void
    {
        if (!Security::validateCsrfToken()) {
            $this->jsonError('Invalid or expired security token. Please refresh and try again.', null, 403);
        }
    }

    // ── Validation ───────────────────────────────────────────────
    protected function validateRequired(array $data, array $required): array
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    // ── AJAX detection ───────────────────────────────────────────
    protected function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    // ── Flash messages ───────────────────────────────────────────
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    protected function getFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    // ── Pagination ───────────────────────────────────────────────
    protected function getPaginationParams(): array
    {
        $page  = max(1, (int)($this->getQuery('page', 1)));
        $limit = min((int)($this->getQuery('limit', DEFAULT_PAGE_SIZE)), MAX_PAGE_SIZE);
        return compact('page', 'limit');
    }

    // ── Abort ────────────────────────────────────────────────────
    protected function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message ?: "HTTP {$code}"]);
        } else {
            echo '<h1>Error ' . $code . '</h1><p>' . htmlspecialchars($message) . '</p>';
        }
        exit;
    }
}
