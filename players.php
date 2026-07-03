<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/site_settings.php';

$user     = current_user();
$loggedIn = is_logged_in();
$cfg      = settings_load();

if (!function_exists('avatarUrl')) {
    function avatarUrl(?array $user): string {
        if (empty($user['avatar']) || empty($user['id'])) {
            return 'https://cdn.discordapp.com/embed/avatars/0.png';
        }
        return 'https://cdn.discordapp.com/avatars/' . $user['id'] . '/' . $user['avatar'] . '.png?size=64';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Server Status – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#about">Uber uns</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="index.php#rules">Regeln</a></li>
      <li><a href="players.php" class="active">Server Status</a></li>
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
        <a href="login.php" class="btn btn-discord" style="font-size:.85rem">Discord Login</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<main class="sp-page">
  <div class="sp-hero">
    <div class="sp-hero-inner">
      <div class="sp-badge">
        <span class="sp-badge-dot" id="spDot"></span>
        <span id="spStatus">Verbinde...</span>
        <span style="opacity:.3;margin:0 4px">·</span>
        <span style="font-size:.75rem;color:#4b5563">Aktualisiert in <span id="spCountdown">30</span>s</span>
      </div>
      <h1 class="sp-title">Server <span class="accent">Status</span></h1>
      <p class="sp-sub">Echtzeit-Ubersicht · ViennaStateRP</p>
    </div>
  </div>

  <div class="container">

    <!-- Stats -->
    <div class="sp-stats" id="spStats">
      <div class="sp-stats-cols">
        <div class="sp-stat">
          <span class="sp-stat-num" id="spPlayers">–</span>
          <span class="sp-stat-lbl">Aktive Spieler</span>
        </div>
        <div class="sp-stat-divider"></div>
        <div class="sp-stat">
          <span class="sp-stat-num secondary" id="spMax">–</span>
          <span class="sp-stat-lbl">Max Slots</span>
        </div>
        <div class="sp-stat-divider"></div>
        <div class="sp-stat">
          <span class="sp-stat-num secondary" id="spPct">–</span>
          <span class="sp-stat-lbl">Auslastung</span>
        </div>
      </div>
      <div class="sp-bar-wrap"><div class="sp-bar" id="spBar" style="width:0%"></div></div>
    </div>

    <!-- Server Details (öffentlich) -->
    <div class="sp-section" id="spDetailsSection" style="display:none">
      <div class="sp-section-head">
        <span class="sp-section-tag">Server Info</span>
        <h2 class="sp-section-title">Server <span class="accent">Details</span></h2>
      </div>
      <div class="sp-details" id="spDetailsGrid"></div>
    </div>

    <!-- Einsatzkraefte -->
    <div class="sp-section" id="spJobsSection">
      <div class="sp-section-head">
        <span class="sp-section-tag">Live</span>
        <h2 class="sp-section-title">Einsatzkräfte <span class="accent">im Dienst</span></h2>
      </div>
      <div class="sp-jobs" id="spJobs">
        <div class="sp-skeleton"></div>
        <div class="sp-skeleton"></div>
        <div class="sp-skeleton"></div>
        <div class="sp-skeleton"></div>
      </div>
    </div>

  </div>
</main>

<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="index.php">Startseite</a>
      <a href="index.php#features">Features</a>
      <a href="index.php#rules">Regeln</a>
      <a href="players.php">Server Status</a>
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
    <p>&copy; <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten.</p>
  </div>
</footer>

<script src="assets/js/main.js"></script>
<script>
const IS_LOGGED_IN = <?= $loggedIn ? 'true' : 'false' ?>;
const SHOW_PLAYERS  = <?= $cfg['show_player_count']   ? 'true' : 'false' ?>;
const SHOW_SLOTS    = <?= $cfg['show_max_slots']       ? 'true' : 'false' ?>;
const SHOW_PCT      = <?= $cfg['show_load_percent']    ? 'true' : 'false' ?>;
const SHOW_JOBS     = {
  police:    <?= $cfg['show_jobs_police']    ? 'true' : 'false' ?>,
  fire:      <?= $cfg['show_jobs_fire']      ? 'true' : 'false' ?>,
  ambulance: <?= $cfg['show_jobs_ambulance'] ? 'true' : 'false' ?>,
  mechanic:  <?= $cfg['show_jobs_mechanic']  ? 'true' : 'false' ?>,
};
const ALWAYS_SHOW_DETAILS = true;

// ── Job-Daten: bekannte Jobs mit Icon + Farbe ─────────────────────────────────
const JOB_DATA = {
  police:        { color:'#3b82f6', label:'Polizei',             icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2 5h5l-4 3 1.5 5L12 12l-4.5 3L9 10 5 7h5z"/></svg>' },
  fire:          { color:'#f97316', label:'Feuerwehr',           icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c0 6-6 8-6 13a6 6 0 0012 0c0-5-6-7-6-13z"/><path d="M12 12c0 3-2 4-2 6a2 2 0 004 0c0-2-2-3-2-6z"/></svg>' },
  ambulance:     { color:'#ef4444', label:'Berufsrettung Wien',  icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>' },
  mechanic:      { color:'#a3a3a3', label:'Mechanik',            icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>' },
  taxi:          { color:'#fbbf24', label:'Taxi',                icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="10" width="22" height="9" rx="2"/><path d="M5 10V7a2 2 0 012-2h4l2 5"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/></svg>' },
  judge:         { color:'#8b5cf6', label:'Richter',             icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>' },
  lawyer:        { color:'#06b6d4', label:'Anwalt',              icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' },
  reporter:      { color:'#10b981', label:'Reporter',            icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h4"/></svg>' },
  unemployed:    { color:'#4b5563', label:'Zivilist',            icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' },
};

// Farb-Palette für unbekannte Jobs (rotiert durch)
const FALLBACK_COLORS = ['#7c3aed','#0ea5e9','#14b8a6','#f59e0b','#ec4899','#84cc16','#6366f1'];
const FALLBACK_ICON   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/></svg>';

function getJobColor(key, index) {
  return JOB_DATA[key] ? JOB_DATA[key].color : FALLBACK_COLORS[index % FALLBACK_COLORS.length];
}
function getJobIcon(key) {
  return JOB_DATA[key] ? JOB_DATA[key].icon : FALLBACK_ICON;
}
function getJobLabel(j) {
  if (JOB_DATA[j.key]) return JOB_DATA[j.key].label;
  return j.label || j.key.charAt(0).toUpperCase() + j.key.slice(1).replace(/[_-]/g,' ');
}

let cd = 30, timer;

async function load() {
  try {
    const r = await fetch('api/players_status.php');
    const d = await r.json();
    render(d);
  } catch(e) {
    document.getElementById('spDot').className = 'sp-badge-dot offline';
    document.getElementById('spStatus').textContent = 'Nicht erreichbar';
    renderJobs([]);
  }
}

function render(d) {
  const dot = document.getElementById('spDot');
  const txt = document.getElementById('spStatus');
  const box = document.getElementById('spStats');
  if (!d.online) {
    dot.className = 'sp-badge-dot offline';
    txt.textContent = 'Server offline';
    box.classList.add('offline');
    document.getElementById('spPlayers').textContent = '0';
    ['spMax','spPct'].forEach(id => document.getElementById(id).textContent = '–');
    document.getElementById('spBar').style.width = '0%';
    renderJobs([]);
    return;
  }
  dot.className = 'sp-badge-dot online';
  txt.textContent = 'Server online';
  box.classList.remove('offline');
  const pct = d.max_players > 0 ? Math.round(d.players/d.max_players*100) : 0;
  document.getElementById('spPlayers').textContent = d.players;
  document.getElementById('spMax').textContent     = d.max_players;
  document.getElementById('spPct').textContent     = pct + '%';
  document.getElementById('spBar').style.width     = pct + '%';
  renderJobs(d.jobs || []);
  if (d.details) renderDetails(d.details);
}

function renderJobs(jobs) {
  const g    = document.getElementById('spJobs');
  const sect = document.getElementById('spJobsSection');

  // Bridge nicht verbunden oder keine Daten
  if (!jobs || !jobs.length) {
    if (sect) sect.style.display = 'none';
    return;
  }

  if (sect) sect.style.display = '';

  // Filter: Admin-Toggles nur für bekannte Jobs, unbekannte immer anzeigen
  const visible = jobs.filter(j => {
    if (j.key in SHOW_JOBS) return SHOW_JOBS[j.key] !== false;
    return true;
  });

  if (!visible.length) {
    if (sect) sect.style.display = 'none';
    return;
  }

  g.innerHTML = visible.map((j, i) => {
    const c   = getJobColor(j.key, i);
    const ico = getJobIcon(j.key);
    const lbl = getJobLabel(j);
    const pct = Math.min(j.count * 10, 100);
    return '<div class="sp-job" style="--jc:' + c + '">'
      + '<div class="sp-job-head">'
      + '<span class="sp-job-icon" style="width:28px;height:28px;color:' + c + '">' + ico + '</span>'
      + '<span class="sp-job-num">' + j.count + '</span>'
      + '</div>'
      + '<span class="sp-job-name">' + lbl + '</span>'
      + '<div class="sp-job-bar-wrap"><div class="sp-job-bar" style="width:' + pct + '%"></div></div>'
      + '</div>';
  }).join('');
}

function renderDetails(det) {
  const sec = document.getElementById('spDetailsSection');
  const g   = document.getElementById('spDetailsGrid');
  if (!sec || !g) return;
  const items = [
    { icon: '🖥️', lbl: 'Ressourcen',  val: det.resource_count || null },
    { icon: '🗺️', lbl: 'Map',          val: det.map || null },
    { icon: '🎮', lbl: 'Gametype',     val: det.gametype || null },
    { icon: '🌍', lbl: 'Sprache',      val: det.locale || null },
    { icon: '⬆️', lbl: 'Upvote Power', val: det.upvote_power > 0 ? det.upvote_power : null },
    { icon: '⚡', lbl: 'Burst Power',  val: det.burst_power > 0 ? det.burst_power : null },
  ].filter(i => i.val !== null && i.val !== '');
  if (!items.length) return;
  g.innerHTML = items.map(i =>
    '<div class="sp-detail-card">'
    + '<span class="sp-detail-icon">' + i.icon + '</span>'
    + '<div><span class="sp-detail-val">' + i.val + '</span>'
    + '<span class="sp-detail-lbl">' + i.lbl + '</span></div>'
    + '</div>'
  ).join('');
  sec.style.display = '';
}

function tick() {
  clearInterval(timer);
  cd = 30;
  document.getElementById('spCountdown').textContent = 30;
  timer = setInterval(function() {
    cd--;
    document.getElementById('spCountdown').textContent = cd;
    if (cd <= 0) { load(); cd = 30; }
  }, 1000);
}

load(); tick();
</script>
</body>
</html>