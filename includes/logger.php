<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Logging-System
//  Doppeltes Logging: MariaDB (activity_logs) + Discord Webhook
//  Muster übernommen von GamingCommunity24/7
// ============================================================

if (defined('_VSRP_LOGGER_LOADED')) return;
define('_VSRP_LOGGER_LOADED', true);

require_once __DIR__ . '/discord_config.php';

// ── Log-Typen ─────────────────────────────────────────────────────────────────
define('VSRP_LOG_TYPES', [
    // Discord Auth
    'discord_login'         => ['color' => 0x5865f2, 'emoji' => '🎮', 'label' => 'Discord Login'],
    'discord_logout'        => ['color' => 0x64748b, 'emoji' => '🚪', 'label' => 'Logout'],
    'login_banned'          => ['color' => 0xef4444, 'emoji' => '🔨', 'label' => 'Gebannter User'],
    'login_bruteforce'      => ['color' => 0xef4444, 'emoji' => '🔨', 'label' => 'Brute-Force erkannt'],
    // Whitelist
    'whitelist_submitted'   => ['color' => 0xf59e0b, 'emoji' => '📋', 'label' => 'Whitelist-Bewerbung'],
    'whitelist_approved'    => ['color' => 0x059669, 'emoji' => '✅', 'label' => 'Whitelist genehmigt'],
    'whitelist_rejected'    => ['color' => 0xef4444, 'emoji' => '❌', 'label' => 'Whitelist abgelehnt'],
    // Admin
    'admin_login'           => ['color' => 0xef4444, 'emoji' => '🔐', 'label' => 'Admin-Login'],
    'admin_ban'             => ['color' => 0xef4444, 'emoji' => '🔨', 'label' => 'Spieler gebannt'],
    'admin_unban'           => ['color' => 0x059669, 'emoji' => '✅', 'label' => 'Spieler entbannt'],
    'admin_action'          => ['color' => 0xf59e0b, 'emoji' => '⚙️', 'label' => 'Admin-Aktion'],
    'role_assigned'         => ['color' => 0x7c3aed, 'emoji' => '🏷️', 'label' => 'Rolle zugewiesen'],
    // Sicherheit
    'csrf_failed'           => ['color' => 0xef4444, 'emoji' => '🛡️', 'label' => 'CSRF-Angriff erkannt'],
    'access_denied'         => ['color' => 0xef4444, 'emoji' => '⛔', 'label' => 'Zugriff verweigert'],
    'security_alert'        => ['color' => 0xef4444, 'emoji' => '🚨', 'label' => 'Sicherheitswarnung'],
    // System
    'error'                 => ['color' => 0xef4444, 'emoji' => '🔴', 'label' => 'Fehler'],
    'warning'               => ['color' => 0xf59e0b, 'emoji' => '⚠️', 'label' => 'Warnung'],
    'info'                  => ['color' => 0x64748b, 'emoji' => 'ℹ️',  'label' => 'Info'],
]);

/**
 * Event loggen – MariaDB + Discord Webhook.
 *
 * @param string $type    Log-Typ (Schlüssel aus VSRP_LOG_TYPES)
 * @param string $message Beschreibung
 * @param int    $userId  Interne DB-User-ID (0 = anonym)
 * @param array  $meta    Zusätzliche Metadaten
 * @param bool   $discord Discord-Notification senden
 */
function log_event(
    string $type,
    string $message,
    int    $userId  = 0,
    array  $meta    = [],
    bool   $discord = true
): void {
    global $pdo;

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
       ?? $_SERVER['HTTP_X_FORWARDED_FOR']
       ?? $_SERVER['REMOTE_ADDR']
       ?? null;
    if ($ip) $ip = trim(explode(',', $ip)[0]);
    if ($ip === '::1') $ip = '127.0.0.1';

    if (php_sapi_name() !== 'cli') {
        $meta['method'] ??= $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $meta['url']    ??= $_SERVER['REQUEST_URI']    ?? '';
    }

    // Username für Lesbarkeit
    $username = null;
    if ($userId > 0 && isset($pdo)) {
        try {
            $s = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $s->execute([$userId]);
            $username = $s->fetchColumn() ?: null;
        } catch (\Throwable $e) { /* ignorieren */ }
    }

    // DB-Log
    if (isset($pdo)) {
        try {
            $pdo->prepare(
                "INSERT INTO activity_logs (type, message, user_id, username, ip, meta, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            )->execute([
                $type,
                $message,
                $userId > 0 ? $userId : null,
                $username,
                $ip,
                !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[Logger] DB-Fehler: ' . $e->getMessage());
        }
    }

    // Discord-Log
    if ($discord && DISCORD_LOGGING_ENABLED) {
        _vsrp_send_discord($type, $message, $userId, $username, $ip, $meta);
    }
}

function _vsrp_send_discord(
    string  $type,
    string  $message,
    int     $userId,
    ?string $username,
    ?string $ip,
    array   $meta
): void {
    if (!DISCORD_WEBHOOK_URL) return;

    $info  = VSRP_LOG_TYPES[$type] ?? ['color' => 0x64748b, 'emoji' => '📋', 'label' => $type];
    $fields = [];

    if ($userId > 0 && $username) {
        $fields[] = ['name' => '👤 User', 'value' => "`{$username}` (#{$userId})", 'inline' => true];
    } else {
        $fields[] = ['name' => '👤 User', 'value' => 'Anonym', 'inline' => true];
    }
    if ($ip) {
        $fields[] = ['name' => '🌍 IP', 'value' => "`{$ip}`", 'inline' => true];
    }
    if (!empty($meta['method']) && !empty($meta['url'])) {
        $fields[] = ['name' => '🔗 Request', 'value' => "`{$meta['method']} " . mb_substr($meta['url'], 0, 200) . "`", 'inline' => false];
        unset($meta['method'], $meta['url']);
    }
    foreach ($meta as $k => $v) {
        if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        $fields[] = ['name' => ucwords(str_replace('_', ' ', $k)), 'value' => '`' . mb_substr((string)$v, 0, 200) . '`', 'inline' => true];
    }

    $payload = json_encode([
        'username' => DISCORD_BOT_NAME,
        'embeds'   => [[
            'title'       => $info['emoji'] . '  ' . $info['label'],
            'description' => mb_substr($message, 0, 4096),
            'color'       => $info['color'],
            'fields'      => $fields,
            'footer'      => ['text' => 'ViennaStateRP • ' . date('d.m.Y H:i:s')],
            'timestamp'   => date('c'),
        ]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
}
