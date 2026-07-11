<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/logger.php';

require_login();

$user = current_user();

// Discord-Verknüpfungs-URL (für Passwort-User ohne Discord)
$linkDiscordUrl = empty($user['id']) ? discord_auth_url() : null;

function avatarUrl(array $user): string {
    return discord_avatar($user['id'], $user['avatar'] ?? null, 256);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="dashboard-body">

<nav class="navbar scrolled" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php">Startseite</a></li>
      <li><a href="players.php">Server Status</a></li>
      <?php if (has_permission('admin')): ?>
      <li><a href="admin.php">⚙️ Admin</a></li>
      <?php endif; ?>
    </ul>
    <div class="nav-actions">
      <a href="logout.php" class="btn btn-outline">Abmelden</a>
    </div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
  </div>
</nav>

<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<main class="dashboard-main">
  <div class="container">

    <!-- Profile Header -->
    <div class="profile-header glass">
      <div class="profile-avatar-wrap">
        <img src="<?= avatarUrl($user) ?>" alt="Avatar" class="profile-avatar" />
        <div class="profile-status online"></div>
      </div>
      <div class="profile-info">
        <h1><?= htmlspecialchars($user['global_name']) ?></h1>
        <p class="profile-tag">@<?= htmlspecialchars($user['username']) ?>
          <?php if ($user['discriminator'] && $user['discriminator'] !== '0'): ?>
            #<?= htmlspecialchars($user['discriminator']) ?>
          <?php endif; ?>
        </p>
        <p class="profile-id">Discord ID: <code><?= htmlspecialchars($user['id']) ?></code></p>
      </div>
      <div class="profile-badge">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36"><path fill="currentColor" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord verifiziert
      </div>
    </div>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">



      <!-- Discord verknüpfen (nur wenn kein Discord verknüpft) -->
      <?php if ($linkDiscordUrl): ?>
      <div class="dash-card glass">
        <h3 class="dash-card-title">🔗 Discord verknüpfen</h3>
        <p style="color:var(--text-main);font-size:.9rem;margin-bottom:16px;">
          Verknüpfe deinen Discord Account für schnelleres Einloggen und Zugang zu Server-Events.
        </p>
        <a href="<?= htmlspecialchars($linkDiscordUrl) ?>" class="qa-btn qa-discord">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36"><path fill="currentColor" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
          Discord verknüpfen
        </a>
      </div>
      <?php endif; ?>

      <div class="dash-card glass">
        <h3 class="dash-card-title">🎮 Schnellzugriff</h3>
        <div class="quick-actions">
          <a href="<?= htmlspecialchars(FIVEM_CONNECT_LINK) ?>" class="qa-btn qa-primary">
            <span>▶</span> Server beitreten
          </a>
          <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="qa-btn qa-discord">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36"><path fill="currentColor" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
            Discord öffnen
          </a>
        </div>
      </div>

      <!-- Server Status (Live) -->
      <div class="dash-card glass dash-wide" id="serverStatusCard">
        <h3 class="dash-card-title">📡 Server Status <span class="status-dot" id="statusDot"></span></h3>
        <div id="serverStatusBody" class="server-status-grid">
          <div class="status-loading">Lade Server-Status…</div>
        </div>
      </div>

      <!-- Announcements placeholder -->
      <div class="dash-card glass dash-wide">
        <h3 class="dash-card-title">📢 Ankündigungen</h3>
        <div class="announcement-list">
          <div class="announcement">
            <span class="ann-date"><?= date('d.m.Y') ?></span>
            <div>
              <strong>Willkommen auf ViennaStateRP!</strong>
              <p>Du bist jetzt eingeloggt. Viel Spaß beim Roleplay!</p>
            </div>
          </div>
          <div class="announcement">
            <span class="ann-date">01.07.2026</span>
            <div>
              <strong>Update 2.4 – Neue Features</strong>
              <p>Neues Housing-System, überarbeitetes Wirtschaftssystem und über 50 neue Fahrzeuge wurden hinzugefügt.</p>
            </div>
          </div>
          <div class="announcement">
            <span class="ann-date">25.06.2026</span>
            <div>
              <strong>Community Event: Street Races</strong>
              <p>Dieses Wochenende finden wieder unsere beliebten Street Races statt. Preise im Wert von 500.000€ warten!</p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<script src="assets/js/main.js"></script>
<script>
// ── Live Server Status ────────────────────────────────────────────────────────
async function loadServerStatus() {
  const body = document.getElementById('serverStatusBody');
  const dot  = document.getElementById('statusDot');
  if (!body) return;

  try {
    const res  = await fetch('api/server_status.php');
    const data = await res.json();

    if (!data.online) {
      dot.className  = 'status-dot offline';
      body.innerHTML = '<div class="status-offline">⚫ Server ist offline</div>';
      return;
    }

    dot.className = 'status-dot online';
    const pct     = Math.round((data.players / data.max_players) * 100);

    body.innerHTML = `
      <div class="status-stats">
        <div class="status-stat">
          <span class="status-num">${data.players}</span>
          <span class="status-lbl">Online</span>
        </div>
        <div class="status-stat">
          <span class="status-num">${data.max_players}</span>
          <span class="status-lbl">Max Slots</span>
        </div>
        <div class="status-stat">
          <span class="status-num">${pct}%</span>
          <span class="status-lbl">Auslastung</span>
        </div>
      </div>
      <div class="status-bar-wrap">
        <div class="status-bar" style="width:${pct}%"></div>
      </div>
    `;
  } catch (e) {
    dot.className  = 'status-dot offline';
    body.innerHTML = '<div class="status-offline">Server nicht erreichbar</div>';
  }
}

loadServerStatus();
setInterval(loadServerStatus, 30000); // alle 30 Sekunden aktualisieren
</script>
</html>
