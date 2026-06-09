<?php
namespace App\Helpers;

/**
 * AKABBO SOCIAL FUND
 * Security Helper
 *
 * CSRF, XSS prevention, input sanitization, password hashing,
 * upload validation, and HTTP security headers.
 */
class Security
{
    /**
     * Generate (or retrieve) the CSRF token for the current session.
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Ensure session is started before touching $_SESSION
            return '';
        }
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Validate CSRF token from POST body or X-CSRF-Token header (AJAX).
     */
    public static function validateCsrfToken(): bool
    {
        $token  = $_POST[CSRF_TOKEN_NAME]
               ?? $_SERVER['HTTP_X_CSRF_TOKEN']
               ?? '';
        $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';

        return !empty($token) && !empty($stored) && hash_equals($stored, $token);
    }

    /**
     * Render a hidden CSRF input field for HTML forms.
     */
    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME
             . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Sanitize a string value to prevent XSS attacks.
     */
    public static function sanitize(mixed $value, bool $strict = true): string
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        $value = trim($value);
        if ($strict) {
            $value = strip_tags($value);
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize an array of inputs recursively.
     */
    public static function sanitizeArray(array $data, array $skipKeys = []): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $skipKeys, true)) {
                $clean[$key] = $value;
                continue;
            }
            $clean[$key] = is_array($value)
                ? self::sanitizeArray($value, $skipKeys)
                : self::sanitize($value);
        }
        return $clean;
    }

    /**
     * Validate and sanitize an email address.
     */
    public static function sanitizeEmail(string $email): string|false
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    /**
     * Hash a password with bcrypt.
     */
    public static function hashPassword(string $plaintext): string
    {
        return password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }

    /**
     * Verify plaintext against a stored hash.
     */
    public static function verifyPassword(string $plaintext, string $hash): bool
    {
        return password_verify($plaintext, $hash);
    }

    /**
     * Check whether the stored hash needs rehashing.
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }

    /**
     * Redirect HTTP → HTTPS in production only.
     */
    public static function enforceHttps(): void
    {
        if (!defined('APP_ENV') || APP_ENV !== 'production') {
            return;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        if (!$isHttps) {
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $url, true, 301);
            exit;
        }
    }

    /**
     * Set security HTTP headers using PHP (no mod_headers needed).
     *
     * Safe for WAMP / development — no HSTS, no nonce-based CSP.
     */
    public static function setSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // HSTS only on production HTTPS
        if (defined('APP_ENV') && APP_ENV === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Relaxed CSP — allows Tailwind CDN, Font Awesome CDN, Google Fonts
        header("Content-Security-Policy: "
            . "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
            . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; "
            . "img-src 'self' data: blob:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self';"
        );
    }

    /**
     * Generate a cryptographically secure random token.
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Get real client IP (for logging only — headers can be spoofed).
     */
    public static function getClientIp(): string
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP);
                if ($ip) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Validate an uploaded file (MIME type, size).
     *
     * @return array{valid: bool, error: string, mime_type?: string}
     */
    public static function validateUpload(
        array $file,
        array $allowedTypes = ALLOWED_DOC_TYPES,
        int   $maxSize      = MAX_UPLOAD_SIZE
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $msgs = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension.',
            ];
            $code  = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            return ['valid' => false, 'error' => $msgs[$code] ?? "Upload error code {$code}"];
        }

        if (($file['size'] ?? 0) > $maxSize) {
            return ['valid' => false, 'error' => 'File exceeds the ' . round($maxSize / 1048576, 1) . ' MB limit.'];
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes, true)) {
            return ['valid' => false, 'error' => "File type '{$mimeType}' is not allowed."];
        }

        return ['valid' => true, 'error' => '', 'mime_type' => $mimeType];
    }
}
