<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Berechtigungen & Rollen
// ============================================================

if (defined('_VSRP_PERMS_LOADED')) return;
define('_VSRP_PERMS_LOADED', true);

/**
 * Rollen-Hierarchie (höher = mehr Rechte).
 * Der interne Schlüssel ist die DB-/Session-Rolle, der Wert die Rangstufe.
 * 'websitenmanager' hat immer und uneingeschränkt Vollzugriff (siehe has_permission()).
 */
const VSRP_ROLES = [
    'player'             => 0,   // Mitglied
    'test_supporter'     => 10,
    'supporter'          => 20,
    'moderator'          => 30,
    'test_admin'         => 40,
    'admin'              => 50,  // Administrator
    'test_developer'     => 60,
    'developer'          => 70,
    'stv_teamleitung'    => 80,
    'teamleitung'        => 90,
    'stv_projektleitung' => 100,
    'projektleitung'     => 110,
    'owner'              => 120,
    'websitenmanager'    => 130, // volle Rechte, siehe has_permission()
];

/** Anzeigename je Rolle (Deutsch) */
const VSRP_ROLE_LABELS = [
    'player'             => 'Mitglied',
    'test_supporter'     => 'Test-Supporter',
    'supporter'          => 'Supporter',
    'moderator'          => 'Moderator',
    'test_admin'         => 'Test-Admin',
    'admin'              => 'Administrator',
    'test_developer'     => 'TestDeveloper',
    'developer'          => 'Developer',
    'stv_teamleitung'    => 'Stv. Teamleitung',
    'teamleitung'        => 'Teamleitung',
    'stv_projektleitung' => 'Stv. Projektleitung',
    'projektleitung'     => 'Projektleitung',
    'owner'              => 'Owner',
    'websitenmanager'    => 'Websitenmanager',
];

/** Badge-Farbe je Rolle (Hex) – von niedrig (grau) nach hoch (Gold/Rot) gestaffelt */
const VSRP_ROLE_COLORS = [
    'player'             => '#64748b',
    'test_supporter'     => '#4ade80',
    'supporter'          => '#22c55e',
    'moderator'          => '#7c3aed',
    'test_admin'         => '#f97316',
    'admin'              => '#ef4444',
    'test_developer'     => '#06b6d4',
    'developer'          => '#0ea5e9',
    'stv_teamleitung'    => '#e879f9',
    'teamleitung'        => '#d946ef',
    'stv_projektleitung' => '#fb923c',
    'projektleitung'     => '#f59e0b',
    'owner'              => '#e8b86d',
    'websitenmanager'    => '#facc15',
];

/**
 * Alle einzeln über die Rollenverwaltung (admin_rollen.php) steuerbaren Funktionen der Website.
 * Schlüssel = Capability-Key, Wert = [Label, Gruppe, Standard-Gate].
 * Das Standard-Gate greift nur, solange für eine Rolle nichts explizit konfiguriert wurde
 * (siehe has_capability()) – dadurch ändert sich am bisherigen Verhalten nichts, bevor
 * jemand aktiv etwas in der Rollenverwaltung umstellt.
 */
const VSRP_CAPABILITIES = [
    'admin_hub.view'        => ['Admin-Dashboard öffnen',                 'Allgemein',              'admin'],

    'users.view'            => ['Mitgliederliste einsehen',               'Benutzerverwaltung',     'admin'],
    'users.change_role'     => ['Rollen ändern',                          'Benutzerverwaltung',     'admin'],
    'users.ban'             => ['Mitglieder sperren / entsperren',        'Benutzerverwaltung',     'admin'],
    'users.reset_password'  => ['Passwörter zurücksetzen',                'Benutzerverwaltung',     'admin'],
    'users.verify'          => ['Verifizierung setzen',                   'Benutzerverwaltung',     'admin'],

    'settings.view'         => ['Server-Einstellungen & Live-Daten einsehen', 'Server & Sichtbarkeit', 'admin'],
    'settings.edit'         => ['Sichtbarkeit, Wartungsmodus & Neustarts bearbeiten', 'Server & Sichtbarkeit', 'admin'],
    'settings.db_config'    => ['Datenbank-Zugangsdaten bearbeiten',       'Server & Sichtbarkeit',  'admin'],

    'fraktionen.view'       => ['Fraktionen einsehen',                    'Fraktionen',             'admin'],
    'fraktionen.edit'       => ['Fraktionen anlegen/bearbeiten/löschen',  'Fraktionen',             'admin'],

    'changelog.view'        => ['Entwürfe & geplante Einträge einsehen',  'Changelog',              'admin'],
    'changelog.edit'        => ['Changelog-Einträge verwalten',           'Changelog',              'admin'],

    'deploy.run'            => ['Website-Deployment ausführen (git pull)', 'Server & Sichtbarkeit',  'owner'],

    'fivem_diag.view'       => ['FiveM-Diagnose ausführen',               'FiveM-Diagnose',         'admin'],

    'db.view'               => ['Datenbank-Tabellen einsehen',            'Datenbank-Browser',      'admin'],
    'db.query'              => ['Eigene SQL-Abfragen ausführen',          'Datenbank-Browser',      'admin'],

    'players_admin.view'    => ['Live-Spielerliste einsehen',             'Server-Status',          'admin'],

    'strafenkatalog.view'   => ['Strafenkatalog einsehen',                'Strafenkatalog',         'supporter'],
    'strafenkatalog.edit'   => ['Strafenkatalog bearbeiten',              'Strafenkatalog',         'admin'],

    'teamregelwerk.view'    => ['Teamregelwerk einsehen',                 'Teamregelwerk',          'supporter'],
];

