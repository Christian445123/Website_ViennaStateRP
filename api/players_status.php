<?php
// ============================================================
//  ViennaStateRP – Server-Status API Endpoint
//  GET /api/players_status.php
//
//  Öffentlich:  online, players, max_players, jobs
//  Eingeloggt:  + details (map, gametype, resources, etc.)
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivem_api.php';
require_once __DIR__ . '/../includes/session_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$loggedIn  = is_logged_in();
$cacheKey  = $loggedIn ? 'vsrp_status_full' : 'vsrp_status_pub';
$cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
$cacheTtl  = $loggedIn ? 15 : 30;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    echo file_get_contents($cacheFile);
    exit;
}

$status   = FiveMAPI::getStatus();
$jobStats = FiveMAPI::getJobStats();

$response = [
    'online'      => $status['online'],
    'players'     => $status['players'],
    'max_players' => $status['max_players'],
    'hostname'    => $status['hostname'],
    'jobs'        => $jobStats,
];

if ($loggedIn && $status['online']) {
    $full    = FiveMAPI::getFullInfo();
    $bridgePlayers = FiveMAPI::getBridgePlayers();

    $response['details'] = $full;   // alle verfügbaren Felder, ohne Hardcodierung

    // Spielerliste mit Jobs (nur für eingeloggte Nutzer)
    $response['player_list'] = $bridgePlayers;
}

$json = json_encode($response, JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile, $json);
echo $json;
