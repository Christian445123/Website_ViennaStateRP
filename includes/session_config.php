<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Session-Konfiguration & Auth-Middleware
// ============================================================

if (defined('_VSRP_SESSION_LOADED')) return;
define('_VSRP_SESSION_LOADED', true);

define('SESSION_TIMEOUT', 3600); // 1 Stunde Inaktivität

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,  // 30 Tage
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Session-Timeout prüfen ────────────────────────────────────────────────────
if (isset($_SESSION['user_id'])) {
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ── CSRF-Helfer ───────────────────────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ── Auth-Helfer ───────────────────────────────────────────────────────────────

/** Aktuell eingeloggten User aus Session holen */
function current_user(): ?array
{
    return $_SESSION['discord_user'] ?? null;
}

/** Gibt true zurück wenn jemand eingeloggt ist */
function is_logged_in(): bool
{
    return isset($_SESSION['discord_user']['id']);
}

/** Leitet nicht eingeloggte User zu login.php um */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/login.php');
        exit;
    }
}

/** Leitet non-Admin User um */
function require_admin(): void
{
    require_login();
    if (!has_permission('admin')) {
        http_response_code(403);
        die('Zugriff verweigert.');
    }
}
