<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';

// Bereits eingeloggt → weiter
if (is_logged_in()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Discord-Login-URL vorbereiten
$discordUrl  = discord_auth_url();
$error       = htmlspecialchars($_GET['error']   ?? '', ENT_QUOTES);
$success     = htmlspecialchars($_GET['success'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="auth-body">

<div class="auth-bg">
  <div class="auth-bg-overlay"></div>
</div>

<div class="auth-bg"><div class="auth-bg-overlay"></div></div>

<div class="auth-wrap">
  <a href="index.php" class="auth-logo"><span class="accent">Vienna</span>StateRP</a>

  <div class="auth-card">

    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:20px">⚠ <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:20px">✓ <?= $success ?></div>
    <?php endif; ?>

    <!-- Discord Login (primär) -->
    <h1 class="auth-title">Anmelden</h1>
    <p class="auth-sub">Mit deinem Discord-Account einloggen.</p>

    <a href="<?= htmlspecialchars($discordUrl) ?>" class="btn btn-discord btn-block" style="font-size:1rem;padding:13px 20px">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:20px;height:20px;flex-shrink:0"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
      Mit Discord anmelden
    </a>

    <div class="auth-divider"><span>oder mit Passwort</span></div>

    <!-- Passwort-Login -->
    <form class="auth-form" id="loginForm">
      <div class="form-group">
        <label for="username">Benutzername</label>
        <input type="text" id="username" name="username" required autocomplete="username" placeholder="DeinUsername" maxlength="64" />
      </div>
      <div class="form-group">
        <label for="password">Passwort</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••" maxlength="128" />
          <button type="button" class="pw-toggle" onclick="togglePw()" tabindex="-1">👁</button>
        </div>
      </div>
      <div id="loginMsg" class="auth-msg" style="display:none"></div>
      <button type="submit" class="btn btn-primary btn-block" id="loginBtn">Anmelden</button>
    </form>

    <p class="auth-switch" style="margin-top:16px">Noch kein Konto? <a href="register.php">Registrieren</a></p>
  </div>
</div>

<script>
function togglePw() {
  const i = document.getElementById('password');
  i.type = i.type === 'password' ? 'text' : 'password';
}
document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  const msg = document.getElementById('loginMsg');
  btn.disabled = true;
  btn.textContent = 'Anmelden...';
  msg.style.display = 'none';
  try {
    const res  = await fetch('process/process_login.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        username: document.getElementById('username').value,
        password: document.getElementById('password').value,
        csrf_token: '<?= csrf_token() ?>',
      })
    });
    const data = await res.json();
    if (data.status === 'success') {
      window.location.href = data.redirect || 'dashboard.php';
    } else {
      msg.textContent = data.message || 'Fehler beim Anmelden';
      msg.className = 'auth-msg alert alert-error';
      msg.style.display = 'block';
      btn.disabled = false;
      btn.textContent = 'Anmelden';
    }
  } catch(e) {
    msg.textContent = 'Verbindungsfehler – bitte erneut versuchen';
    msg.className = 'auth-msg alert alert-error';
    msg.style.display = 'block';
    btn.disabled = false;
    btn.textContent = 'Anmelden';
  }
});
</script>
</body>
</html>
