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

// Strip subdirectory base path
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
$scriptDir  = rtrim(str_replace('/public/index.php', '', $scriptName), '/');
$requestUri = '/' . ltrim(substr(strtok($requestUri, '?') ?: '/', strlen($scriptDir)), '/');

// ── Eager-load all controllers ───────────────────────────────────
$controllerDir = APP_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR;
$baseCtrl      = $controllerDir . 'BaseController.php';
if (file_exists($baseCtrl)) require_once $baseCtrl;

foreach (glob($controllerDir . '*.php') as $file) {
    require_once $file;
}

// Also eager-load core helpers, models, services
foreach ([
    APP_PATH . DIRECTORY_SEPARATOR . 'Helpers'  . DIRECTORY_SEPARATOR . 'Security.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Helpers'  . DIRECTORY_SEPARATOR . 'Format.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Models'   . DIRECTORY_SEPARATOR . 'BaseModel.php',
    APP_PATH . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'AuthService.php',
] as $f) {
    if (file_exists($f)) require_once $f;
}

use App\Controllers\{
    ApprovalController,
    AuditController,
    AuthController,
    DashboardController,
    ExpenseController,
    FundTransferController,
    GroupController,
    ImportController,
    LoanController,
    LoanProductController,
    MemberController,
    NotificationController,
    ReportController,
    SavingsController,
    SearchController,
    SettingsController,
    AccessControlController,
    ShareController,
    SocialFundFeeController,
    TransactionController,
    TrashController,
    UserController,
    CronController,
};

