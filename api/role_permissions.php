<?php
// ============================================================
//  ViennaStateRP – Rollenrechte-API
//  GET  /api/role_permissions.php   – Aktuelle Matrix laden
//  POST /api/role_permissions.php   – Matrix speichern { entries: [{role,gate,enabled}, ...] }
//
//  Steuert pro Rolle, welche Funktionen der Website (VSRP_CAPABILITIES) sie
//  nutzen darf. 'websitenmanager' ist ausgenommen – hat immer uneingeschränkten
//  Zugriff, unabhängig von jeder Konfiguration hier.
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Nur Owner/Websitenmanager dürfen die Rollenrechte einsehen oder ändern
if (!is_logged_in() || !has_permission('owner')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Keine DB-Verbindung']);
    exit;
}

$validGates = array_keys(VSRP_CAPABILITIES);
$configurableRoles = array_keys(array_filter(VSRP_ROLES, fn($lvl, $role) => $role !== 'websitenmanager', ARRAY_FILTER_USE_BOTH));

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = $pdo->query("SELECT role, gate, enabled FROM role_permissions")->fetchAll();
        $matrix = [];
        foreach ($rows as $r) {
            if (!in_array($r['gate'], $validGates, true)) continue; // veraltete/unbekannte Gates ignorieren
            $matrix[$r['role']][$r['gate']] = (bool)$r['enabled'];
        }
        // Für alle noch nicht explizit konfigurierten Kombinationen den aktuellen
        // Hierarchie-Fallback eintragen, damit die Matrix immer den echten Ist-Zustand zeigt.
        foreach ($configurableRoles as $role) {
            foreach (VSRP_CAPABILITIES as $key => [$label, $group, $defaultGate]) {
                if (isset($matrix[$role][$key])) continue;
                $matrix[$role][$key] = (VSRP_ROLES[$role] ?? 0) >= (VSRP_ROLES[$defaultGate] ?? 0);
            }
        }

        // Gruppen für die UI aufbereiten: [group => [key => [label, defaultGate]]]
        $groups = [];
        foreach (VSRP_CAPABILITIES as $key => [$label, $group, $defaultGate]) {
            $groups[$group][] = ['key' => $key, 'label' => $label, 'default_gate' => $defaultGate];
        }

        echo json_encode([
            'ok'       => true,
            'roles'    => $configurableRoles,
            'labels'   => VSRP_ROLE_LABELS,
            'levels'   => VSRP_ROLES,
            'groups'   => $groups,
            'matrix'   => $matrix,
        ]);
    } catch (\Throwable $e) {
        http_response_code(500);
        error_log('[API] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Interner Fehler']);
    }
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $entries = $body['entries'] ?? null;
    if (!is_array($entries)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO role_permissions (role, gate, enabled) VALUES (:role, :gate, :enabled)
        ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)
    ");

    $saved = 0;
    foreach ($entries as $entry) {
        $role = (string)($entry['role'] ?? '');
        $gate = (string)($entry['gate'] ?? '');
        if (!in_array($role, $configurableRoles, true) || !in_array($gate, $validGates, true)) {
            continue;
        }
        $stmt->execute([
            ':role'    => $role,
            ':gate'    => $gate,
            ':enabled' => !empty($entry['enabled']) ? 1 : 0,
        ]);
        $saved++;
    }

    echo json_encode(['ok' => true, 'saved' => $saved]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
