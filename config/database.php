<?php
/**
 * AKABBO SOCIAL FUND
 * Database Configuration & Connection Manager
 *
 * Singleton PDO wrapper with WAMP-compatible defaults.
 * Override credentials via environment variables in production.
 */

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private array $queryLog = [];
    private bool $logging;

    // ── Default credentials (WAMP defaults) ──────────────────────
    private const DB_HOST    = 'localhost';
    private const DB_PORT    = '3306';
    private const DB_NAME    = 'akabbo_fund';
    private const DB_USER    = 'root';
    private const DB_PASS    = '';           // WAMP default: empty password
    private const DB_CHARSET = 'utf8mb4';

    private function __construct()
    {
        $this->logging = defined('APP_DEBUG') && APP_DEBUG;

        $host = getenv('DB_HOST') ?: self::DB_HOST;
        $port = getenv('DB_PORT') ?: self::DB_PORT;
        $name = getenv('DB_NAME') ?: self::DB_NAME;
        $user = getenv('DB_USER') ?: self::DB_USER;
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : self::DB_PASS;

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=" . self::DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO(): PDO { return $this->pdo; }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($this->logging) {
                $this->queryLog[] = [
                    'sql'      => $sql,
                    'params'   => $params,
                    'duration' => round((microtime(true) - $start) * 1000, 2) . 'ms',
                ];
            }
            return $stmt;
        } catch (PDOException $e) {
            $this->handleQueryError($e, $sql, $params);
        }
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction(): void  { $this->pdo->beginTransaction(); }
    public function commit(): void            { $this->pdo->commit(); }
    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
    public function inTransaction(): bool     { return $this->pdo->inTransaction(); }
    public function getQueryLog(): array      { return $this->queryLog; }

    private function handleConnectionError(PDOException $e): never
    {
        $debug = defined('APP_DEBUG') && APP_DEBUG;
        error_log('[AKABBO DB ERROR] Connection failed: ' . $e->getMessage());
        if ($debug) {
            // Show a helpful page instead of a blank 500
            http_response_code(500);
            echo '<!DOCTYPE html><html><head><title>Database Error – Akabbo</title>'
               . '<style>body{font-family:sans-serif;margin:40px;background:#fef2f2;color:#7f1d1d}'
               . 'pre{background:#fff;padding:16px;border-radius:8px;overflow:auto}'
               . 'h2{color:#dc2626}</style></head><body>'
               . '<h2>⚠ Database Connection Failed</h2>'
               . '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>'
               . '<p>Check your credentials in <code>config/database.php</code> and make sure MySQL is running in WAMP.</p>'
               . '<pre>DB_HOST: localhost&#10;DB_NAME: akabbo_fund&#10;DB_USER: root&#10;DB_PASS: (empty)</pre>'
               . '</body></html>';
            exit;
        }
        http_response_code(503);
        die('Database connection failed. Please contact your administrator.');
    }

    private function handleQueryError(PDOException $e, string $sql, array $params): never
    {
        $debug = defined('APP_DEBUG') && APP_DEBUG;
        error_log(sprintf('[AKABBO DB QUERY ERROR] %s | SQL: %s | Params: %s',
            $e->getMessage(), $sql, json_encode($params)));
        if ($debug) {
            throw new RuntimeException('DB Query Error: ' . $e->getMessage() . "\nSQL: " . $sql, (int)$e->getCode(), $e);
        }
        throw new RuntimeException('A database error occurred. Please try again.', 500);
    }

    private function __clone() {}
    public function __wakeup(): never { throw new RuntimeException('Cannot unserialize singleton.'); }
}
