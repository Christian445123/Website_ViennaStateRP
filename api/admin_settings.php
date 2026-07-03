<?php
// ============================================================
//  ViennaStateRP – Admin Settings API
//  POST /api/admin_settings.php  – Einstellungen speichern
//  GET  /api/admin_settings.php  – Einstellungen laden
// ============================================================
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/site_settings.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !has_permission('admin')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'settings' => settings_load()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    $ok = settings_save($body);
    echo json_encode(['ok' => $ok]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
