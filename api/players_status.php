<?php
// ============================================================
//  ViennaStateRP – Spieler-Status AJAX Endpoint
//  Aufruf: /api/players_status.php
//  Gibt JSON zurück: Spieleranzahl + Job-Statistiken
// ============================================================
declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivem_api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Caching: max 1x pro 30 Sekunden
$cacheFile = sys_get_temp_dir() . '/vsrp_players_status.json';
$cacheTtl  = 30;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    echo file_get_contents($cacheFile);
    exit;
}

$status   = FiveMAPI::getStatus();
$jobStats = FiveMAPI::getJobStats();

$response = json_encode([
    'online'      => $status['online'],
    'players'     => $status['players'],
    'max_players' => $status['max_players'],
    'jobs'        => $jobStats,
], JSON_UNESCAPED_UNICODE);

file_put_contents($cacheFile, $response);
echo $response;
