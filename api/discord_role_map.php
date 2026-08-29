<?php
// ============================================================
//  ViennaStateRP – Discord-Rollen-Zuordnung API
//  GET    /api/discord_role_map.php         – Alle Zuordnungen laden
//  POST   /api/discord_role_map.php         – Neue Zuordnung anlegen  {discord_role_id, website_role, label}
//  DELETE /api/discord_role_map.php?id=X    – Zuordnung löschen
//
//  Legt fest, welche Discord-Rolle beim Login automatisch zu welcher Website-Rolle
//  führt (siehe discord_resolve_website_role() in includes/discord_config.php).
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/discord_config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Nur Owner/Websitenmanager – steuert die automatische Rollenvergabe
if (!is_logged_in() || !has_permission('owner')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Keine DB-Verbindung']);
    exit;
}

// websitenmanager ist bewusst nicht per Discord-Rolle vergebbar – nur manuell über die Benutzerverwaltung
$assignableRoles = array_keys(array_filter(VSRP_ROLES, fn($lvl, $role) => $role !== 'websitenmanager', ARRAY_FILTER_USE_BOTH));

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = $pdo->query("SELECT id, discord_role_id, website_role, label FROM discord_role_map ORDER BY id ASC")->fetchAll();
        echo json_encode([
            'ok'                  => true,
            'mappings'            => $rows,
            'assignable_roles'    => $assignableRoles,
            'labels'              => VSRP_ROLE_LABELS,
            'bot_configured'      => DISCORD_BOT_TOKEN !== '' && DISCORD_GUILD_ID !== '',
        ]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    $discordRoleId = trim((string)($body['discord_role_id'] ?? ''));
    $websiteRole   = (string)($body['website_role'] ?? '');
    $label         = mb_substr(trim((string)($body['label'] ?? '')), 0, 100);

    if (!preg_match('/^\d{5,25}$/', $discordRoleId)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Discord-Rollen-ID sieht ungültig aus (nur Ziffern)']);
        exit;
    }
    if (!in_array($websiteRole, $assignableRoles, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ungültige Website-Rolle']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO discord_role_map (discord_role_id, website_role, label) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE website_role = VALUES(website_role), label = VALUES(label)
        ");
        $stmt->execute([$discordRoleId, $websiteRole, $label]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Keine ID angegeben']);
        exit;
    }
    $pdo->prepare("DELETE FROM discord_role_map WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
