<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';

$user = current_user();

function avatarUrl(?array $user): string {
    if (empty($user['avatar']) || empty($user['id'])) {
        return 'https://cdn.discordapp.com/embed/avatars/0.png';
    }
    return 'https://cdn.discordapp.com/avatars/' . $user['id'] . '/' . $user['avatar'] . '.png?size=64';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Aktive Spieler – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
</head>
<body>

<!-- ==================== NAVBAR ==================== -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php">
      <span class="logo-accent">Vienna</span>StateRP
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#about">Über uns</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="index.php#rules">Regeln</a></li>
      <li><a href="players.php" class="active">Aktive Spieler</a></li>
      <li><a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a></li>
    </ul>
    <div class="nav-actions">
      <?php if ($user): ?>
        <a href="dashboard.php" class="btn btn-ghost">
          <img src="<?= avatarUrl($user) ?>" alt="Avatar" class="nav-avatar" />
          <?= htmlspecialchars($user['global_name']) ?>
        </a>
        <a href="logout.php" class="btn btn-outline">Abmelden</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-discord">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
          Mit Discord anmelden
        </a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menü">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- ==================== MAIN ==================== -->
<main class="players-page">

  <!-- Hero Banner -->
  <div class="players-hero">
    <div class="players-hero-bg"></div>
    <div class="container players-hero-content">
      <div class="players-live-badge">
        <span class="plb-dot" id="psbDot"></span>
        <span id="psbText">Verbinde…</span>
        <span class="plb-sep">·</span>
        <span class="plb-refresh">↻ <span id="psbCountdown">30</span>s</span>
      </div>
      <h1 class="players-hero-title">Server<span class="accent"> Status</span></h1>
      <p class="players-hero-sub">Echtzeit-Übersicht · ViennaStateRP</p>
    </div>
  </div>

  <div class="container">

    <!-- Spieler-Statistik -->
    <div class="players-stats-row" id="countCard">
      <div class="psr-card">
        <span class="psr-num" id="playerCount">–</span>
        <span class="psr-lbl">Aktive Spieler</span>
      </div>
      <div class="psr-divider"></div>
      <div class="psr-card">
        <span class="psr-num psr-num--secondary" id="playerMax">–</span>
        <span class="psr-lbl">Max Slots</span>
      </div>
      <div class="psr-divider"></div>
      <div class="psr-card">
        <span class="psr-num psr-num--secondary" id="playerPct">–</span>
        <span class="psr-lbl">Auslastung</span>
      </div>
      <div class="psr-bar-wrap">
        <div class="psr-bar" id="playerBar" style="width:0%"></div>
      </div>
    </div>

    <!-- Einsatzkräfte -->
    <div class="jobs-section">
      <div class="jobs-section-header">
        <span class="section-tag">Live</span>
        <h2 class="jobs-section-title">Einsatzkräfte <span class="accent">im Dienst</span></h2>
      </div>
      <div class="jobs-grid" id="jobsGrid">
        <div class="job-card-skeleton"></div>
        <div class="job-card-skeleton"></div>
        <div class="job-card-skeleton"></div>
        <div class="job-card-skeleton"></div>
      </div>
    </div>

  </div>
</main>

<!-- ==================== FOOTER ==================== -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server.<br>Seit 2022 mit Leidenschaft entwickelt.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="index.php#about">Über uns</a>
      <a href="index.php#features">Features</a>
      <a href="index.php#rules">Regeln</a>
      <a href="players.php">Aktive Spieler</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="login.php">Login</a>
      <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="footer-links">
      <h5>Server</h5>
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>">Verbinden</a>
      <p class="server-ip"><?= htmlspecialchars(FIVEM_IP) ?>:<?= htmlspecialchars(FIVEM_PORT) ?></p>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten. | Kein offizielles Rockstar Games Produkt.</p>
  </div>
</footer>

<script src="assets/js/main.js"></script>
<script>
// ── Job-Icons (SVG inline) ────────────────────────────────────────────────────
const JOB_ICONS = {
  police:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2 5h5l-4 3 1.5 5L12 12l-4.5 3L9 10 5 7h5z"/></svg>`,
  fire:      `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c0 6-6 8-6 13a6 6 0 0012 0c0-5-6-7-6-13z"/><path d="M12 12c0 3-2 4-2 6a2 2 0 004 0c0-2-2-3-2-6z"/></svg>`,
  ambulance: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>`,
  mechanic:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>`,
};

const JOB_COLORS = {
  police:    '#3b82f6',
  fire:      '#f97316',
  ambulance: '#ef4444',
  mechanic:  '#a3a3a3',
};

let countdown = 30;
let countdownTimer;

async function fetchStatus() {
  try {
    const res  = await fetch('api/players_status.php');
    const data = await res.json();
    renderStatus(data);
  } catch (e) {
    renderOffline();
  }
}

function renderStatus(data) {
  const dot     = document.getElementById('psbDot');
  const text    = document.getElementById('psbText');
  const pCount  = document.getElementById('playerCount');
  const pMax    = document.getElementById('playerMax');
  const pPct    = document.getElementById('playerPct');
  const pBar    = document.getElementById('playerBar');
  const card    = document.getElementById('countCard');

  if (!data.online) {
    dot.className    = 'plb-dot offline';
    text.textContent = 'Server offline';
    card.classList.add('offline');
    renderJobs([]);
    return;
  }

  dot.className    = 'plb-dot online';
  text.textContent = 'Server online';
  card.classList.remove('offline');

  pCount.textContent = data.players;
  pMax.textContent   = data.max_players;
  const pct = data.max_players > 0 ? Math.round((data.players / data.max_players) * 100) : 0;
  pPct.textContent   = pct + '%';
  pBar.style.width   = pct + '%';

  renderJobs(data.jobs || []);
}

function renderOffline() {
  document.getElementById('psbDot').className    = 'plb-dot offline';
  document.getElementById('psbText').textContent = 'Nicht erreichbar';
  renderJobs([]);
}

function renderJobs(jobs) {
  const grid = document.getElementById('jobsGrid');
  if (!jobs.length) {
    grid.innerHTML = '<p class="jobs-empty">Keine Daten verfügbar</p>';
    return;
  }
  grid.innerHTML = jobs.map(j => {
    const color = JOB_COLORS[j.key] || '#7c3aed';
    const icon  = JOB_ICONS[j.key]  || '';
    const pct   = Math.min(j.count * 12, 100);
    return `
      <div class="job-card" style="--job-color:${color}">
        <div class="job-card-glow"></div>
        <div class="job-card-top">
          <div class="job-icon">${icon}</div>
          <span class="job-count">${j.count}</span>
        </div>
        <div class="job-label">${j.label}</div>
        <div class="job-bar-wrap">
          <div class="job-bar" style="width:${pct}%"></div>
        </div>
        <div class="job-bar-label">Im Dienst</div>
      </div>`;
  }).join('');
}

function startCountdown() {
  clearInterval(countdownTimer);
  countdown = 30;
  document.getElementById('psbCountdown').textContent = countdown;
  countdownTimer = setInterval(() => {
    countdown--;
    document.getElementById('psbCountdown').textContent = countdown;
    if (countdown <= 0) {
      countdown = 30;
      fetchStatus();
    }
  }, 1000);
}

fetchStatus();
startCountdown();
</script>
</body>
</html>
