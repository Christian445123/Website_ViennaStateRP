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
