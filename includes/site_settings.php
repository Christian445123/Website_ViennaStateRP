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
    'restart_times'        => '04:00,12:00,18:00', // Tägliche Server-Neustarts (HH:MM, kommagetrennt)
    'manual_restart_at'    => '', // Einmaliger, manuell geplanter Neustart (YYYY-MM-DDTHH:MM, leer = keiner)
    // index.php Hero-Stats
    'show_hero_status'     => true,   // Online/Offline Badge
    'show_hero_players'    => true,   // Spielerzahl im Hero
    // Allgemein
    'maintenance_mode'     => false,  // Wartungsmodus
    'maintenance_message'  => 'Der Server befindet sich im Wartungsmodus.',
    // Discord
    'discord_changelog_webhook' => '',  // Webhook-URL für Changelog-Ankündigungen
    // Google Analytics (nur nach Cookie-Consent geladen)
    'ga_enabled'          => true,             // GA global aktiv/inaktiv (Kill-Switch)
    'ga_measurement_id'   => 'G-LWR7JY7EQV',   // GA4 Measurement ID
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

/**
 * Validiert Text-Einstellungen. Werte landen in Seiten, Skripten und Webhook-Aufrufen –
 * ohne Prüfung wäre darüber Skript-Injection (z. B. über die GA-ID) oder SSRF möglich.
 * Ungültige Eingaben fallen auf den bisherigen/Standardwert zurück.
 */
function _settings_sanitize(string $key, string $val, string $default): string
{
    $val = trim($val);
    switch ($key) {
        case 'restart_times':
            $ok = $val === '' || preg_match('/^([01]\d|2[0-3]):[0-5]\d(\s*,\s*([01]\d|2[0-3]):[0-5]\d)*$/', $val);
            return $ok ? $val : $default;
        case 'manual_restart_at':
            return ($val === '' || preg_match('/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):[0-5]\d$/', $val)) ? $val : '';
        case 'ga_measurement_id':
            return ($val === '' || preg_match('/^G-[A-Z0-9]{4,20}$/', $val)) ? $val : $default;
        case 'discord_changelog_webhook':
            return ($val === '' || preg_match('#^https://(?:ptb\.|canary\.)?discord(?:app)?\.com/api/webhooks/\d+/[\w-]+$#', $val)) ? $val : '';
        case 'maintenance_message':
            return mb_substr($val, 0, 500);
        default:
            return mb_substr($val, 0, 500);
    }
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
                : _settings_sanitize($key, (string) $val, (string) $default);
        }
    }
    $merged = array_merge(settings_load(), $filtered);
    return file_put_contents(SETTINGS_FILE, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function setting(string $key): mixed
{
    static $cache = null;
    if ($cache === null) $cache = settings_load();
    return $cache[$key] ?? (SETTINGS_DEFAULTS[$key] ?? null);
}
