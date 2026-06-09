<?php
/**
 * AKABBO SOCIAL FUND — Web Router
 *
 * Maps HTTP paths to controller actions.
 * Controllers are required explicitly for WAMP/Windows compatibility.
 */

// ── Parse request ────────────────────────────────────────────────
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);

// Method override via hidden _method field or header
if ($requestMethod === 'POST') {
    $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
    if (in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
        $requestMethod = strtoupper($override);
    }
}

// Strip subdirectory base path so routes always start with /
// SCRIPT_NAME is e.g. /akabbo/public/index.php
// We need the app root (/akabbo), NOT the public dir (/akabbo/public)
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
$scriptDir  = rtrim(str_replace('/public/index.php', '', $scriptName), '/');

// Remove query string first, then strip base path
$requestUri = strtok($requestUri, '?') ?: '/';
$requestUri = '/' . ltrim(substr($requestUri, strlen($scriptDir)), '/');

// ── Eager-load all controllers ───────────────────────────────────
// This avoids class_exists() failing on Windows before autoloader fires.
$controllerDir = APP_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR;
$baseCtrl      = $controllerDir . 'BaseController.php';
if (file_exists($baseCtrl)) {
    require_once $baseCtrl;
}
foreach (glob($controllerDir . '*.php') as $file) {
    require_once $file;
}

// Also eager-load helpers, models, services used in every request
foreach ([
    APP_PATH . DIRECTORY_SEPARATOR . 'Helpers'  . DIRECTORY_SEPARATOR . 'Security.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Helpers'  . DIRECTORY_SEPARATOR . 'Format.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Models'   . DIRECTORY_SEPARATOR . 'BaseModel.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Models'   . DIRECTORY_SEPARATOR . 'Member.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Models'   . DIRECTORY_SEPARATOR . 'Loan.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'AuthService.php',
] as $f) {
    if (file_exists($f)) {
        require_once $f;
    }
}

use App\Controllers\{
    AuthController,
    DashboardController,
    MemberController,
    LoanController,
    SavingsController,
    TransactionController,
    ReportController,
    NotificationController,
    AuditController,
    UserController,
    SettingsController,
    TrashController,
    SearchController,
    GroupController,
    LoanProductController,
    ImportController,
};

