<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/logger.php';

$userId = (int) ($_SESSION['discord_user']['db_id'] ?? 0);
if ($userId > 0) {
    log_event('discord_logout', 'Benutzer hat sich ausgeloggt', $userId);
}

session_unset();
session_destroy();
header('Location: ' . SITE_URL . '/index.php');
exit;
