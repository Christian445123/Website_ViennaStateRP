<?php
// ============================================================
//  ViennaStateRP – FiveM Server API  v4.0
//
//  PUBLIC ENDPOINTS (kein Auth):
//    /info.json     – Server-Name, Ressourcen, Vars, Icon
//    /dynamic.json  – Clients, Slots, Hostname, Map, Gametype
//    /players.json  – Spielerliste (ID, Ping, Identifiers)
//
//  BRIDGE ENDPOINT (HMAC-Auth):
//    whitelist_add / whitelist_remove / whitelist_check
//    kick / ban / unban / ban_check
//    broadcast
//    job_stats         – alle Jobs dynamisch gezählt
//    get_players       – alle Online-Spieler mit Job, Endpoint etc.
//    get_player_detail – einzelner Spieler (discord_id|server_id)
//    get_server_info   – Convars, Ressourcen, Framework
// ============================================================
declare(strict_types=1);

if (defined('_VSRP_FIVEM_LOADED')) return;
define('_VSRP_FIVEM_LOADED', true);

// API-URL aus FIVEM_IP/PORT aufbauen (konfigurierbar via .env)
define('FIVEM_API_URL', env('FIVEM_API_URL',
    'http://' . (defined('FIVEM_IP') ? FIVEM_IP : 'fivem.viennastaterpx.at')
    . ':' . (defined('FIVEM_PORT') ? FIVEM_PORT : '30120')
));
define('FIVEM_BRIDGE_SECRET', env('FIVEM_BRIDGE_SECRET', ''));

class FiveMAPI
{
    // =========================================================================
    //  ÖFFENTLICHE ENDPOINTS
    // =========================================================================

    /**
     * /info.json
     * Enthält: server, hostname, resources[], vars{}, version,
     *          connectEndPoints[], upvotePower, burstPower,
     *          enhancedHostSupport, icon (base64), licenseKeyToken
     */
    public static function getInfo(): ?array
    {
        return self::fetchPublic('/info.json');
    }

    /**
     * /dynamic.json
     * Enthält: clients, sv_maxclients, hostname, gametype, mapname, iv
     */
    public static function getDynamic(): ?array
    {
        return self::fetchPublic('/dynamic.json');
    }

    /**
     * /players.json
     * Enthält pro Spieler: id, name, identifiers[], ping, endpoint
     */
    public static function getPlayers(): array
    {
        return self::fetchPublic('/players.json') ?? [];
    }

    // =========================================================================
    //  KOMBINIERTE AUSWERTUNGEN
    // =========================================================================

    /**
     * Kompakter Status – für Statusanzeigen & API-Responses.
     * Kombiniert /dynamic.json + /players.json
     *
     * @return array{
     *   online: bool,
     *   players: int,
     *   max_players: int,
     *   hostname: string,
     *   player_count: int,
     * }
     */
    public static function getStatus(): array
    {
        $dynamic = self::getDynamic();
        $players = self::getPlayers();

        if ($dynamic === null) {
            return [
                'online'      => false,
                'players'     => 0,
                'max_players' => 0,
                'hostname'    => defined('FIVEM_IP') ? FIVEM_IP : '',
                'player_count'=> 0,
            ];
        }

        $count = (int) ($dynamic['clients'] ?? count($players));

        return [
            'online'      => true,
            'players'     => $count,
            'max_players' => (int) ($dynamic['sv_maxclients'] ?? 64),
            'hostname'    => $dynamic['hostname'] ?? (defined('FIVEM_IP') ? FIVEM_IP : ''),
            'player_count'=> $count,
        ];
    }

