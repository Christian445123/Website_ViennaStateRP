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

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

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
if (isset($_SESSION['discord_user'])) {
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();

    // Rolle/Sperre bei jedem Request frisch aus der DB lesen: Degradierungen und Sperren
    // wirken sofort, nicht erst wenn die Session abläuft (Session ist sonst ein Rechte-Cache).
    global $pdo;
    $sid = (int) ($_SESSION['discord_user']['db_id'] ?? 0);
    if ($sid > 0 && isset($pdo) && $pdo instanceof PDO) {
        try {
            $st = $pdo->prepare('SELECT role, is_banned FROM users WHERE id = ? LIMIT 1');
            $st->execute([$sid]);
            $fresh = $st->fetch();
            if (!$fresh || !empty($fresh['is_banned'])) {
                session_unset();
                session_destroy();
                if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'error' => 'Session beendet']);
                } else {
                    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/index.php');
                }
                exit;
            }
            $_SESSION['discord_user']['role'] = $fresh['role'];
        } catch (\Throwable $e) {
            // Spalte/Tabelle fehlt (alte Installation) → bisherige Session-Rolle behalten
        }
    }
}

// ── Cross-Site-Schutz für alle schreibenden API-Aufrufe ───────────────────────
// Die JSON-APIs haben kein CSRF-Token. Browser senden bei fetch()/Formularen immer
// Origin bzw. Sec-Fetch-Site; fremde Seiten werden hier abgewiesen (zusätzlich zu SameSite=Lax).
function require_same_origin(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;

    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    $ok = false;
    if ($fetchSite !== '') {
        $ok = in_array($fetchSite, ['same-origin', 'none'], true);
    } else {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        $oHost  = $origin !== '' ? strtolower((string) parse_url($origin, PHP_URL_HOST)) : '';
        $allowed = [strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')))];
        if (defined('SITE_URL')) $allowed[] = strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
        $ok = $oHost !== '' && in_array($oHost, array_filter($allowed), true);
    }
    if (!$ok) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Cross-Site-Anfrage abgelehnt']);
        exit;
    }
}
if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
    require_same_origin();
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
    // db_id ist bei allen Login-Methoden (Discord + Passwort) gesetzt
    return isset($_SESSION['discord_user']) && !empty($_SESSION['discord_user']['db_id']);
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
