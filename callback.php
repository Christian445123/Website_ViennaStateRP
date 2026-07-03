<?php
// ============================================================
//  ViennaStateRP – Discord OAuth2 Callback
// ============================================================
declare(strict_types=1);

// Fehler nicht anzeigen, nur loggen
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';

// Rate-Limit nur wenn DB verfügbar
if (isset($pdo)) {
    require_once __DIR__ . '/includes/rate_limit.php';
    require_once __DIR__ . '/includes/logger.php';
    if (!checkRateLimit('discord_callback', 10, 300)) {
        rateLimitExceeded();
    }
}

$siteUrl = defined('SITE_URL') ? SITE_URL : '';

// ── State validieren (CSRF) ───────────────────────────────────────────────────
if (empty($_GET['state']) || empty($_SESSION['oauth_state'])
    || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
    error_log('[Callback] Ungültiger OAuth State');
    header('Location: ' . $siteUrl . '/index.php?error=' . urlencode('Ungültige Anfrage'));
    exit;
}
unset($_SESSION['oauth_state']);

if (isset($_GET['error'])) {
    header('Location: ' . $siteUrl . '/index.php?error=' . urlencode($_GET['error_description'] ?? 'Zugriff verweigert'));
    exit;
}

if (empty($_GET['code'])) {
    header('Location: ' . $siteUrl . '/index.php?error=' . urlencode('Kein Code empfangen'));
    exit;
}

// ── Token-Austausch ───────────────────────────────────────────────────────────
$token = _discord_post('https://discord.com/api/oauth2/token', [
    'client_id'     => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => DISCORD_REDIRECT_URI,
]);

if (empty($token['access_token'])) {
    error_log('[Callback] Token-Austausch fehlgeschlagen: ' . json_encode($token));
    header('Location: ' . $siteUrl . '/index.php?error=' . urlencode('Login fehlgeschlagen – bitte nochmals versuchen'));
    exit;
}

// ── Discord User holen ────────────────────────────────────────────────────────
$discordUser = _discord_get('https://discord.com/api/users/@me', $token['access_token']);

if (empty($discordUser['id'])) {
    error_log('[Callback] Discord User-Abfrage fehlgeschlagen');
    header('Location: ' . $siteUrl . '/index.php?error=' . urlencode('Benutzerdaten konnten nicht abgerufen werden'));
    exit;
}

// ── DB: User speichern (optional – kein Crash wenn DB fehlt) ─────────────────
$dbRow = null;
if (isset($pdo)) {
    try {
        $pdo->prepare(
            'INSERT INTO users (discord_id, username, global_name, avatar, email, last_login)
             VALUES (:did, :u, :g, :a, :e, NOW())
             ON DUPLICATE KEY UPDATE
               username=VALUES(username), global_name=VALUES(global_name),
               avatar=VALUES(avatar), email=VALUES(email), last_login=NOW()'
        )->execute([
            ':did' => $discordUser['id'],
            ':u'   => $discordUser['username'],
            ':g'   => $discordUser['global_name'] ?? $discordUser['username'],
            ':a'   => $discordUser['avatar'],
            ':e'   => $discordUser['email'] ?? null,
        ]);

        $s = $pdo->prepare('SELECT id, role, is_whitelisted, is_banned, ban_reason FROM users WHERE discord_id = ?');
        $s->execute([$discordUser['id']]);
        $dbRow = $s->fetch() ?: null;

        // Gebannten User blockieren
        if ($dbRow && $dbRow['is_banned']) {
            if (function_exists('log_event')) {
                log_event('login_banned', "Gebannter User {$discordUser['username']}", (int)$dbRow['id']);
            }
            session_unset();
            session_destroy();
            header('Location: ' . $siteUrl . '/index.php?error=' . urlencode('Du wurdest gebannt: ' . ($dbRow['ban_reason'] ?? 'Kein Grund')));
            exit;
        }

        // Login-Log
        $pdo->prepare('INSERT INTO login_log (user_id, ip_hash, user_agent) VALUES (?,?,?)')
            ->execute([
                $dbRow['id'] ?? 0,
                hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'x'),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);

        if (function_exists('log_event')) {
            log_event('discord_login', "{$discordUser['username']} eingeloggt", (int)($dbRow['id'] ?? 0));
        }

    } catch (\Throwable $e) {
        error_log('[Callback] DB-Fehler: ' . $e->getMessage());
        $dbRow = null;
    }
}

// ── Session aufbauen ──────────────────────────────────────────────────────────
session_regenerate_id(true);