    /**
     * Vollständige Server-Informationen für eingeloggte Nutzer.
     * Kombiniert alle drei öffentlichen Endpoints.
     *
     * @return array{
     *   online: bool,
     *   players: int,
     *   max_players: int,
     *   hostname: string,
     *   server_name: string,
     *   description: string,
     *   map: string,
     *   gametype: string,
     *   resource_count: int,
     *   resources: string[],
     *   locale: string,
     *   tags: string,
     *   upvote_power: int,
     *   burst_power: int,
     *   server_version: string,
     *   connect_endpoints: string[],
     *   license_token: string,
     *   icon_url: string,
     * }
     */
    public static function getFullInfo(): array
    {
        $dynamic = self::getDynamic();
        $info    = self::getInfo();
        $players = self::getPlayers();

        if ($dynamic === null && $info === null) {
            return ['online' => false];
        }

        $vars      = $info['vars']      ?? [];
        $resources = $info['resources'] ?? [];

        return [
            'online'           => ($dynamic !== null),
            'players'          => (int) ($dynamic['clients']       ?? count($players)),
            'max_players'      => (int) ($dynamic['sv_maxclients'] ?? (int) ($vars['sv_maxClients'] ?? 64)),
            'hostname'         => $dynamic['hostname']             ?? ($vars['sv_projectName'] ?? ''),
            'server_name'      => $vars['sv_projectName']          ?? ($info['server']         ?? ''),
            'description'      => $vars['sv_projectDesc']          ?? '',
            'map'              => $dynamic['mapname']              ?? ($vars['mapname']          ?? ''),
            'gametype'         => $dynamic['gametype']             ?? ($vars['gametype']         ?? ''),
            'resource_count'   => is_array($resources) ? count($resources) : 0,
            'resources'        => is_array($resources) ? $resources : [],
            'locale'           => $vars['locale']                  ?? '',
            'tags'             => $vars['tags']                    ?? '',
            'upvote_power'     => (int) ($info['upvotePower']      ?? 0),
            'burst_power'      => (int) ($info['burstPower']       ?? 0),
            'server_version'   => $info['server']                  ?? '',
            'connect_endpoints'=> $info['connectEndPoints']        ?? [],
            'license_token'    => substr((string) ($info['licenseKeyToken'] ?? ''), 0, 8) . '...',
            'icon_url'         => !empty($info['icon'])
                ? 'data:image/png;base64,' . $info['icon']
                : '',
            'enhanced_host'    => (bool) ($info['enhancedHostSupport'] ?? false),
            'support_status'   => $info['support_status'] ?? '',
        ];
    }

    /**
     * Job-Statistiken über die Bridge.
     * Vollständig dynamisch – alle Jobs die aktuell von Online-Spielern
     * gehalten werden, ohne Hardcodierung.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public static function getJobStats(): array
    {
        $data = self::bridgeQuery('job_stats');
        if (!isset($data['jobs']) || !is_array($data['jobs'])) {
            return [];
        }

        $jobs = [];
        foreach ($data['jobs'] as $key => $count) {
            $jobs[] = [
                'key'   => $key,
                'label' => ucfirst(str_replace(['_', '-'], ' ', $key)),
                'count' => (int) $count,
            ];
        }
        // Sortiere absteigend nach Spieleranzahl
        usort($jobs, fn($a, $b) => $b['count'] <=> $a['count']);
        return $jobs;
    }

    /**
     * Vollständige Spielerliste über die Bridge.
     * Enthält: id, name, ping, endpoint, last_msg, identifiers[], job{name,grade,grade_name,label}
     * Fallback auf öffentliches /players.json wenn Bridge nicht verfügbar.
     */
    public static function getBridgePlayers(): array
    {
        $data = self::bridgeQuery('get_players');
        if (isset($data['players']) && is_array($data['players'])) {
            return $data['players'];
        }
        // Fallback: öffentlicher Endpoint (kein Job / Endpoint verfügbar)
        $raw = self::getPlayers();
        $result = [];
        foreach ($raw as $p) {
            $result[] = [
                'id'          => (int)    ($p['id']          ?? 0),
                'name'        => (string) ($p['name']        ?? ''),
                'ping'        => (int)    ($p['ping']        ?? 0),
                'endpoint'    => (string) ($p['endpoint']    ?? ''),
                'last_msg'    => null,
                'identifiers' => (array)  ($p['identifiers'] ?? []),
                'job'         => null,
            ];
        }
        return $result;
    }

    /**
     * Vollständige Details eines einzelnen Spielers über die Bridge.
     * Identifizierung per discord_id ODER server_id.
     */
    public static function getPlayerDetail(string $discordId = '', int $serverId = 0): ?array
    {
        $payload = [];
        if ($discordId !== '') $payload['discord_id'] = $discordId;
        if ($serverId   >  0) $payload['server_id']   = $serverId;
        if (empty($payload))  return null;

        $data = self::bridgeQuery('get_player_detail', $payload);
        return (isset($data['ok']) && $data['ok']) ? $data : null;
    }

