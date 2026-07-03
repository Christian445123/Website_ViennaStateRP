<?php
// ============================================================
//  ViennaStateRP – FiveM Server API
//  Liest öffentliche FiveM-Endpoints + kommuniziert mit
//  dem vsrp_bridge Resource via signierter HTTP-Requests
// ============================================================
declare(strict_types=1);

if (defined('_VSRP_FIVEM_LOADED')) return;
define('_VSRP_FIVEM_LOADED', true);

define('FIVEM_API_URL',       env('FIVEM_API_URL',       'http://localhost:30120'));
define('FIVEM_BRIDGE_SECRET', env('FIVEM_BRIDGE_SECRET', ''));

class FiveMAPI
{
    // ── Öffentliche FiveM Endpoints (kein Auth nötig) ─────────────────────────

    /**
     * Server-Infos (Name, Tags, Beschreibung, etc.)
     */
    public static function getInfo(): ?array
    {
        return self::fetchPublic('/info.json');
    }

    /**
     * Dynamische Daten: aktuelle Spielerzahl, Max-Spieler, Hostname
     */
    public static function getDynamic(): ?array
    {
        return self::fetchPublic('/dynamic.json');
    }

    /**
     * Liste aller Online-Spieler
     */
    public static function getPlayers(): array
    {
        return self::fetchPublic('/players.json') ?? [];
    }

    /**
     * Kompakter Server-Status für Dashboard
     */
    public static function getStatus(): array
    {
        $dynamic = self::getDynamic();
        $players = self::getPlayers();

        if ($dynamic === null) {
            return [
                'online'     => false,
                'players'    => 0,
                'max_players'=> 0,
                'hostname'   => FIVEM_IP,
                'player_list'=> [],
            ];
        }

        return [
            'online'      => true,
            'players'     => (int) ($dynamic['clients'] ?? count($players)),
            'max_players' => (int) ($dynamic['sv_maxclients'] ?? 64),
            'hostname'    => $dynamic['hostname'] ?? FIVEM_IP,
            'player_list' => array_map(fn($p) => [
                'id'   => $p['id']   ?? 0,
                'name' => htmlspecialchars($p['name'] ?? 'Unknown', ENT_QUOTES),
                'ping' => (int) ($p['ping'] ?? 0),
            ], array_slice($players, 0, 50)), // max 50 anzeigen
        ];
    }

    // ── Bridge Commands (Auth via HMAC-Signature) ─────────────────────────────

    /**
     * Spieler zur Whitelist hinzufügen (Discord-ID + CFX-ID)
     */
    public static function whitelistAdd(string $discordId, string $playerName = ''): bool
    {
        return self::bridgeRequest('whitelist_add', [
            'discord_id'  => $discordId,
            'player_name' => $playerName,
        ]);
    }

    /**
     * Spieler von der Whitelist entfernen
     */
    public static function whitelistRemove(string $discordId): bool
    {
        return self::bridgeRequest('whitelist_remove', [
            'discord_id' => $discordId,
        ]);
    }

    /**
     * Spieler kicken (falls online)
     */
    public static function kickPlayer(string $discordId, string $reason = 'Kein Grund'): bool
    {
        return self::bridgeRequest('kick', [
            'discord_id' => $discordId,
            'reason'     => $reason,
        ]);
    }

    /**
     * Spieler bannen
     */
    public static function banPlayer(string $discordId, string $reason = 'Gebannt'): bool
    {
        return self::bridgeRequest('ban', [
            'discord_id' => $discordId,
            'reason'     => $reason,
        ]);
    }

    /**
     * Nachricht an alle Spieler senden
     */
    public static function broadcast(string $message, string $color = '#e8b86d'): bool
    {
        return self::bridgeRequest('broadcast', [
            'message' => $message,
            'color'   => $color,
        ]);
    }

    // ── Interne Helfer ────────────────────────────────────────────────────────

    private static function fetchPublic(string $path): ?array
    {
        $url = rtrim(FIVEM_API_URL, '/') . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'ViennaStateRP-Website/1.0',
        ]);
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $code !== 200) return null;
        return json_decode((string) $result, true) ?? null;
    }

    private static function bridgeRequest(string $action, array $payload): bool
    {
        if (!FIVEM_BRIDGE_SECRET) return false;

        $payload['action']    = $action;
        $payload['timestamp'] = time();
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // HMAC-SHA256 Signatur
        $signature = hash_hmac('sha256', $body, FIVEM_BRIDGE_SECRET);

        $url = rtrim(FIVEM_API_URL, '/') . ':' . (defined('FIVEM_BRIDGE_PORT') ? FIVEM_BRIDGE_PORT : '30121') . '/vsrp';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-VSRP-Signature: ' . $signature,
                'X-VSRP-Timestamp: ' . $payload['timestamp'],
            ],
        ]);
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $code !== 200) return false;
        $data = json_decode((string) $result, true);
        return ($data['ok'] ?? false) === true;
    }
}