// ── Route table ──────────────────────────────────────────────────
$routes = [
    // AUTH
    ['GET',  '/login',                       AuthController::class, 'showLogin'],
    ['POST', '/auth/login',                  AuthController::class, 'login'],
    ['GET',  '/auth/logout',                 AuthController::class, 'logout'],
    ['GET',  '/auth/forgot-password',        AuthController::class, 'showForgotPassword'],
    ['POST', '/auth/forgot-password',        AuthController::class, 'forgotPassword'],
    ['GET',  '/auth/reset-password/{token}', AuthController::class, 'showResetPassword', ['token']],
    ['POST', '/auth/reset-password',         AuthController::class, 'resetPassword'],

    // DASHBOARD
    ['GET', '/dashboard',            DashboardController::class, 'index'],
    ['GET', '/dashboard/mini-stats', DashboardController::class, 'miniStats'],
    ['GET', '/',                     DashboardController::class, 'index'],

    // MEMBERS
    ['GET',  '/members',                 MemberController::class, 'index'],
    ['GET',  '/members/create',          MemberController::class, 'create'],
    ['POST', '/members/store',           MemberController::class, 'store'],
    ['GET',  '/members/search',          MemberController::class, 'search'],
    ['GET',  '/members/export',          MemberController::class, 'export'],
    ['GET',  '/members/{id}',            MemberController::class, 'show',      ['id']],
    ['GET',  '/members/{id}/edit',       MemberController::class, 'edit',      ['id']],
    ['POST', '/members/{id}/update',     MemberController::class, 'update',    ['id']],
    ['POST', '/members/{id}/delete',     MemberController::class, 'delete',    ['id']],
    ['POST', '/members/{id}/kyc-verify', MemberController::class, 'verifyKyc', ['id']],
    ['GET',  '/members/{id}/statement',  MemberController::class, 'statement', ['id']],
    ['POST', '/members/{id}/send-kyc-reminder', MemberController::class, 'sendKycReminder', ['id']],
    ['POST', '/members/{id}/disable',  MemberController::class, 'disable',  ['id']],
    ['POST', '/members/{id}/activate', MemberController::class, 'activate', ['id']],

    // LOANS
    ['GET',  '/loans',             LoanController::class, 'index'],
    ['GET',  '/loans/create',      LoanController::class, 'create'],
    ['POST', '/loans/store',       LoanController::class, 'store'],
    ['GET',  '/loans/calculate',   LoanController::class, 'calculate'],
    ['GET',  '/loans/{id}',        LoanController::class, 'show',     ['id']],
    ['GET',  '/loans/{id}/edit',   LoanController::class, 'edit',     ['id']],
    ['POST', '/loans/{id}/update', LoanController::class, 'update',   ['id']],
    ['POST', '/loans/{id}/approve',LoanController::class, 'approve',  ['id']],
    ['POST', '/loans/{id}/reject', LoanController::class, 'reject',   ['id']],
    ['POST', '/loans/{id}/disburse', LoanController::class, 'requestDisbursement', ['id']],
    ['POST', '/loans/{id}/delete', LoanController::class, 'delete',   ['id']],
    ['POST', '/loans/repayment',   LoanController::class, 'recordRepayment'],
    ['GET',  '/loans/{id}/schedule', LoanController::class, 'schedule', ['id']],

    // SAVINGS
    ['GET',  '/savings',            SavingsController::class, 'index'],
    ['GET',  '/savings/deposit',    SavingsController::class, 'showDeposit'],
    ['POST', '/savings/deposit',    SavingsController::class, 'deposit'],
    ['GET',  '/savings/withdraw',   SavingsController::class, 'showWithdraw'],
    ['POST', '/savings/withdraw',   SavingsController::class, 'withdraw'],
    ['GET',  '/savings/transfer',   SavingsController::class, 'showTransfer'],
    ['POST', '/savings/transfer',   SavingsController::class, 'transfer'],
    ['POST', '/savings/post-interest', SavingsController::class, 'postInterest'],
    ['GET',  '/savings/{id}',       SavingsController::class, 'show', ['id']],
    ['GET',  '/savings/{id}/statement', SavingsController::class, 'statement', ['id']],
    ['POST', '/savings/{id}/goal',  SavingsController::class, 'updateGoal', ['id']],

    // TRANSACTIONS
    ['GET',  '/transactions',              TransactionController::class, 'index'],
    ['GET',  '/transactions/{id}',         TransactionController::class, 'show',    ['id']],
    ['POST', '/transactions/{id}/approve', TransactionController::class, 'approve', ['id']],
    ['POST', '/transactions/{id}/reverse', TransactionController::class, 'reverse', ['id']],

    // APPROVALS (Added)
    ['GET',  '/approvals',              ApprovalController::class, 'index'],
    ['GET',  '/approvals/{id}',         ApprovalController::class, 'show', ['id']],
    ['POST', '/approvals/{id}/approve', ApprovalController::class, 'approve', ['id']],
    ['POST', '/approvals/{id}/reject',  ApprovalController::class, 'reject', ['id']],
    ['GET',  '/approvals/pending-count',ApprovalController::class, 'pendingCount'],

    // FUND TRANSFERS (Added)
    ['GET',  '/transfers',              FundTransferController::class, 'index'],
    ['GET',  '/transfers/create',       FundTransferController::class, 'create'],
    ['POST', '/transfers/store',        FundTransferController::class, 'store'],
    ['GET',  '/transfers/{id}',         FundTransferController::class, 'show', ['id']],
    ['POST', '/transfers/{id}/reverse', FundTransferController::class, 'requestReversal', ['id']],

    // SHARES (Added)
    ['GET',  '/shares',                 ShareController::class, 'index'],
    ['GET',  '/shares/create',          ShareController::class, 'create'],
    ['POST', '/shares/store',           ShareController::class, 'store'],
    ['POST', '/shares/{id}/approve',    ApprovalController::class, 'approve', ['id']],
    ['POST', '/shares/{id}/reject',     ApprovalController::class, 'reject', ['id']],
    ['GET',  '/shares/member/{id}',     ShareController::class, 'member', ['id']],
    ['GET',  '/shares/report',          ShareController::class, 'report'],
    ['POST', '/shares/config',          ShareController::class, 'updateConfig'],

    // SOCIAL FUND FEES (Added)
    ['GET',  '/social-fund',                SocialFundFeeController::class, 'index'],
    ['GET',  '/social-fund/create',         SocialFundFeeController::class, 'create'],
    ['POST', '/social-fund/store',          SocialFundFeeController::class, 'store'],
    ['GET', '/social-fund/{id}/edit',       SocialFundFeeController::class, 'edit', ['id']],
    ['POST', '/social-fund/{id}/update',    SocialFundFeeController::class, 'update', ['id']],
    ['POST', '/social-fund/generate',       SocialFundFeeController::class, 'generatePeriod'],
    ['POST', '/social-fund/pay',            SocialFundFeeController::class, 'recordPayment'],
    ['POST', '/social-fund/{id}/waive',     SocialFundFeeController::class, 'waive', ['id']],
    ['GET',  '/social-fund/payments',       SocialFundFeeController::class, 'periodPayments'],
    ['POST', '/social-fund/mark-overdue',   SocialFundFeeController::class, 'markOverdue'],

    // EXPENSES (Added)
    ['GET',  '/expenses',             ExpenseController::class, 'index'],
    ['GET',  '/expenses/create',      ExpenseController::class, 'create'],
    ['POST', '/expenses/store',       ExpenseController::class, 'store'],
    ['GET',  '/expenses/{id}',        ExpenseController::class, 'show', ['id']],
    ['GET',  '/expenses/{id}/edit',   ExpenseController::class, 'edit', ['id']],
    ['POST', '/expenses/{id}/update', ExpenseController::class, 'update', ['id']],
    ['POST', '/expenses/{id}/paid',   ExpenseController::class, 'markPaid', ['id']],
    ['POST', '/expenses/{id}/delete', ExpenseController::class, 'delete', ['id']],
    ['GET',  '/expenses/export',      ExpenseController::class, 'export'],

    // REPORTS
    ['GET', '/reports',              ReportController::class, 'index'],
    ['GET', '/reports/savings',      ReportController::class, 'savings'],
    ['GET', '/reports/loans',        ReportController::class, 'loans'],
    ['GET', '/reports/transactions', ReportController::class, 'transactions'],
    ['GET', '/reports/members',      ReportController::class, 'members'],
    ['GET', '/reports/cash-flow',    ReportController::class, 'cashFlow'],
    ['GET', '/reports/export',       ReportController::class, 'export'],
    ['GET', '/reports/service-fees', ReportController::class, 'serviceFees'],
    ['GET', '/reports/shares',       ReportController::class, 'shares'],
    ['GET', '/reports/expenses',     ReportController::class, 'expenses'],
    ['GET', '/reports/transfers',    ReportController::class, 'transfers'],
    ['GET', '/reports/social-fund',  ReportController::class, 'socialFund'],
    ['GET', '/reports/service-fees', ReportController::class, 'serviceFees'],

    // NOTIFICATIONS
    ['GET',  '/notifications',               NotificationController::class, 'index'],
    ['POST', '/notifications/mark-read',     NotificationController::class, 'markRead'],
    ['POST', '/notifications/mark-all-read', NotificationController::class, 'markAllRead'],
    ['POST', '/notifications/send',          NotificationController::class, 'send'],

    // AUDIT
    ['GET', '/audit',      AuditController::class, 'index'],
    ['GET', '/audit/{id}', AuditController::class, 'show', ['id']],

    // USERS
    ['GET',  '/users',                      UserController::class, 'index'],
    ['GET',  '/users/create',               UserController::class, 'create'],
    ['POST', '/users/store',                UserController::class, 'store'],
    ['GET',  '/users/{id}',                 UserController::class, 'show',          ['id']],
    ['GET',  '/users/{id}/edit',            UserController::class, 'edit',          ['id']],
    ['POST', '/users/{id}/update',          UserController::class, 'update',        ['id']],
    ['POST', '/users/{id}/delete',          UserController::class, 'delete',        ['id']],
    ['POST', '/users/{id}/reset-password',  UserController::class, 'resetPassword', ['id']],
    ['GET',  '/profile',                    UserController::class, 'profile'],
    ['POST', '/profile/update',             UserController::class, 'updateProfile'],
    ['GET',  '/profile/change-password',    UserController::class, 'showChangePassword'],
    ['POST', '/profile/change-password',    UserController::class, 'changePassword'],
    ['POST', '/users/{id}/disable',  UserController::class, 'disable',  ['id']],
    ['POST', '/users/{id}/activate', UserController::class, 'activate', ['id']],

    // SETTINGS
    ['GET',  '/settings',        SettingsController::class, 'index'],
    ['POST', '/settings/update', SettingsController::class, 'update'],
    ['POST', '/settings/logo',   SettingsController::class, 'uploadLogo'],
    ['POST', '/settings/favicon',SettingsController::class, 'uploadFavicon'],
    ['POST', '/settings/backup', SettingsController::class, 'backup'],

    // ACCESS CONTROL
    ['GET',  '/access-control',                            AccessControlController::class, 'index'],
    ['GET',  '/access-control/user/{id}',                  AccessControlController::class, 'getUserPermissions', ['id']],
    ['GET',  '/access-control/role/{id}/permissions',      AccessControlController::class, 'getRolePermissions', ['id']],
    ['POST', '/access-control/update',                     AccessControlController::class, 'update'],
    ['POST', '/access-control/save-permission',            AccessControlController::class, 'savePermission'],
    ['POST', '/access-control/delete-permission',          AccessControlController::class, 'deletePermission'],
    ['POST', '/access-control/save-role',                  AccessControlController::class, 'saveRole'],
    ['POST', '/access-control/delete-role',                AccessControlController::class, 'deleteRole'],

    // TRASH
    ['GET',  '/trash',                     TrashController::class, 'index'],
    ['POST', '/trash/{type}/{id}/restore', TrashController::class, 'restore', ['type', 'id']],
    ['POST', '/trash/{type}/{id}/destroy', TrashController::class, 'destroy', ['type', 'id']],

    // GROUPS
    ['GET',  '/groups',              GroupController::class, 'index'],
    ['GET',  '/groups/create',       GroupController::class, 'create'],
    ['POST', '/groups/store',        GroupController::class, 'store'],
    ['GET',  '/groups/list',         GroupController::class, 'list'], // If exists
    ['GET',  '/groups/{id}',         GroupController::class, 'show',   ['id']],
    ['GET',  '/groups/{id}/edit',    GroupController::class, 'edit',   ['id']],
    ['POST', '/groups/{id}/update',  GroupController::class, 'update', ['id']],
    ['POST', '/groups/{id}/delete',  GroupController::class, 'delete', ['id']],
    ['POST', '/groups/{id}/add-member',    GroupController::class, 'addMember',    ['id']],
    ['POST', '/groups/{id}/remove-member', GroupController::class, 'removeMember', ['id']],

    // LOAN PRODUCTS
    ['GET',  '/loan-products',             LoanProductController::class, 'index'],
    ['GET',  '/loan-products/create',      LoanProductController::class, 'create'],
    ['POST', '/loan-products/store',       LoanProductController::class, 'store'],
    ['GET',  '/loan-products/{id}/edit',   LoanProductController::class, 'edit',         ['id']],
    ['POST', '/loan-products/{id}/update', LoanProductController::class, 'update',       ['id']],
    ['POST', '/loan-products/{id}/toggle', LoanProductController::class, 'toggleStatus', ['id']],

    // IMPORT / EXPORT
    ['GET',  '/import',                 ImportController::class, 'index'],
    ['GET',  '/import/template/{type}', ImportController::class, 'downloadTemplate', ['type']],
    ['POST', '/import/members',         ImportController::class, 'importMembers'],

    // CRON JOBS
    ['GET', '/cron/run-daily', CronController::class, 'runDaily'],

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

        if ($routeMethod !== $method) continue;

        $regex = '@^' . preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern) . '$@';
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