<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Admin-Only Spielerliste
//  GET /api/admin_players.php
//  Gibt Spielernamen + Job via FiveM HTTP-API zurück.
//  Kein direkter DB-Zugriff auf den Spielserver.
// ============================================================
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/fivem_api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !has_capability('players_admin.view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$cacheFile = sys_get_temp_dir() . '/vsrp_admin_players.json';
$cacheTtl  = 15;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    echo file_get_contents($cacheFile);
    exit;
}

// Spieler über Bridge holen (fällt auf öffentliches /players.json zurück)
$players = FiveMAPI::getBridgePlayers();

// Nur sichere Felder ausgeben – keine Identifiers/Endpoints
$safe = array_map(function (array $p): array {
    return [
        'id'   => (int)    ($p['id']   ?? 0),
        'name' => htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'job'  => isset($p['job']['label']) ? htmlspecialchars((string) $p['job']['label'], ENT_QUOTES, 'UTF-8') : null,
    ];
}, $players);

$json = json_encode(['ok' => true, 'players' => $safe, 'count' => count($safe)], JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile, $json);
echo $json;
