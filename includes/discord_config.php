<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Discord OAuth2 & Webhook Konfiguration
// ============================================================

if (defined('_VSRP_DISCORD_LOADED')) return;
define('_VSRP_DISCORD_LOADED', true);

// Webhook-Logging aktiv?
define('DISCORD_LOGGING_ENABLED', env('DISCORD_LOGGING_ENABLED', 'false') === 'true');
define('DISCORD_WEBHOOK_URL',     env('DISCORD_WEBHOOK_URL', ''));
define('DISCORD_BOT_NAME',        env('DISCORD_BOT_NAME', 'ViennaStateRP'));
define('DISCORD_BOT_AVATAR',      env('DISCORD_BOT_AVATAR', ''));
// Eigener Webhook für öffentliche Changelog-Ankündigungen (z.B. #ankündigungen).
// Fällt auf den allgemeinen Webhook zurück, falls keiner separat gesetzt ist.
define('DISCORD_CHANGELOG_WEBHOOK_URL', env('DISCORD_CHANGELOG_WEBHOOK_URL', DISCORD_WEBHOOK_URL));

// Bot-Token + Server-ID für den automatischen Rollen-Sync (Discord-Rolle → Website-Rolle).
// Optional: Ist eines von beiden leer, bleibt der Sync einfach inaktiv (kein Fehler).
define('DISCORD_BOT_TOKEN', env('DISCORD_BOT_TOKEN', ''));
define('DISCORD_GUILD_ID',  env('DISCORD_GUILD_ID', ''));

// Eigener Kanal für die Sammelberichte des automatischen Discord-Rollen-Syncs
define('DISCORD_ROLE_SYNC_WEBHOOK_URL', env('DISCORD_ROLE_SYNC_WEBHOOK_URL', ''));

// ── OAuth2 Helfer ─────────────────────────────────────────────────────────────

/** Gibt die Discord OAuth2-URL zurück und setzt einen State in der Session */
function discord_auth_url(): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    return 'https://discord.com/api/oauth2/authorize?' . http_build_query([
        'client_id'     => DISCORD_CLIENT_ID,
        'redirect_uri'  => DISCORD_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'identify email guilds',
        'state'         => $state,
    ]);
}

/** Tauscht den OAuth2-Code gegen einen Access-Token */
function discord_exchange_code(string $code): array
{
    return _discord_post('https://discord.com/api/oauth2/token', [
        'client_id'     => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => DISCORD_REDIRECT_URI,
    ]);
}

/** Holt den eingeloggten Discord-User */
function discord_get_user(string $accessToken): array
{
    return _discord_get('https://discord.com/api/users/@me', $accessToken);
}

/**
 * Holt die Rollen-IDs eines Mitglieds im konfigurierten Discord-Server (via Bot-Token).
 * Gibt null zurück wenn kein Bot-Token/Guild konfiguriert ist oder die Anfrage fehlschlägt
 * (z. B. der Nutzer ist nicht mehr auf dem Server) – niemals ein Fehler, nur "kein Sync möglich".
 */
function discord_get_member_role_ids(string $discordUserId): ?array
{
    if (DISCORD_BOT_TOKEN === '' || DISCORD_GUILD_ID === '') return null;

    $ch = curl_init('https://discord.com/api/guilds/' . DISCORD_GUILD_ID . '/members/' . $discordUserId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bot ' . DISCORD_BOT_TOKEN],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("[Discord] Rollenabruf fehlgeschlagen (HTTP {$code}) für User {$discordUserId}");
        return null;
    }
    $data = json_decode((string) $result, true);
    return is_array($data['roles'] ?? null) ? $data['roles'] : null;
}

/**
 * Ermittelt anhand einer Liste von Discord-Rollen-IDs die höchste zugeordnete Website-Rolle
 * (siehe Tabelle `discord_role_map`, verwaltet über die Rollenverwaltung). Hat ein Nutzer
 * mehrere zugeordnete Discord-Rollen, gewinnt die mit der höchsten Website-Rangstufe.
 */
function discord_resolve_website_role(array $discordRoleIds): ?string
{
    global $pdo;
    if (!isset($pdo) || empty($discordRoleIds)) return null;

    try {
        $placeholders = implode(',', array_fill(0, count($discordRoleIds), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT website_role FROM discord_role_map WHERE discord_role_id IN ({$placeholders})");
        $stmt->execute(array_values($discordRoleIds));
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        return null;
    }
    if (!$matches) return null;

    usort($matches, fn($a, $b) => (VSRP_ROLES[$b] ?? 0) <=> (VSRP_ROLES[$a] ?? 0));
    return $matches[0];
}

/**
 * Schickt einen Sammelbericht eines Discord-Rollen-Sync-Laufs in den dafür konfigurierten
 * Kanal (DISCORD_ROLE_SYNC_WEBHOOK_URL). Ohne konfigurierten Webhook passiert nichts.
 *
 * @param int   $checked  Anzahl geprüfter Mitglieder (mit verknüpftem Discord-Account)
 * @param array $upgraded Liste von ['username' => ..., 'from' => Rollen-Slug, 'to' => Rollen-Slug]
 */
function discord_send_role_sync_report(int $checked, array $upgraded): void
{
    if (DISCORD_ROLE_SYNC_WEBHOOK_URL === '') return;

    $labelOf = fn(string $role): string => defined('VSRP_ROLE_LABELS') && isset(VSRP_ROLE_LABELS[$role])
        ? VSRP_ROLE_LABELS[$role] : $role;

    if ($upgraded) {
        $lines = array_map(
            fn($u) => "• **{$u['username']}**: " . $labelOf($u['from']) . ' → ' . $labelOf($u['to']),
            $upgraded
        );
        $description = implode("\n", $lines);
        if (mb_strlen($description) > 3800) {
            $description = mb_substr($description, 0, 3800) . "\n… und weitere";
        }
    } else {
        $description = 'Keine Hochstufungen nötig – alle Rollen sind bereits aktuell.';
    }

    $payload = json_encode([
        'username' => DISCORD_BOT_NAME,
        'embeds'   => [[
            'title'       => '🔄 Discord-Rollen-Sync',
            'description' => $description,
            'color'       => $upgraded ? 0x22c55e : 0x64748b,
            'fields'      => [
                ['name' => 'Überprüft',   'value' => (string) $checked,        'inline' => true],
                ['name' => 'Hochgestuft', 'value' => (string) count($upgraded), 'inline' => true],
            ],
            'footer'    => ['text' => 'ViennaStateRP • ' . date('d.m.Y H:i:s')],
            'timestamp' => date('c'),
        ]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(DISCORD_ROLE_SYNC_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/** Avatar-URL für einen Discord-User */
function discord_avatar(string $userId, ?string $avatar, int $size = 128): string
{
    if (!$avatar) return "https://cdn.discordapp.com/embed/avatars/0.png";
    return "https://cdn.discordapp.com/avatars/{$userId}/{$avatar}.png?size={$size}";
}

// ── Interne HTTP-Helfer ───────────────────────────────────────────────────────

function _discord_post(string $url, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    return json_decode((string) $result, true) ?? [];
}

function _discord_get(string $url, string $token): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    return json_decode((string) $result, true) ?? [];
}
