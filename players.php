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
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#about">Über uns</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="index.php#fraktionen">Fraktionen</a></li>
      <li><a href="regelwerk.php">Regeln</a></li>
      <li><a href="changelog.php">Changelog</a></li>
      <li><a href="index.php#join">Beitreten</a></li>
      <li><a href="index.php#faq">FAQ</a></li>
      <li><a href="players.php" class="active">Server Status</a></li>
      <li><a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a></li>
    </ul>
    <div class="nav-actions">
      <a href="https://ko-fi.com/viennastaterp" target="_blank" rel="noopener" class="btn btn-kofi" style="font-size:.85rem">☕ Unterstützen</a>
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
<?php include __DIR__ . '/includes/restart_banner.php'; ?>

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
      <div class="sp-badge" style="margin-top:-10px">
        <span class="sp-badge-dot" id="devDot"></span>
        <span id="devStatus">Dev-Server: Verbinde...</span>
      </div>
      <div class="sp-badge" style="margin-top:-10px">
        <span class="sp-badge-dot" style="background:var(--gold)"></span>
        <span id="restartStatus">Nächster Neustart: –</span>
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
      <a href="regelwerk.php">Regeln</a>
      <a href="index.php#faq">FAQ</a>
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
      <h5>Unterstützung</h5>
      <a href="https://www.paypal.com/paypalme/chrisgaming445" target="_blank" rel="noopener">PayPal</a>
      <a href="https://ko-fi.com/viennastaterp" target="_blank" rel="noopener">Ko-fi</a>
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

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
const IS_LOGGED_IN = <?= $loggedIn ? 'true' : 'false' ?>;
const SHOW_PLAYERS  = <?= $cfg['show_player_count']   ? 'true' : 'false' ?>;
const SHOW_SLOTS    = <?= $cfg['show_max_slots']       ? 'true' : 'false' ?>;
const SHOW_PCT      = <?= $cfg['show_load_percent']    ? 'true' : 'false' ?>;
const ALWAYS_SHOW_DETAILS = true;
const RESTART_TIMES = <?= json_encode(array_values(array_filter(array_map('trim', explode(',', $cfg['restart_times']))))) ?>;

let cd = 30, timer;

async function load() {
  const controller = new AbortController();
  const abortTimer = setTimeout(() => controller.abort(), 12000);
  try {
    const r = await fetch('api/players_status.php', { signal: controller.signal, cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    render(d);
  } catch(e) {
    document.getElementById('spDot').className = 'sp-badge-dot offline';
    document.getElementById('spStatus').textContent = 'Nicht erreichbar';
    document.getElementById('spPlayers').textContent = '0';
    ['spMax','spPct'].forEach(id => document.getElementById(id).textContent = '–');
    document.getElementById('spBar').style.width = '0%';
    renderDevStatus(null);
  } finally {
    clearTimeout(abortTimer);
  }
}

function renderDevStatus(online) {
  const dot = document.getElementById('devDot');
  const txt = document.getElementById('devStatus');
  if (!dot || !txt) return;
  if (online === true) {
    dot.className = 'sp-badge-dot online';
    txt.textContent = 'Dev-Server: Online';
  } else if (online === false) {
    dot.className = 'sp-badge-dot offline';
    txt.textContent = 'Dev-Server: Offline';
  } else {
    dot.className = 'sp-badge-dot';
    txt.textContent = 'Dev-Server: Unbekannt';
  }
}

function render(d) {
  const dot = document.getElementById('spDot');
  const txt = document.getElementById('spStatus');
  const box = document.getElementById('spStats');
  renderDevStatus(d.dev_server_online === true);
  if (!d.online) {
    dot.className = 'sp-badge-dot offline';
    txt.textContent = 'Server offline';
    box.classList.add('offline');
    document.getElementById('spPlayers').textContent = '0';
    ['spMax','spPct'].forEach(id => document.getElementById(id).textContent = '–');
    document.getElementById('spBar').style.width = '0%';
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
  if (d.details) renderDetails(d.details);
}

// ── Nächster Server-Neustart (fixer täglicher Zeitplan) ───────────────────────
function nextRestartDate() {
  if (!RESTART_TIMES.length) return null;
  const now = new Date();
  let best = null;
  RESTART_TIMES.forEach(t => {
    const parts = t.split(':');
    const h = parseInt(parts[0], 10), m = parseInt(parts[1] || '0', 10);
    if (isNaN(h)) return;
    for (let dayOffset = 0; dayOffset <= 1; dayOffset++) {
      const d = new Date(now);
      d.setDate(d.getDate() + dayOffset);
      d.setHours(h, m, 0, 0);
      if (d > now && (!best || d < best)) best = d;
    }
  });
  return best;
}

function renderRestartStatus() {
  const el = document.getElementById('restartStatus');
  if (!el) return;
  const next = nextRestartDate();
  if (!next) { el.textContent = 'Neustart-Zeitplan nicht konfiguriert'; return; }
  const hh = String(next.getHours()).padStart(2, '0');
  const mm = String(next.getMinutes()).padStart(2, '0');
  const diffMin = Math.max(0, Math.round((next - new Date()) / 60000));
  const countdown = diffMin < 60 ? ('in ' + diffMin + ' Min.') : ('in ' + Math.floor(diffMin / 60) + 'h ' + (diffMin % 60) + 'min');
  el.textContent = 'Nächster Neustart: ' + hh + ':' + mm + ' Uhr (' + countdown + ')';
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
renderRestartStatus();
setInterval(renderRestartStatus, 30000);
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
      + '</tr></thead><tbody>'
      + d.players.map(p =>
          '<tr style="border-top:1px solid rgba(255,255,255,.05)">'
          + '<td style="padding:6px 10px;color:#4b5563">' + p.id + '</td>'
          + '<td style="padding:6px 10px;color:#e2e8f0;font-weight:600">' + p.name + '</td>'
          + '<td style="padding:6px 10px;color:#94a3b8">' + (p.job || '–') + '</td>'
          + '</tr>'
        ).join('')
      + '</tbody></table>'
      + '<div style="margin-top:10px;font-size:.75rem;color:#4b5563">'
      + d.count + ' Spieler online · ' + new Date().toLocaleTimeString('de-AT') + '</div>';
  } catch(e) {
    el.innerHTML = '<span style="color:#ef4444">Fehler: ' + e.message + '</span>';
  }
}

loadPlayerList();
<?php endif; ?>
</script>
</body>
</html>