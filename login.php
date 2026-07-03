<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

header('Location: ' . discord_auth_url());
exit;