// ── Route table ──────────────────────────────────────────────────
$routes = [

    // AUTH
    ['GET',    '/login',                       AuthController::class,  'showLogin'],
    ['POST',   '/auth/login',                  AuthController::class,  'login'],
    ['GET',    '/auth/logout',                 AuthController::class,  'logout'],
    ['GET',    '/auth/forgot-password',        AuthController::class,  'showForgotPassword'],
    ['POST',   '/auth/forgot-password',        AuthController::class,  'forgotPassword'],
    ['GET',    '/auth/reset-password/{token}', AuthController::class,  'showResetPassword', ['token']],
    ['POST',   '/auth/reset-password',         AuthController::class,  'resetPassword'],

    // DASHBOARD
    ['GET', '/dashboard',              DashboardController::class, 'index'],
    ['GET', '/dashboard/mini-stats',   DashboardController::class, 'miniStats'],
    ['GET', '/',                       DashboardController::class, 'index'],

    // MEMBERS
    ['GET',  '/members',                  MemberController::class, 'index'],
    ['GET',  '/members/create',           MemberController::class, 'create'],
    ['POST', '/members/store',            MemberController::class, 'store'],
    ['GET',  '/members/search',           MemberController::class, 'search'],
    ['GET',  '/members/export',           MemberController::class, 'export'],
    ['GET',  '/members/{id}',             MemberController::class, 'show',      ['id']],
    ['GET',  '/members/{id}/edit',        MemberController::class, 'edit',      ['id']],
    ['POST', '/members/{id}/update',      MemberController::class, 'update',    ['id']],
    ['POST', '/members/{id}/delete',      MemberController::class, 'delete',    ['id']],
    ['POST', '/members/{id}/kyc-verify',  MemberController::class, 'verifyKyc', ['id']],
    ['GET',  '/members/{id}/statement',   MemberController::class, 'show',      ['id']],

    // LOANS
    ['GET',  '/loans',                    LoanController::class, 'index'],
    ['GET',  '/loans/create',             LoanController::class, 'create'],
    ['POST', '/loans/store',              LoanController::class, 'store'],
    ['GET',  '/loans/calculate',          LoanController::class, 'calculate'],
    ['GET',  '/loans/{id}',               LoanController::class, 'show',     ['id']],
    ['GET',  '/loans/{id}/edit',          LoanController::class, 'edit',     ['id']],
    ['POST', '/loans/{id}/update',        LoanController::class, 'update',   ['id']],
    ['POST', '/loans/{id}/approve',       LoanController::class, 'approve',  ['id']],
    ['POST', '/loans/{id}/reject',        LoanController::class, 'reject',   ['id']],
    ['POST', '/loans/{id}/disburse',      LoanController::class, 'disburse', ['id']],
    ['POST', '/loans/{id}/delete',        LoanController::class, 'delete',   ['id']],
    ['POST', '/loans/repayment',          LoanController::class, 'recordRepayment'],
    ['GET',  '/loans/{id}/schedule',      LoanController::class, 'schedule', ['id']],

    // SAVINGS
    ['GET',  '/savings',                  SavingsController::class, 'index'],
    ['GET',  '/savings/deposit',          SavingsController::class, 'showDeposit'],
    ['POST', '/savings/deposit',          SavingsController::class, 'deposit'],
    ['GET',  '/savings/withdraw',         SavingsController::class, 'showWithdraw'],
    ['POST', '/savings/withdraw',         SavingsController::class, 'withdraw'],
    ['GET',  '/savings/{id}',             SavingsController::class, 'show', ['id']],

    // TRANSACTIONS
    ['GET',  '/transactions',             TransactionController::class, 'index'],
    ['GET',  '/transactions/{id}',        TransactionController::class, 'show',    ['id']],
    ['POST', '/transactions/{id}/approve',TransactionController::class, 'approve', ['id']],
    ['POST', '/transactions/{id}/reverse',TransactionController::class, 'reverse', ['id']],

    // REPORTS
    ['GET', '/reports',                   ReportController::class, 'index'],
    ['GET', '/reports/savings',           ReportController::class, 'savings'],
    ['GET', '/reports/loans',             ReportController::class, 'loans'],
    ['GET', '/reports/transactions',      ReportController::class, 'transactions'],
    ['GET', '/reports/members',           ReportController::class, 'members'],
    ['GET', '/reports/cash-flow',         ReportController::class, 'cashFlow'],

    // NOTIFICATIONS
    ['GET',  '/notifications',              NotificationController::class, 'index'],
    ['POST', '/notifications/mark-read',    NotificationController::class, 'markRead'],
    ['POST', '/notifications/mark-all-read',NotificationController::class, 'markAllRead'],
    ['POST', '/notifications/send',         NotificationController::class, 'send'],

    // AUDIT
    ['GET', '/audit',       AuditController::class, 'index'],
    ['GET', '/audit/{id}',  AuditController::class, 'show', ['id']],

    // USERS
    ['GET',  '/users',                      UserController::class, 'index'],
    ['GET',  '/users/create',               UserController::class, 'create'],
    ['POST', '/users/store',                UserController::class, 'store'],
    ['GET',  '/users/{id}',                 UserController::class, 'show',          ['id']],
    ['POST', '/users/{id}/update',          UserController::class, 'update',         ['id']],
    ['POST', '/users/{id}/delete',          UserController::class, 'delete',         ['id']],
    ['POST', '/users/{id}/reset-password',  UserController::class, 'resetPassword',  ['id']],

    // PROFILE
    ['GET',  '/profile',                    UserController::class, 'profile'],
    ['POST', '/profile/update',             UserController::class, 'updateProfile'],
    ['GET',  '/profile/change-password',    UserController::class, 'showChangePassword'],
    ['POST', '/profile/change-password',    UserController::class, 'changePassword'],

    // SETTINGS
    ['GET',  '/settings',          SettingsController::class, 'index'],
    ['POST', '/settings/update',   SettingsController::class, 'update'],
    ['POST', '/settings/backup',   SettingsController::class, 'backup'],

    // TRASH
    ['GET',  '/trash',                       TrashController::class, 'index'],
    ['POST', '/trash/{type}/{id}/restore',   TrashController::class, 'restore', ['type', 'id']],
    ['POST', '/trash/{type}/{id}/destroy',   TrashController::class, 'destroy', ['type', 'id']],

    // GROUPS
    ['GET',  '/groups',                    GroupController::class,       'index'],
    ['GET',  '/groups/create',             GroupController::class,       'create'],
    ['POST', '/groups/store',              GroupController::class,       'store'],
    ['GET',  '/groups/list',               GroupController::class,       'list'],
    ['GET',  '/groups/{id}',               GroupController::class,       'show',   ['id']],
    ['GET',  '/groups/{id}/edit',          GroupController::class,       'edit',   ['id']],
    ['POST', '/groups/{id}/update',        GroupController::class,       'update', ['id']],
    ['POST', '/groups/{id}/delete',        GroupController::class,       'delete', ['id']],

    // LOAN PRODUCTS
    ['GET',  '/loan-products',             LoanProductController::class, 'index'],
    ['GET',  '/loan-products/create',      LoanProductController::class, 'create'],
    ['POST', '/loan-products/store',       LoanProductController::class, 'store'],
    ['GET',  '/loan-products/{id}/edit',   LoanProductController::class, 'edit',         ['id']],
    ['POST', '/loan-products/{id}/update', LoanProductController::class, 'update',       ['id']],
    ['POST', '/loan-products/{id}/toggle', LoanProductController::class, 'toggleStatus', ['id']],

    // IMPORT / EXPORT
    ['GET',  '/import',                         ImportController::class, 'index'],
    ['GET',  '/import/template/{type}',         ImportController::class, 'downloadTemplate', ['type']],
    ['POST', '/import/members',                 ImportController::class, 'importMembers'],

    // GLOBAL SEARCH
    ['GET', '/search', SearchController::class, 'search'],
];

