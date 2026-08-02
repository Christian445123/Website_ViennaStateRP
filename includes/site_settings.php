<?php
// ============================================================
//  ViennaStateRP – Site-Einstellungen (Admin-Toggle)
//  Gespeichert in: includes/settings.json
// ============================================================

if (defined('_VSRP_SETTINGS_LOADED')) return;
define('_VSRP_SETTINGS_LOADED', true);

define('SETTINGS_FILE', __DIR__ . '/settings.json');

/**
 * Standardwerte – was auf der öffentlichen Statusseite angezeigt wird.
 */
const SETTINGS_DEFAULTS = [
    // players.php – öffentliche Anzeige
    'show_player_count'    => true,   // Spieleranzahl
    'show_max_slots'       => true,   // Max Slots
    'show_load_percent'    => true,   // Auslastung %
    'show_jobs_police'     => true,   // Polizei
    'show_jobs_fire'       => true,   // Feuerwehr
    'show_jobs_ambulance'  => true,   // Berufsrettung
    'show_jobs_mechanic'   => true,   // Mechanik
    // index.php Hero-Stats
    'show_hero_status'     => true,   // Online/Offline Badge
    'show_hero_players'    => true,   // Spielerzahl im Hero
    // Allgemein
    'maintenance_mode'     => false,  // Wartungsmodus
    'maintenance_message'  => 'Der Server befindet sich im Wartungsmodus.',
    // Discord
    'discord_changelog_webhook' => '',  // Webhook-URL für Changelog-Ankündigungen
];

function settings_load(): array
{
    if (!file_exists(SETTINGS_FILE)) return SETTINGS_DEFAULTS;
    $json = file_get_contents(SETTINGS_FILE);
    if ($json === false) return SETTINGS_DEFAULTS;
    $data = json_decode($json, true);
    if (!is_array($data)) return SETTINGS_DEFAULTS;
    return array_merge(SETTINGS_DEFAULTS, $data);
}

function settings_save(array $data): bool
{
    // Nur bekannte Keys zulassen
    $filtered = [];
    foreach (SETTINGS_DEFAULTS as $key => $default) {
        if (isset($data[$key])) {
            $val = $data[$key];
            $filtered[$key] = is_bool($default)
                ? (bool) $val
                : (string) $val;
        }
    }
    $merged = array_merge(settings_load(), $filtered);
    return file_put_contents(SETTINGS_FILE, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function setting(string $key): mixed
{
    static $cache = null;
    if ($cache === null) $cache = settings_load();
    return $cache[$key] ?? (SETTINGS_DEFAULTS[$key] ?? null);
}
