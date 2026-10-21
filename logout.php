<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/logger.php';

// Logout nur per POST + CSRF-Token: sonst kann jede fremde Seite (<img src="logout.php">)
// Besucher ausloggen. Ein GET zeigt deshalb nur eine Bestätigung.
$home = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $userId = (int) ($_SESSION['discord_user']['db_id'] ?? 0);
        if ($userId > 0) {
            log_event('discord_logout', 'Benutzer hat sich ausgeloggt', $userId);
        }
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }
    header('Location: ' . $home);
    exit;
}

if (!is_logged_in()) {
    header('Location: ' . $home);
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>Abmelden – ViennaStateRP</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
</head>
<body class="auth-body">
<div class="auth-bg"><div class="auth-bg-overlay"></div></div>
<div class="auth-wrap">
  <div class="auth-card">
    <h1 class="auth-title">Abmelden?</h1>
    <p class="auth-sub">Möchtest du dich wirklich abmelden?</p>
    <form method="POST" action="logout.php" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" />
      <button type="submit" class="btn btn-primary btn-block">Ja, abmelden</button>
    </form>
    <p class="auth-switch" style="margin-top:16px"><a href="dashboard.php">Abbrechen</a></p>
  </div>
</div>
</body>
</html>