    /**
     * Server-Informationen: Convars, alle Ressourcen, Framework-Typ.
     */
    public static function getServerInfo(): ?array
    {
        return self::bridgeQuery('get_server_info');
    }

    /**
     * Ban-Status eines Spielers prüfen.
     * @return array{banned: bool, reason: string}|null
     */
    public static function banCheck(string $discordId): ?array
    {
        $data = self::bridgeQuery('ban_check', ['discord_id' => $discordId]);
        if (!$data) return null;
        return [
            'banned' => (bool)   ($data['banned'] ?? false),
            'reason' => (string) ($data['reason'] ?? ''),
        ];
    }

    /**
     * Spieler entbannen.
     */
    public static function unbanPlayer(string $discordId): bool
    {
        return self::bridgeRequest('unban', ['discord_id' => $discordId]);
    }

    // =========================================================================
    //  BRIDGE COMMANDS  (Auth via HMAC-SHA256 + Secret im Body)
    // =========================================================================

    /** Spieler zur Whitelist hinzufügen */
    public static function whitelistAdd(string $discordId, string $playerName = ''): bool
    {
        return self::bridgeRequest('whitelist_add', [
            'discord_id'  => $discordId,
            'player_name' => $playerName,
        ]);
    }

    /** Spieler von der Whitelist entfernen */
    public static function whitelistRemove(string $discordId): bool
    {
        return self::bridgeRequest('whitelist_remove', ['discord_id' => $discordId]);
    }

    /** Whitelist-Status eines Spielers prüfen */
    public static function whitelistCheck(string $discordId): bool
    {
        $r = self::bridgeQuery('whitelist_check', ['discord_id' => $discordId]);
        return ($r['whitelisted'] ?? false) === true;
    }

    /** Spieler kicken (falls online) */
    public static function kickPlayer(string $discordId, string $reason = 'Kein Grund'): bool
    {
        return self::bridgeRequest('kick', [
            'discord_id' => $discordId,
            'reason'     => $reason,
        ]);
    }

    /** Spieler bannen */
    public static function banPlayer(string $discordId, string $reason = 'Gebannt'): bool
    {
        return self::bridgeRequest('ban', [
            'discord_id' => $discordId,
            'reason'     => $reason,
        ]);
    }

    /** Server-weite Nachricht an alle Spieler */
    public static function broadcast(string $message, string $color = '#e8b86d'): bool
    {
        return self::bridgeRequest('broadcast', [
            'message' => $message,
            'color'   => $color,
        ]);
    }

    // =========================================================================
    //  PRIVATE HELFER
    // =========================================================================

    /** HTTP-GET auf öffentlichen FiveM-Endpoint */
    private static function fetchPublic(string $path): ?array
    {
        $url = rtrim(FIVEM_API_URL, '/') . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'ViennaStateRP-Website/2.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
        ]);
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($result === false || $code !== 200) {
            error_log('[FiveMAPI] fetchPublic ' . $path . ' failed: HTTP ' . $code . ' ' . $error);
            return null;
        }
        return json_decode((string) $result, true) ?? null;
    }

    /** Bridge-URL aufbauen */
    private static function bridgeUrl(): string
    {
        return rtrim(FIVEM_API_URL, '/') . '/vsrp_bridge/vsrp';
    }

    /** Bridge-Request der eine bool-Antwort erwartet */
    private static function bridgeRequest(string $action, array $payload): bool
    {
        $r = self::bridgeQuery($action, $payload);
        return ($r['ok'] ?? false) === true;
    }

    /** Bridge-Request der Daten zurückgibt */
    private static function bridgeQuery(string $action, array $payload = []): ?array
    {
        if (!FIVEM_BRIDGE_SECRET) return null;

        $payload['action']    = $action;
        $payload['timestamp'] = time();
        $payload['secret']    = FIVEM_BRIDGE_SECRET;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) return null;

        $signature = hash_hmac('sha256', $body, FIVEM_BRIDGE_SECRET);

        $ch = curl_init(self::bridgeUrl());
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
        $error  = curl_error($ch);
        curl_close($ch);

        if ($result === false || $code !== 200) {
            error_log('[FiveMAPI] bridgeQuery ' . $action . ' failed: HTTP ' . $code . ' ' . $error);
            return null;
        }
        return json_decode((string) $result, true) ?? null;
    }
}
