<?php
declare(strict_types=1);
// Cache-Leeren (nur Admins, nur per POST – wird aus dem Admin-Panel bzw. manuell ausgelöst)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: text/plain; charset=utf-8');

if (!is_logged_in() || !has_capability('settings.edit')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Nur per POST aufrufbar.';
    exit;
}

$files = glob(sys_get_temp_dir() . '/vsrp_*.json') ?: [];
$count = 0;
foreach ($files as $f) {
    if (@unlink($f)) {
        $count++;
    }
}
echo "Deleted $count cache files.";
