<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Registrierung verarbeiten
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
    json_out('error', 'Ungültige Anfrage. Bitte Seite neu laden.');
}

// Rate-Limit: 5 Registrierungen / 10 Min pro IP
if (!checkRateLimit('register', 5, 600)) {
    rateLimitExceeded();
}

$username = trim($_POST['username'] ?? '');
$email    = trim(strtolower($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

// Validierung
if (mb_strlen($username) < 3 || mb_strlen($username) > 64) {
    json_out('error', 'Benutzername muss 3–64 Zeichen lang sein.');
}
if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
    json_out('error', 'Benutzername darf nur Buchstaben, Zahlen, _ und - enthalten.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out('error', 'Ungültige E-Mail Adresse.');
}
if (mb_strlen($password) < 8) {
    json_out('error', 'Passwort muss mind. 8 Zeichen lang sein.');
}
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    json_out('error', 'Passwort braucht mind. 1 Großbuchstaben und 1 Zahl.');
}

// Duplikat prüfen
$check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
$check->execute([$username, $email]);
if ($check->fetch()) {
    json_out('error', 'Benutzername oder E-Mail bereits vergeben.');
}

// Account anlegen
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Spalten prüfen die evtl. noch nicht existieren
$colCache = sys_get_temp_dir() . '/vsrp_cols_users.json';
$cols = file_exists($colCache) ? (json_decode(file_get_contents($colCache), true) ?? []) : [];
if (empty($cols)) {
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[] = $c['Field'];
        }
        file_put_contents($colCache, json_encode($cols));
    } catch (\Throwable $e) { /* ignore */ }
}
$hasCol = fn($c) => in_array($c, $cols, true);

$insertCols = ['username', 'global_name', 'email', 'password_hash', 'discord_id', 'last_login'];
$insertVals = [$username, $username, $email, $hash, null, date('Y-m-d H:i:s')];
$insertPlch = ['?', '?', '?', '?', 'NULL', '?'];

if ($hasCol('is_verified')) { $insertCols[] = 'is_verified'; $insertVals[] = 1; $insertPlch[] = '?'; }

$sql = 'INSERT INTO users (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertPlch) . ')';
$pdo->prepare($sql)->execute($insertVals);

$newId = (int) $pdo->lastInsertId();

log_event('discord_login', "Neuer Account registriert: {$username}", $newId, ['email' => $email]);

json_out('success', 'Account erfolgreich erstellt! Du kannst dich jetzt anmelden.', SITE_URL . '/login.php');
