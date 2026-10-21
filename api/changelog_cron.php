<?php
// ============================================================
//  ViennaStateRP – Fällige Changelog-Discord-Ankündigungen versenden
//  Wird per Cronjob aufgerufen: */5 * * * * curl .../api/changelog_cron.php?secret=...
//  Sichert geplante (visible_at) Einträge, die veröffentlicht wurden während
//  niemand im Admin-Panel war. Optional per CRON_SECRET aus .env abgesichert.
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/changelog.php';

header('Content-Type: application/json; charset=utf-8');

$cronSecret = env('CRON_SECRET', '');
// Ohne gesetztes CRON_SECRET bleibt der Endpunkt geschlossen (sonst könnte jeder Besucher
// Discord-Posts bzw. DB-Schreibvorgänge auslösen).
$provided = (string) ($_SERVER['HTTP_X_CRON_SECRET'] ?? ($_GET['secret'] ?? ''));
if ($cronSecret === '' || !hash_equals($cronSecret, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'DB unavailable']);
    exit;
}

$sent = changelog_process_due_notifications($pdo);
echo json_encode(['ok' => true, 'sent' => $sent]);