/**
 * Gibt die Rolle des aktuellen Users zurück.
 */
function current_role(): string
{
    return $_SESSION['discord_user']['role'] ?? 'player';
}

/**
 * Gibt alle Rollen als [slug => Label] zurück, in Hierarchie-Reihenfolge (niedrig → hoch).
 */
function role_options(): array
{
    return VSRP_ROLE_LABELS;
}

/**
 * Sucht eine über die Website konfigurierte Sonderberechtigung für eine Rolle+Gate-Kombination.
 * Gate = entweder ein Rollen-Slug (has_permission()) oder ein Capability-Key aus VSRP_CAPABILITIES (has_capability()).
 * Gibt true/false zurück wenn explizit konfiguriert, sonst null (→ Standard-Hierarchie greift).
 */
function role_gate_override(string $role, string $gate): ?bool
{
    global $pdo;
    static $cache = [];

    if (!isset($pdo)) return null;
    if (isset($cache[$role][$gate])) return $cache[$role][$gate];

    try {
        $stmt = $pdo->prepare("SELECT enabled FROM role_permissions WHERE role = ? AND gate = ? LIMIT 1");
        $stmt->execute([$role, $gate]);
        $row = $stmt->fetch();
        $value = ($row === false) ? null : (bool)$row['enabled'];
    } catch (\Throwable $e) {
        $value = null; // Tabelle existiert evtl. noch nicht – Hierarchie als Fallback nutzen
    }

    $cache[$role][$gate] = $value;
    return $value;
}

/**
 * Prüft ob der aktuelle User eine bestimmte Rolle hat (oder höher).
 * 'websitenmanager' hat immer Vollzugriff, unabhängig von jeder Konfiguration.
 * Für alle anderen Rollen kann das Ergebnis über die Rollenverwaltung (admin_rollen.php)
 * pro Rolle explizit übersteuert werden – ist nichts konfiguriert, gilt die Hierarchie.
 */
function has_permission(string $minRole): bool
{
    $role = current_role();
    if ($role === 'websitenmanager') return true;

    $override = role_gate_override($role, $minRole);
    if ($override !== null) return $override;

    $userLevel = VSRP_ROLES[$role]    ?? 0;
    $minLevel  = VSRP_ROLES[$minRole] ?? 0;
    return $userLevel >= $minLevel;
}

/**
 * Prüft eine einzelne, über die Rollenverwaltung konfigurierbare Funktion (siehe VSRP_CAPABILITIES).
 * 'websitenmanager' hat immer Zugriff. Ist für die aktuelle Rolle nichts explizit konfiguriert,
 * greift das Standard-Gate der Capability (has_permission-Fallback) – Verhalten bleibt also
 * unverändert, bis jemand die Funktion aktiv in der Rollenverwaltung umstellt.
 */
function has_capability(string $key): bool
{
    $role = current_role();
    if ($role === 'websitenmanager') return true;

    $override = role_gate_override($role, $key);
    if ($override !== null) return $override;

    $default = VSRP_CAPABILITIES[$key][2] ?? 'admin';
    return has_permission($default);
}

/**
 * Gibt ein HTML-Badge für eine Rolle zurück.
 */
function role_badge(string $role): string
{
    $label = VSRP_ROLE_LABELS[$role] ?? ucfirst($role);
    $color = VSRP_ROLE_COLORS[$role] ?? '#94a3b8';
    return '<span class="role-badge" style="background:' . $color . '1f;color:' . $color
        . ';border:1px solid ' . $color . '4d">' . htmlspecialchars($label) . '</span>';
}
