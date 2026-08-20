<?php
// ============================================================
//  ViennaStateRP – Server-Status API Endpoint
//  GET /api/players_status.php
//
//  Öffentlich:  online, players, max_players, dev_server_online
//  Eingeloggt:  + details (map, gametype, resources, etc.)
// ============================================================
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivem_api.php';
require_once __DIR__ . '/../includes/session_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Einheitlicher Cache – alle Besucher bekommen dieselben Daten
$cacheFile = sys_get_temp_dir() . '/vsrp_status_full.json';
$cacheTtl  = 30;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    echo file_get_contents($cacheFile);
    exit;
}

$status = FiveMAPI::getStatus();

$response = [
    'online'             => $status['online'],
    'players'            => $status['players'],
    'max_players'        => $status['max_players'],
    'hostname'           => $status['hostname'],
    'dev_server_online'  => dev_server_is_online(),
];

if ($status['online']) {
    $response['details'] = FiveMAPI::getFullInfo();
}

$json = json_encode($response, JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile, $json);
echo $json;
