<?php
// ============================================================
//  ViennaStateRP – Server-Status AJAX Endpoint
//  Aufruf: /api/server_status.php
//  Gibt JSON zurück – wird vom Dashboard per JS abgefragt
// ============================================================
declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivem_api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Einfaches Caching: max 1x pro 15 Sekunden zur FiveM API
$cacheFile = sys_get_temp_dir() . '/vsrp_server_status.json';
$cacheTtl  = 15;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    echo file_get_contents($cacheFile);
    exit;
}

$status     = FiveMAPI::getStatus();
$serverInfo = FiveMAPI::getServerInfo();

$response = array_merge($status, [
    'framework'      => $serverInfo['framework']      ?? null,
    'resource_count' => $serverInfo['resource_count'] ?? ($status['resource_count'] ?? 0),
    'convars'        => $serverInfo['convars']         ?? [],
]);

$json = json_encode($response, JSON_UNESCAPED_UNICODE);

file_put_contents($cacheFile, $json);
echo $json;
