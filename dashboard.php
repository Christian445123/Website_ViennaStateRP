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

// --- Whitelist-Bewerbung → wird jetzt via process/process_whitelist.php gehandelt ---
// Dashboard lädt nur den aktuellen Status
$formSuccess = htmlspecialchars($_GET['wl_success'] ?? '', ENT_QUOTES);
$formError   = htmlspecialchars($_GET['wl_error']   ?? '', ENT_QUOTES);

// --- Whitelist-Status aus DB laden ---
$wlApplication = null;
if (!empty($user['db_id'])) {
    $s = $pdo->prepare('SELECT status, staff_note, created_at FROM whitelist_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $s->execute([$user['db_id']]);
    $wlApplication = $s->fetch() ?: null;
}

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

<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <div class="nav-actions">
      <a href="index.php" class="btn btn-ghost">← Startseite</a>
      <a href="logout.php" class="btn btn-outline">Abmelden</a>
    </div>
  </div>
</nav>

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

      <!-- Whitelist Status -->
      <div class="dash-card glass">
        <h3 class="dash-card-title">✅ Whitelist Status</h3>
        <?php if ($user['is_whitelisted'] ?? false): ?>
          <div class="wl-badge wl-approved">✓ Whitelisted – Du hast Zugang zum Server</div>
        <?php elseif ($wlApplication): ?>
          <?php
            $statusMap = ['pending'=>['🕐','wl-pending','Bewerbung läuft'], 'approved'=>['✓','wl-approved','Genehmigt'], 'rejected'=>['✗','wl-rejected','Abgelehnt']];
            [$icon, $cls, $label] = $statusMap[$wlApplication['status']] ?? ['?','wl-pending','Unbekannt'];
          ?>
          <div class="wl-badge <?= $cls ?>"><?= $icon ?> <?= $label ?></div>
          <p class="wl-date">Eingereicht am <?= date('d.m.Y', strtotime($wlApplication['created_at'])) ?></p>
          <?php if ($wlApplication['staff_note']): ?>
            <p class="wl-note"><strong>Staff-Notiz:</strong> <?= htmlspecialchars($wlApplication['staff_note']) ?></p>
          <?php endif; ?>
        <?php else: ?>
          <div class="wl-badge wl-pending">⚠ Noch nicht beworben</div>
          <p style="color:var(--text-200);font-size:.88rem;margin-top:8px;">Fülle das Whitelist-Formular unten aus, um Zugang zu erhalten.</p>
        <?php endif; ?>
      </div>

      <!-- Whitelist-Formular -->
      <?php if (!($user['is_whitelisted'] ?? false) && (!$wlApplication || $wlApplication['status'] === 'rejected')): ?>
      <div class="dash-card glass dash-wide">
        <h3 class="dash-card-title">📋 Whitelist Bewerbung</h3>

        <?php if ($formSuccess): ?>
          <div class="alert alert-success"><?= $formSuccess ?></div>
        <?php elseif ($formError): ?>
          <div class="alert alert-error">⚠ <?= $formError ?></div>
        <?php endif; ?>

        <form method="POST" action="process/process_whitelist.php" class="wl-form" id="wlForm">
          <input type="hidden" name="action" value="apply_whitelist" />
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />

          <div class="form-grid">
            <div class="form-group">
              <label for="real_name">Dein echter Vorname</label>
              <input type="text" id="real_name" name="real_name" required maxlength="100" placeholder="Max" />
            </div>
            <div class="form-group">
              <label for="age">Dein Alter</label>
              <input type="number" id="age" name="age" required min="14" max="80" placeholder="20" />
            </div>
            <div class="form-group">
              <label for="char_name">Charaktername (IC)</label>
              <input type="text" id="char_name" name="char_name" required maxlength="100" placeholder="Max Mustermann" />
            </div>
            <div class="form-group form-full">
              <label for="char_story">Hintergrundgeschichte deines Charakters</label>
              <textarea id="char_story" name="char_story" required minlength="100" maxlength="3000" rows="5"
                placeholder="Erzähle uns, wer dein Charakter ist, woher er kommt..."></textarea>
            </div>
            <div class="form-group form-full">
              <label for="motivation">Warum möchtest du bei ViennaStateRP spielen?</label>
              <textarea id="motivation" name="motivation" required minlength="50" maxlength="1000" rows="4"
                placeholder="Was motiviert dich, unserem Server beizutreten?"></textarea>
            </div>
            <div class="form-group form-full">
              <label for="rp_experience">Deine RP-Erfahrung</label>
              <textarea id="rp_experience" name="rp_experience" required minlength="20" maxlength="1000" rows="3"
                placeholder="Hast du bereits auf anderen RP-Servern gespielt? Welche?"></textarea>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="margin-top:8px;">
            Bewerbung einreichen
          </button>
        </form>
      </div>
      <?php endif; ?>
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

      <!-- Server Info -->
      <div class="dash-card glass">
        <h3 class="dash-card-title">📡 Server Info</h3>
        <ul class="info-list">
          <li><span>IP</span><code><?= htmlspecialchars(FIVEM_SERVER_IP) ?></code></li>
          <li><span>Port</span><code><?= htmlspecialchars(FIVEM_SERVER_PORT) ?></code></li>
          <li><span>Framework</span><code>ESX / QBCore</code></li>
          <li><span>Version</span><code>FiveM Latest</code></li>
        </ul>
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
</body>
</html>