$_SESSION['discord_user'] = [
    'id'            => $discordUser['id'],
    'username'      => $discordUser['username'],
    'global_name'   => $discordUser['global_name'] ?? $discordUser['username'],
    'avatar'        => $discordUser['avatar'],
    'email'         => $discordUser['email'] ?? '',
    'db_id'         => (int) ($dbRow['id'] ?? 0),
    'role'          => $dbRow['role'] ?? 'player',
    'is_whitelisted'=> (bool) ($dbRow['is_whitelisted'] ?? false),
];
$_SESSION['last_activity'] = time();

header('Location: ' . $siteUrl . '/dashboard.php');
exit;

// Rate-Limit: max 10 Callbacks / 5 Min pro IP
if (!checkRateLimit('discord_callback', 10, 300)) {
    rateLimitExceeded();
}

// State validieren (CSRF-Schutz)
if (empty($_GET['state']) || empty($_SESSION['oauth_state'])
    || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
    log_event('security_alert', 'Ungültiger OAuth State', 0, ['state' => $_GET['state'] ?? '']);
    http_response_code(403);
    die('Ungültige Anfrage.');
}
unset($_SESSION['oauth_state']);

if (isset($_GET['error'])) {
    header('Location: ' . SITE_URL . '/index.php?error=' . urlencode($_GET['error_description'] ?? 'Zugriff verweigert'));
    exit;
}

if (empty($_GET['code'])) {
    header('Location: ' . SITE_URL . '/index.php?error=Kein+Code+empfangen');
    exit;
}

// Code gegen Token tauschen
$token = discord_exchange_code($_GET['code']);

if (empty($token['access_token'])) {
    log_event('security_alert', 'Token-Austausch fehlgeschlagen', 0, ['response' => json_encode($token)]);
    header('Location: ' . SITE_URL . '/index.php?error=Login+fehlgeschlagen');
    exit;
}

// Discord-User holen
$discordUser = discord_get_user($token['access_token']);

if (empty($discordUser['id'])) {
    log_event('security_alert', 'Discord-User-Abfrage fehlgeschlagen', 0);
    header('Location: ' . SITE_URL . '/index.php?error=Benutzerdaten+konnten+nicht+abgerufen+werden');
    exit;
}

// ── User in DB speichern / aktualisieren ──────────────────────────────────────
try {
    $pdo->prepare(
        'INSERT INTO users (discord_id, username, global_name, avatar, email, last_login)
         VALUES (:discord_id, :username, :global_name, :avatar, :email, NOW())
         ON DUPLICATE KEY UPDATE
           username    = VALUES(username),
           global_name = VALUES(global_name),
           avatar      = VALUES(avatar),
           email       = VALUES(email),
           last_login  = NOW()'
    )->execute([
        ':discord_id'  => $discordUser['id'],
        ':username'    => $discordUser['username'],
        ':global_name' => $discordUser['global_name'] ?? $discordUser['username'],
        ':avatar'      => $discordUser['avatar'],
        ':email'       => $discordUser['email'] ?? null,
    ]);

    $s = $pdo->prepare('SELECT id, role, is_whitelisted, is_banned, ban_reason FROM users WHERE discord_id = ?');
    $s->execute([$discordUser['id']]);
    $dbRow = $s->fetch();

} catch (\PDOException $e) {
    error_log('[Callback] DB-Fehler: ' . $e->getMessage());
    $dbRow = null;
}

// Gebannte User blockieren
if ($dbRow && $dbRow['is_banned']) {
    log_event('login_banned', "Gebannter User {$discordUser['username']} hat versucht sich einzuloggen", (int) ($dbRow['id'] ?? 0));
    header('Location: ' . SITE_URL . '/index.php?error=' . urlencode('Du wurdest gebannt: ' . ($dbRow['ban_reason'] ?? 'Kein Grund')));
    exit;
}

// ── Session aufbauen ──────────────────────────────────────────────────────────
session_regenerate_id(true);

$_SESSION['discord_user'] = [
    'id'            => $discordUser['id'],
    'username'      => $discordUser['username'],
    'global_name'   => $discordUser['global_name'] ?? $discordUser['username'],
    'avatar'        => $discordUser['avatar'],
    'email'         => $discordUser['email'] ?? '',
    'db_id'         => (int) ($dbRow['id'] ?? 0),
    'role'          => $dbRow['role'] ?? 'player',
    'is_whitelisted'=> (bool) ($dbRow['is_whitelisted'] ?? false),
];
$_SESSION['last_activity'] = time();

log_event('discord_login',
    "{$discordUser['username']} hat sich eingeloggt",
    (int) ($dbRow['id'] ?? 0),
    ['discord_id' => $discordUser['id']]
);

if ($dbRow) {
    try {
        $pdo->prepare('INSERT INTO login_log (user_id, ip_hash, user_agent) VALUES (?, ?, ?)')->execute([
            $dbRow['id'],
            hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (\Throwable) {}
}

header('Location: ' . SITE_URL . '/dashboard.php');
exit;
