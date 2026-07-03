<?php
// ============================================================
//  ViennaStateRP – Whitelist-Bewerbung verarbeiten
// ============================================================
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json; charset=utf-8');

// Nur POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 'error', 'message' => 'Methode nicht erlaubt.']));
}

// Eingeloggt?
if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Nicht eingeloggt.']));
}

// Rate-Limit: max 3 Bewerbungen / 10 Min
if (!checkRateLimit('whitelist', 3, 600)) {
    rateLimitExceeded();
}

// CSRF
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    log_event('csrf_failed', 'CSRF beim Whitelist-Formular', $_SESSION['discord_user']['db_id'] ?? 0);
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Ungültige Anfrage.']));
}

$user = $_SESSION['discord_user'];
$dbId = (int) ($user['db_id'] ?? 0);

if (!$dbId) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Benutzerfehler – bitte erneut einloggen.']));
}

// Bestehende aktive Bewerbung prüfen
$existing = $pdo->prepare(
    'SELECT id, status FROM whitelist_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
);
$existing->execute([$dbId]);
$app = $existing->fetch();

if ($app && in_array($app['status'], ['pending', 'approved'])) {
    die(json_encode(['status' => 'error', 'message' => 'Du hast bereits eine aktive Bewerbung.']));
}

// Eingaben validieren
$required = ['real_name', 'char_name', 'char_story', 'motivation', 'rp_experience'];
$data = [];

foreach ($required as $field) {
    $val = trim($_POST[$field] ?? '');
    if (mb_strlen($val) < 3) {
        die(json_encode(['status' => 'error', 'message' => 'Bitte alle Felder ausfüllen (mind. 3 Zeichen).']));
    }
    $data[$field] = mb_substr(htmlspecialchars($val, ENT_QUOTES), 0, 3000);
}

$age = (int) ($_POST['age'] ?? 0);
if ($age < 14 || $age > 80) {
    die(json_encode(['status' => 'error', 'message' => 'Ungültiges Alter (14–80).']));
}

if (mb_strlen($data['char_story']) < 100) {
    die(json_encode(['status' => 'error', 'message' => 'Die Hintergrundgeschichte muss mind. 100 Zeichen haben.']));
}

// Bewerbung speichern
$pdo->prepare(
    'INSERT INTO whitelist_applications (user_id, real_name, age, char_name, char_story, motivation, rp_experience)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([$dbId, $data['real_name'], $age, $data['char_name'], $data['char_story'], $data['motivation'], $data['rp_experience']]);

log_event('whitelist_submitted',
    "Neue Whitelist-Bewerbung von {$user['global_name']} (Discord: {$user['id']})",
    $dbId,
    ['discord_tag' => $user['username']]
);

echo json_encode(['status' => 'success', 'message' => 'Deine Bewerbung wurde erfolgreich eingereicht! Wir prüfen sie so schnell wie möglich.']);
