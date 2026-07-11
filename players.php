<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/fivem_api.php';

$user     = current_user();
$loggedIn = is_logged_in();
$isAdmin  = has_permission('admin');
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

<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<main class="sp-page">
  <?php if ($cfg['maintenance_mode']): ?>
  <div class="sp-hero">
    <div class="sp-hero-inner">
      <div class="sp-badge" style="border-color:rgba(239,68,68,.35);color:#f87171">
        <span class="sp-badge-dot offline"></span>
        <span>Wartungsmodus</span>
      </div>
      <h1 class="sp-title">Server <span class="accent">Status</span></h1>
      <p class="sp-sub">Echtzeit-&Uuml;bersicht · ViennaStateRP</p>
    </div>
  </div>

  <div class="container">
    <div class="sp-section">
      <div style="background:var(--bg-card);border:1px solid rgba(239,68,68,.25);border-left:3px solid var(--red);border-radius:14px;padding:28px 32px;text-align:center">
        <div style="font-size:2rem;margin-bottom:10px">&#128295;</div>
        <h2 style="font-size:1.2rem;margin-bottom:8px">FiveM-Server in Wartung</h2>
        <p style="color:var(--text-2);font-size:.92rem;line-height:1.7;max-width:560px;margin:0 auto">
          <?= nl2br(htmlspecialchars($cfg['maintenance_message'])) ?>
        </p>
      </div>
    </div>
  </div>
  <?php else: ?>
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
  <?php endif; ?>

  <div class="container">
    <?php if ($isAdmin): ?>
    <!-- ═══════════════════════════════════════════════════════
         ADMIN-BEREICH  (nur sichtbar für Admins)
    ═══════════════════════════════════════════════════════ -->
    <div style="border-top:2px solid rgba(232,184,109,.2);margin:48px 0 32px;padding-top:40px">

      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px">
        <div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:900">
            ⚙️ Admin <span class="accent">Dashboard</span>
          </div>
          <p style="color:#64748b;font-size:.82rem;margin-top:4px">Nur für Admins sichtbar · Einstellungen werden sofort übernommen</p>
        </div>
        <div style="display:flex;gap:8px">
          <button onclick="loadAdminData()" class="btn btn-outline" style="font-size:.82rem;padding:7px 16px">↻ Daten aktualisieren</button>
          <a href="admin.php" class="btn btn-outline" style="font-size:.82rem;padding:7px 16px">Vollständiges Admin-Panel →</a>
        </div>
      </div>

      <!-- Live FiveM Daten -->
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;margin-bottom:20px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
          <span style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b">📡 Live Server-Daten</span>
          <span style="margin-left:auto;font-size:.75rem;color:#64748b" id="adminDataTs"></span>
        </div>
        <div id="adminDataContent" style="padding:20px;color:#94a3b8;font-size:.9rem">Lade...</div>
      </div>

      <!-- Toggle-Grid -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px">
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b;margin-bottom:16px">👁 Öffentliche Anzeige</div>
          <?php
          $toggles = [
            ['show_player_count',   'Spieleranzahl',         'Aktive Spieler anzeigen'],
            ['show_max_slots',      'Max Slots',             'Maximale Kapazität anzeigen'],
            ['show_load_percent',   'Auslastung %',          'Prozentuale Auslastung'],
            ['show_jobs_police',    'Polizei-Karte',         'Polizisten im Dienst'],
            ['show_jobs_fire',      'Feuerwehr-Karte',       'Feuerwehr im Dienst'],
            ['show_jobs_ambulance', 'Berufsrettung-Karte',   'Berufsrettung im Dienst'],
            ['show_jobs_mechanic',  'Mechaniker-Karte',      'Mechaniker im Dienst'],
            ['show_hero_status',    'Online-Badge (Startseite)', 'Server-Status im Hero'],
            ['show_hero_players',   'Spieler (Startseite)',  'Spielerzahl auf Startseite'],
          ];
          foreach ($toggles as [$key, $label, $sub]): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border)">
            <div>
              <div style="font-size:.88rem;color:#e2e8f0"><?= $label ?></div>
              <div style="font-size:.72rem;color:#64748b"><?= $sub ?></div>
            </div>
            <label style="position:relative;display:inline-block;width:42px;height:22px;flex-shrink:0">
              <input type="checkbox" data-key="<?= $key ?>"
                     <?= $cfg[$key] ? 'checked' : '' ?>
                     style="opacity:0;width:0;height:0"
                     onchange="saveToggle(this)" />
              <span class="adm-toggle-slider"></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px">
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b;margin-bottom:16px">⚠️ Wartungsmodus</div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border)">
            <div>
              <div style="font-size:.88rem;color:#ef4444">Wartungsmodus aktivieren</div>
              <div style="font-size:.72rem;color:#64748b">Zeigt Hinweis für alle Besucher</div>
            </div>
            <label style="position:relative;display:inline-block;width:42px;height:22px;flex-shrink:0">
              <input type="checkbox" data-key="maintenance_mode"
                     <?= $cfg['maintenance_mode'] ? 'checked' : '' ?>
                     style="opacity:0;width:0;height:0"
                     onchange="saveToggle(this)" />
              <span class="adm-toggle-slider"></span>
            </label>
          </div>
          <div style="margin-top:14px">
            <div style="font-size:.78rem;color:#94a3b8;margin-bottom:6px">Wartungsnachricht:</div>
            <textarea data-key="maintenance_message" rows="3"
              style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:#e2e8f0;font-family:inherit;font-size:.85rem;resize:vertical"
              oninput="scheduleSave()"><?= htmlspecialchars($cfg['maintenance_message']) ?></textarea>
          </div>

          <!-- Schnellinfos -->
          <div style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px">
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b;margin-bottom:12px">📊 Schnellübersicht</div>
            <div id="adminQuickStats" style="display:grid;grid-template-columns:1fr 1fr;gap:8px"></div>
          </div>
        </div>

      </div>

      <div id="adminSaveMsg" style="margin-top:14px;font-size:.82rem;display:none"></div>

      <!-- Online-Spieler (nur Admin) -->
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;margin-top:20px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
          <span style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b">👥 Online-Spieler</span>
          <span style="margin-left:auto">
            <button onclick="loadPlayerList()" class="btn btn-outline" style="font-size:.75rem;padding:4px 12px">↻ Aktualisieren</button>
          </span>
        </div>
        <div id="adminPlayerList" style="padding:20px;color:#94a3b8;font-size:.9rem">Lade...</div>
      </div>

    </div>

    <style>
    .adm-toggle-slider {
      position:absolute;inset:0;background:rgba(255,255,255,.1);
      border-radius:22px;cursor:pointer;transition:.2s;
    }
    .adm-toggle-slider::before {
      content:'';position:absolute;height:16px;width:16px;
      left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;
    }
    input:checked + .adm-toggle-slider { background:#22c55e; }
    input:checked + .adm-toggle-slider::before { transform:translateX(20px); }
    </style>
    <?php endif; ?>

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
    <div class="footer-links">
      <h5>Rechtliches</h5>
      <a href="impressum.php">Impressum</a>
      <a href="datenschutz.php">Datenschutzerklärung</a>
      <a href="allgemeine-hinweise.php">Allgemeine Hinweise</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten. |
      <a href="impressum.php" style="color:inherit">Impressum</a> ·
      <a href="datenschutz.php" style="color:inherit">Datenschutz</a> ·
      <a href="allgemeine-hinweise.php" style="color:inherit">Hinweise</a>
    </p>
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

<?php if (!$cfg['maintenance_mode']): ?>
load(); tick();
<?php endif; ?>

<?php if ($isAdmin): ?>
// ── Admin-Dashboard JS ────────────────────────────────────────────────────────
const IS_ADMIN = true;

async function loadAdminData() {
  const el = document.getElementById('adminDataContent');
  const ts = document.getElementById('adminDataTs');
  if (!el) return;
  el.innerHTML = '<span style="color:#64748b">Lade...</span>';
  try {
    const r = await fetch('api/players_status.php');
    const d = await r.json();
    ts.textContent = 'Abgerufen: ' + new Date().toLocaleTimeString('de-AT');
    renderAdminData(d);
  } catch(e) {
    el.innerHTML = '<span style="color:#ef4444">Fehler: ' + e.message + '</span>';
  }
}

function renderAdminData(d) {
  const el = document.getElementById('adminDataContent');
  const qs = document.getElementById('adminQuickStats');
  const det = d.details || {};

  // Schnellübersicht
  if (qs) {
    const qi = [
      ['🎮', 'Spieler', d.players + ' / ' + d.max_players],
      ['🗺️', 'Map',     det.map || '–'],
      ['🎯', 'Gametype', det.gametype || '–'],
      ['📦', 'Ressourcen', det.resource_count || '–'],
      ['⬆️', 'Upvote',  det.upvote_power || '–'],
      ['⚡', 'Burst',   det.burst_power || '–'],
    ];
    qs.innerHTML = qi.map(([ico,lbl,val]) =>
      '<div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:8px 10px">'
      + '<div style="font-size:.68rem;color:#64748b;text-transform:uppercase;letter-spacing:1px">' + ico + ' ' + lbl + '</div>'
      + '<div style="font-family:\'Orbitron\',sans-serif;font-size:.95rem;color:#e8b86d;margin-top:2px">' + val + '</div>'
      + '</div>'
    ).join('');
  }

  // Alle Felder in Tabelle
  const rows = [
    ['Status',           d.online ? '<span style="color:#22c55e">● ONLINE</span>' : '<span style="color:#ef4444">● OFFLINE</span>'],
    ['Spieler / Slots',  '<b style="color:#e8b86d;font-size:1.2rem">' + d.players + '</b> / ' + d.max_players],
    ['Hostname',         d.hostname || '–'],
    ['Server-Name',      det.server_name || '–'],
    ['Beschreibung',     det.description || '–'],
    ['Map',              det.map || '–'],
    ['Gametype',         det.gametype || '–'],
    ['Game',             det.gamename || '–'],
    ['Game Build',       det.game_build || '–'],
    ['Locale',           det.locale || '–'],
    ['Tags',             det.tags || '–'],
    ['OneSync',          det.onesync || '–'],
    ['Ressourcen',       det.resource_count > 0 ? det.resource_count + ' geladen' : '–'],
    ['Server Version',   det.server_version || '–'],
    ['Build Nr.',        det.server_version_num > 0 ? det.server_version_num : '–'],
    ['Upvote Power',     det.upvote_power > 0 ? det.upvote_power : '–'],
    ['Burst Power',      det.burst_power > 0 ? det.burst_power : '–'],
    ['Pure Level',       det.pure_level !== '' && det.pure_level !== undefined ? det.pure_level : '–'],
    ['ScriptHook',       det.script_hook === true ? '✓ Erlaubt' : '✗ Gesperrt'],
    ['LAN-Modus',        det.lan_mode === true ? '✓ An' : '✗ Aus'],
    ['Enhanced Host',    det.enhanced_host ? '✓ Ja' : '✗ Nein'],
    ['Support Status',   det.support_status || '–'],
    ['Connect IPs',      (det.connect_endpoints || []).join(', ') || '–'],
    ['Owner',            det.owner_name || '–'],
  ];

  // Jobs-Tabelle
  let jobsHtml = '–';
  if (d.jobs && d.jobs.length) {
    const total = d.jobs.reduce((s,j) => s + j.count, 0);
    jobsHtml = '<table style="width:100%;border-collapse:collapse;font-size:.82rem">'
      + '<tr style="color:#64748b;font-size:.7rem"><th style="text-align:left;padding:2px 6px">Job</th>'
      + '<th style="text-align:right;padding:2px 6px">Spieler</th>'
      + '<th style="text-align:right;padding:2px 6px">Anteil</th></tr>';
    d.jobs.forEach(j => {
      const pct = total > 0 ? Math.round(j.count/total*100) : 0;
      jobsHtml += '<tr style="border-top:1px solid rgba(255,255,255,.06)">'
        + '<td style="padding:4px 6px;color:#e2e8f0">' + (j.label || j.key) + '</td>'
        + '<td style="padding:4px 6px;text-align:right;color:#e8b86d"><b>' + j.count + '</b></td>'
        + '<td style="padding:4px 6px;text-align:right;color:#64748b">' + pct + '%</td></tr>';
    });
    jobsHtml += '</table>';
  }
  rows.push(['Einsatzkräfte (' + (d.jobs ? d.jobs.length : 0) + ' Jobs)', jobsHtml]);

  // Custom Vars
  if (det.custom_vars && Object.keys(det.custom_vars).length) {
    const cvHtml = Object.entries(det.custom_vars).map(([k,v]) =>
      '<tr style="border-top:1px solid rgba(255,255,255,.06)">'
      + '<td style="padding:3px 6px;color:#94a3b8;font-family:monospace;font-size:.78rem">' + k + '</td>'
      + '<td style="padding:3px 6px;color:#86efac;font-family:monospace;font-size:.78rem">' + (v||'–') + '</td></tr>'
    ).join('');
    rows.push(['Custom Vars (' + Object.keys(det.custom_vars).length + ')',
      '<table style="width:100%;border-collapse:collapse">' + cvHtml + '</table>']);
  }

  // Ressourcen-Liste
  if (det.resources && det.resources.length) {
    rows.push(['Ressourcen-Liste',
      '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px">'
      + det.resources.map(r => '<span style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:4px;padding:2px 7px;font-size:.72rem;color:#94a3b8">' + r + '</span>').join('')
      + '</div>']);
  }

  el.innerHTML = '<table style="width:100%;border-collapse:collapse;font-size:.85rem">'
    + '<thead><tr><th style="text-align:left;color:#64748b;padding:4px 8px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;width:35%">Feld</th>'
    + '<th style="text-align:left;color:#64748b;padding:4px 8px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px">Wert</th></tr></thead><tbody>'
    + rows.filter(([,v]) => v && v !== '–').map(([k,v]) =>
        '<tr style="border-top:1px solid rgba(255,255,255,.05)">'
        + '<td style="padding:7px 8px;color:#94a3b8;vertical-align:top">' + k + '</td>'
        + '<td style="padding:7px 8px;color:#e2e8f0;word-break:break-all">' + v + '</td></tr>'
    ).join('')
    + '</tbody></table>';
}

// Toggle speichern
let saveTimer = null;
function scheduleSave() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(doSave, 600);
}
async function saveToggle(el) { await doSave(); }
async function doSave() {
  const data = {};
  document.querySelectorAll('[data-key]').forEach(el => {
    data[el.dataset.key] = el.type === 'checkbox' ? el.checked : el.value;
  });
  const msg = document.getElementById('adminSaveMsg');
  try {
    const r   = await fetch('api/admin_settings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) });
    const res = await r.json();
    if (msg) {
      msg.textContent = res.ok ? '✓ Einstellungen gespeichert' : '✗ Fehler beim Speichern';
      msg.style.color   = res.ok ? '#22c55e' : '#ef4444';
      msg.style.display = 'block';
      setTimeout(() => { if (msg) msg.style.display = 'none'; }, 2500);
    }
  } catch(e) {
    if (msg) { msg.textContent = '✗ ' + e.message; msg.style.display = 'block'; }
  }
}

// Textarea-Inputs auch beobachten
document.querySelectorAll('textarea[data-key]').forEach(el => {
  el.addEventListener('input', scheduleSave);
});

loadAdminData();

async function loadPlayerList() {
  const el = document.getElementById('adminPlayerList');
  if (!el) return;
  el.innerHTML = '<span style="color:#64748b">Lade...</span>';
  try {
    const r = await fetch('api/admin_players.php');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (!d.ok || !d.players.length) {
      el.innerHTML = '<span style="color:#64748b">Keine Spieler online.</span>';
      return;
    }
    el.innerHTML = '<table style="width:100%;border-collapse:collapse;font-size:.85rem">'
      + '<thead><tr>'
      + '<th style="text-align:left;color:#64748b;padding:6px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)">#</th>'
      + '<th style="text-align:left;color:#64748b;padding:6px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)">Name</th>'
      + '<th style="text-align:left;color:#64748b;padding:6px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)">Job</th>'
      + '<th style="text-align:right;color:#64748b;padding:6px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)">Ping</th>'
      + '</tr></thead><tbody>'
      + d.players.map(p =>
          '<tr style="border-top:1px solid rgba(255,255,255,.05)">'
          + '<td style="padding:6px 10px;color:#4b5563">' + p.id + '</td>'
          + '<td style="padding:6px 10px;color:#e2e8f0;font-weight:600">' + p.name + '</td>'
          + '<td style="padding:6px 10px;color:#94a3b8">' + (p.job || '–') + '</td>'
          + '<td style="padding:6px 10px;text-align:right;color:' + (p.ping > 150 ? '#ef4444' : p.ping > 80 ? '#f59e0b' : '#22c55e') + '">' + p.ping + ' ms</td>'
          + '</tr>'
        ).join('')
      + '</tbody></table>'
      + '<div style="margin-top:10px;font-size:.75rem;color:#4b5563">'
      + d.count + ' Spieler online · ' + new Date().toLocaleTimeString(\'de-AT\') + '</div>';
  } catch(e) {
    el.innerHTML = '<span style="color:#ef4444">Fehler: ' + e.message + '</span>';
  }
}

loadPlayerList();
<?php endif; ?>
</script>
</body>
</html>