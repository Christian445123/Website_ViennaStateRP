<?php
// ============================================================
//  ViennaStateRP – Discord-Rollen-Sync für alle Nutzer
//  POST /api/discord_role_sync.php?offset=0&limit=15
//
//  Geht in Häppchen (Batches) durch alle Nutzer mit verknüpftem Discord-Account,
//  fragt deren aktuelle Discord-Rollen ab und stuft die Website-Rolle bei Bedarf
//  hoch (nie automatisch runter – siehe discord_resolve_website_role()).
//  In Batches statt in einem Rutsch, damit es nicht am PHP-Zeitlimit des Hostings
//  scheitert. Der Client (admin_rollen.php) ruft diesen Endpunkt wiederholt mit
//  steigendem `offset` auf, bis `done: true` zurückkommt.
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/discord_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Nur Owner/Websitenmanager – löst eine echte Rollenänderung für viele Nutzer aus
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

if (DISCORD_BOT_TOKEN === '' || DISCORD_GUILD_ID === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'DISCORD_BOT_TOKEN oder DISCORD_GUILD_ID ist nicht konfiguriert (.env)']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$batchSize = max(1, min(25, (int)($_GET['limit'] ?? 15)));
$offset    = max(0, (int)($_GET['offset'] ?? 0));

try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE discord_id IS NOT NULL AND discord_id <> ''")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT id, discord_id, username, role FROM users
        WHERE discord_id IS NOT NULL AND discord_id <> ''
        ORDER BY id ASC LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':lim', $batchSize, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $batch = $stmt->fetchAll();

    $checked  = 0;
    $upgraded = [];

    foreach ($batch as $u) {
        $checked++;
        $roleIds = discord_get_member_role_ids($u['discord_id']);
        if ($roleIds !== null) {
            $resolved = discord_resolve_website_role($roleIds);
            if ($resolved !== null && (VSRP_ROLES[$resolved] ?? 0) > (VSRP_ROLES[$u['role']] ?? 0)) {
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$resolved, $u['id']]);
                $upgraded[] = ['username' => $u['username'], 'from' => $u['role'], 'to' => $resolved];
                if (function_exists('log_event')) {
                    log_event('discord_role_sync', "Rolle via Massen-Sync auf {$resolved} gesetzt", (int)$u['id'], [
                        'from' => $u['role'], 'to' => $resolved,
                    ]);
                }
            }
        }
        // Kleine Pause zwischen den Discord-Anfragen, um dessen Rate-Limit nicht zu reizen
        usleep(300000);
    }

    $nextOffset = $offset + count($batch);
    echo json_encode([
        'ok'          => true,
        'checked'     => $checked,
        'upgraded'    => $upgraded,
        'next_offset' => $nextOffset,
        'total'       => $total,
        'done'        => $nextOffset >= $total || count($batch) < $batchSize,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
