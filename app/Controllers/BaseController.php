<?php
// Enforce RBAC + IDOR
namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\Security;
use Database;

/**
 * AKABBO SOCIAL FUND
 * Base Controller
 */
abstract class BaseController
{
    protected AuthService $auth;
    protected Database $db;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->db   = Database::getInstance();
    }

    // ── View Rendering ───────────────────────────────────────────
    protected function view(string $view, array $data = [], ?string $layout = 'main'): void
    {
        // Inject common data automatically
        $data['auth']                = $this->auth;
        $data['user']                = $data['user'] ?? $this->auth->user(); 
        $data['csrfField']           = Security::csrfField();
        $data['settings']            = $data['settings'] ?? $this->getSettings();
        $data['unreadNotifications'] = $data['unreadNotifications'] ?? $this->getUnreadCount();

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

    // ── Common View Data Builder ─────────────────────────────────
    protected function prepareViewData(string $title, string $activePage, array $breadcrumbs = []): array
    {
        return [
            'pageTitle'   => $title,
            'activePage'  => $activePage,
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    // ── Reusable Helpers ─────────────────────────────────────────
    protected function getSettings(): array
    {
        static $settings = null;
        if ($settings === null) {
            $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
            $settings = array_column($rows, 'value', 'key');
        }
        return $settings;
    }

    // ── Common View Data Builder ─────────────────────────────────
    protected function getUnreadCount(): int
    {
        if (empty($_SESSION['user_id'])) {
            return 0;
        }
        
        // Use the new Notification model to get permission-filtered unread count
        $notificationModel = new \App\Models\Notification();
        return $notificationModel->getUnreadCount($_SESSION['user_id']);
    }

    // ── Unified Audit Logging ────────────────────────────────────
    protected function logAudit(
        string $action,
        string $module,
        ?int $recordId = null,
        ?string $recordType = null,
        string $description = '',
        ?string $oldValues = null,
        ?string $newValues = null
    ): void {
        $this->auth->logAudit(
            $_SESSION['user_id'] ?? null,
            $action,
            $module,
            $recordId,
            $recordType,
            $description,
            $oldValues,
            $newValues
        );
    }

    // ── JSON Responses ───────────────────────────────────────────
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

    // ── Redirect & Flash ─────────────────────────────────────────
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

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    // ── Input & Validation ───────────────────────────────────────
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

    protected function verifyCsrf(): void
    {
        if (!Security::validateCsrfToken()) {
            $this->jsonError('Invalid or expired security token. Please refresh and try again.', null, 403);
        }
    }

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

    protected function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

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
            $errorFile = VIEWS_PATH . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . $code . '.php';
            if (file_exists($errorFile)) {
                include $errorFile;
            } else {
                echo '<h1>Error ' . $code . '</h1><p>' . htmlspecialchars($message) . '</p>';
            }
        }
        exit;
    }

    // ── RBAC & IDOR ENFORCEMENT ENGINE ───────────────────────────

    /**
     * Check if the current user can access a specific member's records.
     * Evaluates: Super Admin > Own Record > Group Manager > Global Staff
     */
    protected function canAccessMemberRecord(int $targetMemberId): bool
    {
        // 1. Super admins can access everything
        if ($this->auth->isSuperAdmin()) {
            return true;
        }

        $user = $this->auth->user();
        if (!$user) return false;

        // 2. Own record access (Member portal)
        if (!empty($user['member_id']) && (int)$user['member_id'] === $targetMemberId) {
            return true;
        }

        // 3. Group Manager access (Can see members in their own group)
        if ($this->auth->can('groups.view_members')) {
            $userGroupId = $this->db->fetchColumn("SELECT group_id FROM members WHERE id = ?", [$user['member_id']]);
            $targetGroupId = $this->db->fetchColumn("SELECT group_id FROM members WHERE id = ?", [$targetMemberId]);
            
            if ($userGroupId && $userGroupId === $targetGroupId) {
                return true;
            }
        }

        // 4. Global staff access (e.g., regular staff with 'members.view')
        if ($this->auth->can('members.view')) {
            return true;
        }

        return false;
    }

    /**
     * 1. GATEKEEPER: Ensures the user has AT LEAST ONE of the required scope permissions.
     * Blocks access immediately if they lack all defined permissions.
     * 
     * @param array $permissions e.g., ['global' => 'members.view', 'group' => 'groups.view_members', 'personal' => 'members.view_own']
     * @param string $errorMessage
     */
    protected function requireScopeAccess(array $permissions, string $errorMessage = 'You do not have permission to access this resource.'): void 
    {
        $hasGlobal   = !empty($permissions['global']) && $this->auth->can($permissions['global']);
        $hasGroup    = !empty($permissions['group']) && $this->auth->can($permissions['group']);
        $hasPersonal = !empty($permissions['personal']) && $this->auth->can($permissions['personal']);

        if (!$hasGlobal && !$hasGroup && !$hasPersonal) {
            if ($this->isAjax()) {
                $this->jsonError($errorMessage, null, 403);
            } else {
                $this->flash('error', $errorMessage);
                $this->redirect('/dashboard');
            }
        }
    }

    /**
     * 2. RESOLVER: Determines the exact data scope (Global, Group, Personal, or None) 
     * based on the user's highest-level permission.
     * 
     * @param array $permissions
     * @return array ['type' => 'global|group|personal|none', 'member_id' => int|null, 'group_id' => int|null]
     */
    protected function resolveDataScope(array $permissions): array 
    {
        $user = $this->auth->user();
        
        // Precedence 1: Global Access
        if (!empty($permissions['global']) && $this->auth->can($permissions['global'])) {
            return ['type' => 'global', 'member_id' => null, 'group_id' => null];
        }

        // Precedence 2: Group Access
        if (!empty($permissions['group']) && $this->auth->can($permissions['group']) && !empty($user['member_id'])) {
            $groupId = $this->db->fetchColumn("SELECT group_id FROM members WHERE id = ?", [$user['member_id']]);
            if ($groupId) {
                return ['type' => 'group', 'member_id' => null, 'group_id' => (int)$groupId];
            }
        }

        // Precedence 3: Personal Access
        if (!empty($permissions['personal']) && $this->auth->can($permissions['personal']) && !empty($user['member_id'])) {
            return ['type' => 'personal', 'member_id' => (int)$user['member_id'], 'group_id' => null];
        }

        // Fallback: No Access
        return ['type' => 'none', 'member_id' => null, 'group_id' => null];
    }

    /**
     * 3. SQL BUILDER: Generates the SQL WHERE clause fragment to enforce the resolved scope.
     * 
     * @param array $scope The array returned by resolveDataScope()
     * @param string $column The database column to filter on (e.g., 't.member_id', 'sa.member_id', 'id')
     * @return string SQL fragment (e.g., "AND member_id = 5" or "AND 1=0")
     */
    protected function buildScopeCondition(array $scope, string $column = 'member_id'): string 
    {
        switch ($scope['type']) {
            case 'personal':
                return "AND {$column} = " . (int)$scope['member_id'];
                
            case 'group':
                // Ensures soft-deleted members aren't accidentally included in the group scope
                return "AND {$column} IN (SELECT id FROM members WHERE group_id = " . (int)$scope['group_id'] . " AND deleted_at IS NULL)";
                
            case 'none':
                return "AND 1=0"; // Forces an empty result set securely
                
            case 'global':
            default:
                return ""; // No restrictions
        }
    }
}