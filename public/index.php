<?php
/**
 * AKABBO SOCIAL FUND
 * Public Entry Point (Front Controller)
 *
 * All HTTP requests are routed through this file.
 */

// ── Bootstrap ────────────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ── Security: HTTPS redirect (production only) ───────────────────
\App\Helpers\Security::enforceHttps();

// ── Security headers (PHP-based, no mod_headers needed) ──────────
// Only set if headers not already sent (guard for edge cases)
if (!headers_sent()) {
    \App\Helpers\Security::setSecurityHeaders();
}

// ── Route the request ────────────────────────────────────────────
require_once __DIR__ . '/../routes/web.php';