// ── Route matching ───────────────────────────────────────────────
function akabbo_match_route(array $routes, string $method, string $uri): ?array
{
    foreach ($routes as $route) {
        [$routeMethod, $pattern] = $route;
        $controller = $route[2];
        $action     = $route[3];
        $paramKeys  = $route[4] ?? [];

        if ($routeMethod !== $method) {
            continue;
        }

        // Convert {param} placeholders to capturing groups
        $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        $regex = '@^' . $regex . '$@';

        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches);
            $params = !empty($paramKeys) ? array_combine($paramKeys, $matches) : [];
            return [$controller, $action, $params];
        }
    }
    return null;
}

$match = akabbo_match_route($routes, $requestMethod, $requestUri);

if ($match) {
    [$controllerClass, $action, $params] = $match;

    if (!class_exists($controllerClass)) {
        http_response_code(500);
        die('Controller class not found: ' . htmlspecialchars($controllerClass));
    }

    $controller = new $controllerClass();

    if (!method_exists($controller, $action)) {
        http_response_code(500);
        die('Method not found: ' . htmlspecialchars($controllerClass . '::' . $action));
    }

    call_user_func_array([$controller, $action], array_values($params));

} else {
    // 404
    http_response_code(404);
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Endpoint not found.']);
    } else {
        $errorFile = VIEWS_PATH . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . '404.php';
        if (file_exists($errorFile)) {
            include $errorFile;
        } else {
            echo '<h1>404 – Page Not Found</h1>';
        }
    }
    exit;
}
