<?php
// ============================================================
//  ViennaStateRP – Server-Status-Snapshot speichern
//  Wird per Cronjob aufgerufen: */5 * * * * curl .../api/log_status.php
//  Optional: Bearer-Token via CRON_SECRET in .env absichern
// ============================================================
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivem_api.php';

// Schutz: nur mit gueltigem Secret aufrufbar (optionaler Cron-Key)
$cronSecret = env('CRON_SECRET', '');
if ($cronSecret !== '') {
    $provided = $_SERVER['HTTP_X_CRON_SECRET'] ?? ($_GET['secret'] ?? '');
    if (!hash_equals($cronSecret, $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
}

if (!isset($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'DB unavailable']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$info    = FiveMAPI::getFullInfo();
$dynamic = FiveMAPI::getDynamic();

$pdo->prepare(
    'INSERT INTO server_status_log
       (online, players, max_players, map, gametype, resource_count, upvote_power)
     VALUES (?,?,?,?,?,?,?)'
)->execute([
    (int)  ($info['online']         ?? 0),
    (int)  ($info['players']        ?? 0),
    (int)  ($info['max_players']    ?? 0),
    substr((string) ($info['map']          ?? ''), 0, 100),
    substr((string) ($info['gametype']     ?? ''), 0, 100),
    (int)  ($info['resource_count'] ?? 0),
    (int)  ($info['upvote_power']   ?? 0),
]);

// Alte Eintraege loeschen (nur letzte 30 Tage behalten)
$pdo->exec("DELETE FROM server_status_log WHERE snapshot_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

echo json_encode(['ok' => true, 'online' => $info['online'], 'players' => $info['players']]);
