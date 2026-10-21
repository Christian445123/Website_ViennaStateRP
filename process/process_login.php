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
require_once __DIR__ . '/../includes/email_verification.php';

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
if (mb_strlen($username) > 254 || strlen($password) > 1024) json_out('error', 'Benutzername oder Passwort falsch.');

// Limit pro Benutzername+IP: ein Angreifer sperrt damit nur sich selbst, nicht das Opfer
// (ein reines Pro-Benutzername-Limit würde gezieltes Aussperren fremder Accounts erlauben).
if (!checkRateLimit('login_user_ip', 8, 900, $username . '|' . client_ip())) {
    rateLimitExceeded();
}
// Grobe Obergrenze pro Benutzername für verteilte Angriffe von sehr vielen IPs aus.
if (!checkRateLimit('login_user_global', 300, 3600, $username)) {
    rateLimitExceeded();
}

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

// Bei unbekanntem Benutzer trotzdem einen bcrypt-Vorgang gleicher Kosten ausführen, damit die
// Antwortzeit nicht verrät, ob der Benutzername existiert (User-Enumeration per Timing).
if (!$user) {
    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    log_event('login_failed', 'Fehlgeschlagener Login für: ' . mb_substr(preg_replace('/[\x00-\x1F`]/u', '', $username), 0, 64));
    json_out('error', 'Benutzername oder Passwort falsch.');
}

// E-Mail muss bestätigt sein (erst nach korrektem Passwort prüfen, damit nichts über fremde Konten verraten wird)
try {
    $ev = $pdo->prepare('SELECT email_verified_at FROM users WHERE id = ? LIMIT 1');
    $ev->execute([$user['id']]);
    $evRow = $ev->fetch();
    if ($evRow && $evRow['email_verified_at'] === null && email_verification_required()) {
        json_out('error', 'Bitte bestätige zuerst deine E-Mail-Adresse. Den Link findest du in deiner Mail; einen neuen kannst du unter verify_email.php anfordern.');
    }
} catch (\PDOException $e) { /* Spalte fehlt → Migration noch nicht gelaufen, nicht blockieren */ }

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
        hash('sha256', client_ip()),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
} catch (\Throwable) {}

json_out('success', 'Login erfolgreich!', SITE_URL . '/dashboard.php');
