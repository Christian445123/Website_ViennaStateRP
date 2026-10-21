<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Deployment per Klick (Admin-Panel)
//  GET  /api/deploy.php   – Stand + ausstehende Commits
//  POST /api/deploy.php   – git pull --ff-only ausführen   (Header X-CSRF-Token)
// ============================================================
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/deploy.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !has_capability('deploy.run')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}
if (!deploy_exec_available()) {
    http_response_code(501);
    echo json_encode(['ok' => false, 'error' => 'exec() ist auf diesem Server deaktiviert – Deployment per SSH (./deploy.sh) nutzen.']);
    exit;
}

$me = (int) (current_user()['db_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true] + deploy_status());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Ungültiges CSRF-Token – Seite neu laden.']);
        exit;
    }
    // Nur ein Deployment gleichzeitig
    $lock = fopen(sys_get_temp_dir() . '/vsrp_deploy.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Es läuft bereits ein Deployment.']);
        exit;
    }
    [$code, $out] = deploy_pull();
    flock($lock, LOCK_UN);

    log_event('admin_action', 'Deployment (git pull) ' . ($code === 0 ? 'erfolgreich' : 'fehlgeschlagen'), $me, ['exit' => $code]);
    if ($code !== 0) error_log('[Deploy] git pull Exit ' . $code . ': ' . implode(' | ', array_slice($out, -5)));

    http_response_code($code === 0 ? 200 : 500);
    echo json_encode(['ok' => $code === 0, 'output' => implode("\n", array_slice($out, -30))] + deploy_status());
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
