<?php
// ============================================================
//  ViennaStateRP – Server-Status API Endpoint
//  GET /api/players_status.php
//
//  Öffentlich:  online, players, max_players, dev_server_online + eine kleine,
//               unkritische Auswahl an Detailfeldern (Map, Gametype, Sprache, …)
//  Berechtigt:  alle Details (Server-Interna, Ressourcen, Convars)
// ============================================================
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivem_api.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Die Details stammen vom Gameserver (Hostname, Tags, Convars, Ressourcennamen …) und werden
 * von den Seiten per innerHTML eingefügt → alle Strings hier HTML-escapen, sonst ist
 * Stored-XSS über Servereinstellungen möglich.
 */
function status_escape(mixed $v): mixed
{
    if (is_string($v)) return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (is_array($v))  return array_map('status_escape', $v);
    return $v;
}

// Einheitlicher Cache (vollständige Daten) – gefiltert wird erst bei der Ausgabe
$cacheFile = sys_get_temp_dir() . '/vsrp_status_full.json';
$cacheTtl  = 30;

$response = null;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $response = json_decode((string) file_get_contents($cacheFile), true);
}

if (!is_array($response)) {
    $status = FiveMAPI::getStatus();

    $response = [
        'online'             => $status['online'],
        'players'            => $status['players'],
        'max_players'        => $status['max_players'],
        'hostname'           => status_escape($status['hostname'] ?? ''),
        'dev_server_online'  => dev_server_is_online(),
    ];

    if ($status['online']) {
        $response['details'] = status_escape(FiveMAPI::getFullInfo());
        // owner_profile / Banner / Icon landen in href/src → nur http(s) zulassen
        foreach (['owner_profile', 'banner_connecting', 'banner_detail', 'icon_url'] as $urlKey) {
            if (!empty($response['details'][$urlKey]) && !preg_match('#^https?://#i', html_entity_decode((string) $response['details'][$urlKey]))) {
                $response['details'][$urlKey] = '';
            }
        }
    }

    file_put_contents($cacheFile, json_encode($response, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Nur berechtigte Admins sehen die vollständigen Server-Interna
$canSeeAll = is_logged_in() && (has_capability('settings.view') || has_capability('players_admin.view'));
if (!$canSeeAll && isset($response['details'])) {
    $public = ['map', 'gametype', 'locale', 'resource_count', 'upvote_power', 'burst_power'];
    $response['details'] = array_intersect_key($response['details'], array_flip($public));
    unset($response['hostname']);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
