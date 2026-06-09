<?php
/**
 * AKABBO SOCIAL FUND
 * Application Configuration
 */

define('APP_NAME', 'Akabbo Social Fund');
define('APP_VERSION', '1.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');

// ── Auto-detect APP_URL for WAMP / subdirectory installs ─────────
if (getenv('APP_URL')) {
    define('APP_URL', rtrim(getenv('APP_URL'), '/'));
} else {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Derive base path from SCRIPT_NAME (e.g. /akabbo/public/index.php → /akabbo)
    $script   = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $basePath = str_replace('/public/index.php', '', $script);
    $basePath = rtrim($basePath, '/');
    define('APP_URL', $scheme . '://' . $host . $basePath);
}

define('APP_KEY', getenv('APP_KEY') ?: 'akabbo-secret-key-change-in-production-32chars');

// ── Timezone ─────────────────────────────────────────────────────
date_default_timezone_set('Africa/Kampala');

// ── Directory constants (Windows-safe using DIRECTORY_SEPARATOR) ─
define('ROOT_PATH',    dirname(__DIR__));
define('APP_PATH',     ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('CONFIG_PATH',  ROOT_PATH . DIRECTORY_SEPARATOR . 'config');
define('PUBLIC_PATH',  ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
define('VIEWS_PATH',   ROOT_PATH . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
define('LAYOUTS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'layouts');
define('COMPS_PATH',   ROOT_PATH . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'components');
define('LOGS_PATH',    STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs');
define('UPLOADS_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('BACKUP_PATH',  STORAGE_PATH . DIRECTORY_SEPARATOR . 'backups');

// ── Session ───────────────────────────────────────────────────────
define('SESSION_NAME',    'akabbo_session');
define('SESSION_TIMEOUT', 1800);
define('SESSION_SECURE',  false); // set true when using HTTPS
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// ── Security ──────────────────────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900);
define('BCRYPT_COST',        12);
define('CSRF_TOKEN_NAME',    'csrf_token');
define('CSRF_TOKEN_LENGTH',  32);

// ── Pagination ────────────────────────────────────────────────────
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE',     200);

// ── Upload limits ─────────────────────────────────────────────────
define('MAX_UPLOAD_SIZE',    5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_DOC_TYPES',   ['application/pdf', 'image/jpeg', 'image/png']);

// ── Currency ──────────────────────────────────────────────────────
define('DEFAULT_CURRENCY',        'UGX');
define('DEFAULT_CURRENCY_SYMBOL', 'USh');
define('DECIMAL_PLACES',          2);

// ── Financial defaults ────────────────────────────────────────────
define('MIN_SAVINGS',           10000);
define('DEFAULT_INTEREST_RATE', 10.00);
define('MAX_LOAN_MULTIPLIER',   3);
define('LOAN_PROCESSING_FEE',   1.00);
define('LATE_PAYMENT_PENALTY',  5.00);
define('GRACE_PERIOD_DAYS',     5);

// ── Error reporting ───────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_PATH . DIRECTORY_SEPARATOR . 'php_errors.log');
}

// ── PSR-4 style autoloader ────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $namespaceMap = [
        'App\\Controllers\\'  => APP_PATH . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR,
        'App\\Models\\'       => APP_PATH . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR,
        'App\\Services\\'     => APP_PATH . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR,
        'App\\Repositories\\' => APP_PATH . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR,
        'App\\Middleware\\'   => APP_PATH . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR,
        'App\\Helpers\\'      => APP_PATH . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR,
        'App\\Libraries\\'    => APP_PATH . DIRECTORY_SEPARATOR . 'Libraries' . DIRECTORY_SEPARATOR,
    ];

    foreach ($namespaceMap as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $relative = str_replace($namespace, '', $class);
            $file     = $path . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});
