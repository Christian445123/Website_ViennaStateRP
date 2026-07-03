<?php
declare(strict_types=1);
// Cache-Leeren – einmalig aufrufen wenn Status falsch gecacht ist
require_once __DIR__ . '/../includes/db.php';

if (!has_permission('admin') && !isset($_GET['force'])) {
    http_response_code(403); echo 'Forbidden'; exit;
}

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: text/plain');

$files = glob(sys_get_temp_dir() . '/vsrp_*.json');
$count = 0;
foreach ($files as $f) {
    if (unlink($f)) {
        echo "Deleted: $f\n";
        $count++;
    }
}
echo "\nDeleted $count cache files.";
