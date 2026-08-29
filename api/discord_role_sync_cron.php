<?php
// ============================================================
//  ViennaStateRP – Automatischer Discord-Rollen-Sync (Cronjob)
//  Wird per Server-Cronjob aufgerufen, z. B. alle 5 Stunden:
//    0 */5 * * * curl -s "https://viennastaterp.at/api/discord_role_sync_cron.php?secret=DEIN_CRON_SECRET" > /dev/null 2>&1
//
//  Geht alle Mitglieder mit verknüpftem Discord-Account durch, stuft die
//  Website-Rolle bei Bedarf hoch (nie automatisch runter) und schickt danach
//  einen Sammelbericht in den DISCORD_ROLE_SYNC_WEBHOOK_URL-Kanal.
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');
@set_time_limit(0);
@ignore_user_abort(true); // läuft weiter, auch wenn der auslösende Fire-and-forget-Request bereits getrennt hat

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/discord_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json; charset=utf-8');

// Ohne gesetztes CRON_SECRET bleibt der Endpunkt geschlossen (sicherer Standard,
// da dieser Job echte Rollenänderungen auslöst).
$cronSecret = env('CRON_SECRET', '');
$provided   = $_SERVER['HTTP_X_CRON_SECRET'] ?? ($_GET['secret'] ?? '');
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

if (DISCORD_BOT_TOKEN === '' || DISCORD_GUILD_ID === '') {
    echo json_encode(['ok' => false, 'error' => 'DISCORD_BOT_TOKEN/DISCORD_GUILD_ID nicht konfiguriert']);
    exit;
}

$checked  = 0;
$upgraded = [];

try {
    $stmt = $pdo->query("
        SELECT id, discord_id, username, role FROM users
        WHERE discord_id IS NOT NULL AND discord_id <> ''
        ORDER BY id ASC
    ");
    foreach ($stmt->fetchAll() as $u) {
        $checked++;
        $roleIds = discord_get_member_role_ids($u['discord_id']);
        if ($roleIds !== null) {
            $resolved = discord_resolve_website_role($roleIds);
            if ($resolved !== null && (VSRP_ROLES[$resolved] ?? 0) > (VSRP_ROLES[$u['role']] ?? 0)) {
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$resolved, $u['id']]);
                $upgraded[] = ['username' => $u['username'], 'from' => $u['role'], 'to' => $resolved];
                if (function_exists('log_event')) {
                    // discord=false: kein Einzel-Post pro Nutzer, der Sammelbericht unten reicht
                    log_event('discord_role_sync', "Rolle via automatischem Sync auf {$resolved} gesetzt", (int) $u['id'], [
                        'from' => $u['role'], 'to' => $resolved,
                    ], false);
                }
            }
        }
        // Kleine Pause zwischen den Discord-Anfragen, um dessen Rate-Limit nicht zu reizen
        usleep(300000);
    }
} catch (\Throwable $e) {
    error_log('[DiscordRoleSyncCron] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

discord_send_role_sync_report($checked, $upgraded);

echo json_encode(['ok' => true, 'checked' => $checked, 'upgraded' => $upgraded]);
