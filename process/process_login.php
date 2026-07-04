<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Passwort-Login verarbeiten
// ============================================================

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(string $status, string $message, string $redirect = ''): void {
    ob_end_clean();
    echo json_encode(compact('status', 'message', 'redirect'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out('error', 'Methode nicht erlaubt.');
if (!isset($pdo)) json_out('error', 'Datenbankfehler. Bitte versuche es später.');

// CSRF
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    log_event('csrf_failed', 'CSRF beim Login');
    json_out('error', 'Ungültige Anfrage. Bitte Seite neu laden.');
}

// Rate-Limit: 10 Versuche / 5 Minuten
if (!checkRateLimit('login', 10, 300)) {
    rateLimitExceeded();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) json_out('error', 'Bitte Benutzername und Passwort eingeben.');

// User suchen (username ODER email)
// role-Spalte nur selektieren wenn sie existiert (defensiv gegen fehlende Migration)
try {
    $loginCols = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    $loginRoleCol = $loginCols ? ', role' : '';
} catch (\Throwable $e) { $loginRoleCol = ''; }

$stmt = $pdo->prepare(
    "SELECT id, username, global_name, password_hash, avatar{$loginRoleCol}, is_whitelisted, is_banned, ban_reason
     FROM users
     WHERE (username = ? OR email = ?) AND password_hash IS NOT NULL
     LIMIT 1"
);
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    log_event('login_failed', "Fehlgeschlagener Login für: {$username}");
    json_out('error', 'Benutzername oder Passwort falsch.');
}

if ($user['is_banned']) {
    log_event('login_banned', "Gebannter User versuchte Login: {$user['username']}", (int)$user['id']);
    json_out('error', 'Dein Account wurde gesperrt: ' . ($user['ban_reason'] ?? 'Kein Grund'));
}

// Passwort-Rehash wenn nötig
if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $user['id']]);
}

// last_login updaten
$pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

// Session
session_regenerate_id(true);
$_SESSION['discord_user'] = [
    'id'            => null,  // kein Discord verknüpft evtl.
    'username'      => $user['username'],
    'global_name'   => $user['global_name'] ?: $user['username'],
    'avatar'        => $user['avatar'],
    'email'         => '',
    'db_id'         => (int) $user['id'],
    'role'          => $user['role'],
    'is_whitelisted'=> (bool) $user['is_whitelisted'],
];
$_SESSION['last_activity'] = time();

// Login-Log
log_event('discord_login', "{$user['username']} (Passwort-Login)", (int)$user['id']);
try {
    $pdo->prepare('INSERT INTO login_log (user_id, ip_hash, user_agent) VALUES (?,?,?)')->execute([
        $user['id'],
        hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
} catch (\Throwable) {}

json_out('success', 'Login erfolgreich!', SITE_URL . '/dashboard.php');
