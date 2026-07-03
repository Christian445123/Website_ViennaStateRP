<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Berechtigungen & Rollen
// ============================================================

if (defined('_VSRP_PERMS_LOADED')) return;
define('_VSRP_PERMS_LOADED', true);

/**
 * Rollen-Hierarchie (höher = mehr Rechte)
 */
const VSRP_ROLES = [
    'player'    => 0,
    'supporter' => 10,
    'moderator' => 20,
    'admin'     => 100,
];

/**
 * Gibt die Rolle des aktuellen Users zurück.
 */
function current_role(): string
{
    return $_SESSION['discord_user']['role'] ?? 'player';
}

/**
 * Prüft ob der aktuelle User eine bestimmte Rolle hat (oder höher).
 */
function has_permission(string $minRole): bool
{
    $userLevel = VSRP_ROLES[current_role()]  ?? 0;
    $minLevel  = VSRP_ROLES[$minRole]        ?? 0;
    return $userLevel >= $minLevel;
}

/**
 * Gibt ein HTML-Badge für eine Rolle zurück.
 */
function role_badge(string $role): string
{
    $map = [
        'admin'     => ['label' => 'Admin',     'class' => 'role-admin'],
        'moderator' => ['label' => 'Moderator', 'class' => 'role-mod'],
        'supporter' => ['label' => 'Supporter', 'class' => 'role-supporter'],
        'player'    => ['label' => 'Spieler',   'class' => 'role-player'],
    ];
    $info = $map[$role] ?? ['label' => ucfirst($role), 'class' => 'role-player'];
    return '<span class="role-badge ' . $info['class'] . '">' . $info['label'] . '</span>';
}
