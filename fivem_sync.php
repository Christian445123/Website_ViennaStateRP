<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_permission('admin')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}

header('Location: admin.php?notice=' . urlencode('Die direkte Spielserver-DB-Anbindung wurde deaktiviert.'));
exit;
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FiveM DB Sync – ViennaStateRP</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    body{padding:90px 0 80px}
    .sync-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .sync-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden}
    .sync-card-head{padding:14px 20px;border-bottom:1px solid var(--border);font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b;display:flex;align-items:center;justify-content:space-between;gap:8px}
    .sync-card-body{padding:20px}
    .sync-full{grid-column:1/-1}
    .fi{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem;outline:none;transition:border-color .2s}
    .fi:focus{border-color:#e8b86d}
    label{display:block;font-size:.72rem;color:#94a3b8;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px}
    .fg{margin-bottom:14px}
    .log-entry{padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.05);font-size:.82rem;display:flex;gap:16px;align-items:flex-start}
    .log-entry:last-child{border-bottom:none}
    .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.7rem;font-weight:700}
    .badge-ok{background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.3)}
    .badge-err{background:rgba(239,68,68,.12);color:#fca5a5;border:1px solid rgba(239,68,68,.3)}
    .badge-warn{background:rgba(232,184,109,.12);color:#e8b86d;border:1px solid rgba(232,184,109,.3)}
    .progress-bar{height:6px;background:rgba(255,255,255,.06);border-radius:999px;overflow:hidden;margin-top:12px;display:none}
    .progress-fill{height:100%;background:linear-gradient(90deg,#7c3aed,#e8b86d);border-radius:999px;transition:width .4s}
    .tbl-result{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
    .tbl-chip{padding:3px 10px;border-radius:4px;font-size:.72rem;font-family:'JetBrains Mono',monospace}
    @media(max-width:768px){.sync-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>

<nav class="navbar scrolled">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="admin.php">Admin</a></li>
      <li><a href="db_admin.php">DB Browser</a></li>
      <li><a href="db_live_browser.php">🗄️ DBLIVE Browser</a></li>
      <li><a href="fivem_sync.php" class="active">DB Sync</a></li>
    </ul>
    <div class="nav-actions">
      <a href="dashboard.php" class="btn btn-ghost">Dashboard</a>
      <a href="logout.php" class="btn btn-outline">Abmelden</a>
    </div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
  </div>
</nav>

<main>
<div class="container">

  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900">
        🔄 FiveM DB <span style="color:#e8b86d">Sync</span>
      </h1>
      <p style="color:#64748b;font-size:.85rem;margin-top:6px">
        Remote FiveM-Datenbank → Lokal <code style="color:#86efac">DBLIVE</code>
      </p>
    </div>
    <div style="display:flex;gap:8px">
      <button onclick="runSync()" class="btn btn-primary" id="syncBtn" <?= !$hasCfg ? 'disabled' : '' ?>>
        ▶ Sync starten
      </button>
      <a href="db_admin.php" class="btn btn-outline">DB Browser</a>
      <a href="db_live_browser.php" class="btn btn-primary" style="font-size:.82rem">🗄️ DBLIVE Browser</a>
      <a href="admin.php" class="btn btn-ghost">← Admin</a>
    </div>
  </div>

  <div class="sync-grid">

    <!-- Remote DB Konfiguration -->
    <div class="sync-card">
      <div class="sync-card-head">
        🔌 FiveM Remote-Datenbank
        <span id="cfgStatus" style="font-size:.75rem;font-weight:400;color:<?= $hasCfg ? '#22c55e' : '#f59e0b' ?>">
          <?= $hasCfg ? '✓ Konfiguriert' : '⚠ Nicht konfiguriert' ?>
        </span>
      </div>
      <div class="sync-card-body">
        <div class="fg">
          <label>Host / IP-Adresse</label>
          <input class="fi" type="text" id="fHost" value="<?= htmlspecialchars($cfg['host'] ?? '') ?>" placeholder="z.B. db.fivem-server.at" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 100px;gap:12px">
          <div class="fg">
            <label>Datenbankname</label>
            <input class="fi" type="text" id="fName" value="<?= htmlspecialchars($cfg['name'] ?? '') ?>" placeholder="fivem_db" />
          </div>
          <div class="fg">
            <label>Port</label>
            <input class="fi" type="number" id="fPort" value="<?= htmlspecialchars($cfg['port'] ?? '3306') ?>" />
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="fg">
            <label>Benutzer</label>
            <input class="fi" type="text" id="fUser" value="<?= htmlspecialchars($cfg['user'] ?? '') ?>" autocomplete="off" placeholder="db_user" />
          </div>
          <div class="fg">
            <label>Passwort <span style="font-weight:400;color:#4b5563">(leer = unverändert)</span></label>
            <input class="fi" type="password" id="fPass" placeholder="••••••••" autocomplete="new-password" />
          </div>
        </div>
        <div class="fg">
          <label>Zeichensatz</label>
          <select class="fi" id="fCharset">
            <option value="utf8mb4" <?= ($cfg['charset']??'utf8mb4')==='utf8mb4'?'selected':'' ?>>utf8mb4</option>
            <option value="utf8" <?= ($cfg['charset']??'')==='utf8'?'selected':'' ?>>utf8</option>
            <option value="latin1" <?= ($cfg['charset']??'')==='latin1'?'selected':'' ?>>latin1</option>
          </select>
        </div>
        <div style="display:flex;gap:8px;margin-top:4px">
          <button onclick="saveConfig()" class="btn btn-primary" style="flex:1">💾 Speichern</button>
          <button onclick="testConn()" class="btn btn-outline" style="flex:1">🔌 Testen</button>
        </div>
        <div id="cfgMsg" style="display:none;margin-top:10px;font-size:.82rem"></div>
        <?php if ($hasCfg && isset($cfg['updated_at'])): ?>
        <p style="color:#4b5563;font-size:.72rem;margin-top:10px">Zuletzt gespeichert: <?= $cfg['updated_at'] ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sync-Status & Info -->
    <div class="sync-card">
      <div class="sync-card-head">📊 Sync-Status</div>
      <div class="sync-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
          <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:12px">
            <div style="font-family:'Orbitron',sans-serif;font-size:1.2rem;color:#e8b86d" id="statTables">–</div>
            <div style="font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:4px">Tabellen</div>
          </div>
          <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:12px">
            <div style="font-family:'Orbitron',sans-serif;font-size:1.2rem;color:#e8b86d" id="statRows">–</div>
            <div style="font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:4px">Zeilen (letzter Sync)</div>
          </div>
          <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:12px">
            <div style="font-size:.82rem;color:#e2e8f0" id="statLast">–</div>
            <div style="font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:4px">Letzter Sync</div>
          </div>
          <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:12px">
            <div style="font-size:.82rem;color:#e2e8f0" id="statDuration">–</div>
            <div style="font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:4px">Dauer</div>
          </div>
        </div>

        <div style="font-size:.78rem;color:#64748b;margin-bottom:8px">Zieldatenbank (lokal):</div>
        <code style="font-size:.85rem;color:#86efac" id="targetDbLabel">DBLIVE</code>
        <span style="font-size:.72rem;color:#4b5563;margin-left:8px" id="targetUserLabel"></span>

        <div style="margin-top:16px;padding:12px;background:rgba(232,184,109,.05);border:1px solid rgba(232,184,109,.15);border-radius:8px;font-size:.78rem;color:#94a3b8;line-height:1.6">
          <strong style="color:#e8b86d">Wie funktioniert der Sync?</strong><br>
          1. Verbindet sich mit der Remote MariaDB (FiveM)<br>
          2. Liest alle Tabellen + Struktur (nur SELECT)<br>
          3. Schreibt in lokale <code id="targetDbDesc">DBLIVE</code> Datenbank<br>
          4. Kopiert Daten in 500-Zeilen-Batches<br>
          5. Protokolliert Ergebnis mit Zeitstempel
        </div>

        <div style="margin-top:14px">
          <div style="font-size:.72rem;color:#64748b;margin-bottom:6px">Tabellen auswählen (leer = alle):</div>
          <div id="tableSelector" style="display:flex;flex-wrap:wrap;gap:6px;min-height:32px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;padding:8px">
            <span style="color:#4b5563;font-size:.78rem;font-style:italic">Verbindung testen um Tabellen zu laden...</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Progress & Ergebnis -->
    <div class="sync-card sync-full" id="progressCard" style="display:none">
      <div class="sync-card-head">⏳ Sync läuft...</div>
      <div class="sync-card-body">
        <div id="syncMsg" style="font-size:.9rem;color:#e2e8f0;margin-bottom:8px">Verbinde...</div>
        <div class="progress-bar" id="progressBar" style="display:block">
          <div class="progress-fill" id="progressFill" style="width:30%"></div>
        </div>
      </div>
    </div>

    <!-- Sync-Ergebnis -->
    <div class="sync-card sync-full" id="resultCard" style="display:none">
      <div class="sync-card-head" id="resultHead">✓ Ergebnis</div>
      <div class="sync-card-body">
        <div id="resultSummary" style="font-size:.9rem;margin-bottom:14px"></div>
        <div id="resultTables" class="tbl-result"></div>
        <div id="resultErrors" style="margin-top:12px"></div>
      </div>
    </div>

    <!-- Sync-Log -->
    <div class="sync-card sync-full">
      <div class="sync-card-head">
        📋 Sync-Verlauf
        <button onclick="loadStatus()" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:.8rem">↻ Aktualisieren</button>
      </div>
      <div id="syncLog" style="font-size:.82rem;color:#94a3b8;padding:16px">Lade...</div>
    </div>

  </div>
</div>
</main>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
const CSRF = '<?= csrf_token() ?>';
let availTables = [];
let selectedTables = new Set();

async function api(action, extra = {}) {
  const r = await fetch('api/fivem_sync.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action, csrf_token: CSRF, ...extra }),
  });
  return r.json();
}

function collectCfg() {
  return {
    host:    document.getElementById('fHost').value.trim(),
    port:    document.getElementById('fPort').value.trim() || '3306',
    name:    document.getElementById('fName').value.trim(),
    user:    document.getElementById('fUser').value.trim(),
    pass:    document.getElementById('fPass').value,
    charset: document.getElementById('fCharset').value,
  };
}

async function saveConfig() {
  const msg = document.getElementById('cfgMsg');
  msg.style.display = 'block'; msg.style.color = '#64748b'; msg.textContent = 'Speichere...';
  const res = await api('save_config', collectCfg());
  msg.style.color = res.ok ? '#22c55e' : '#ef4444';
  msg.textContent = res.ok ? '✓ ' + res.message : '✗ ' + res.error;
  if (res.ok) {
    document.getElementById('cfgStatus').textContent = '✓ Konfiguriert';
    document.getElementById('cfgStatus').style.color = '#22c55e';
    document.getElementById('syncBtn').disabled = false;
  }
}

async function testConn() {
  const msg = document.getElementById('cfgMsg');
  msg.style.display = 'block'; msg.style.color = '#64748b'; msg.textContent = 'Teste Verbindung...';
  // Zuerst speichern damit frische Credentials genutzt werden
  await api('save_config', collectCfg());
  const res = await api('test');
  if (res.ok) {
    msg.style.color = '#22c55e';
    msg.textContent = '✓ Verbunden – MariaDB ' + res.version + ' · ' + res.table_count + ' Tabellen';
    availTables = res.tables || [];
    renderTableSelector(availTables);
    document.getElementById('statTables').textContent = availTables.length;
  } else {
    msg.style.color = '#ef4444';
    msg.textContent = '✗ ' + res.error;
  }
}

function renderTableSelector(tables) {
  const el = document.getElementById('tableSelector');
  if (!tables.length) { el.innerHTML = '<span style="color:#4b5563;font-size:.78rem">Keine Tabellen gefunden</span>'; return; }
  el.innerHTML = '<button onclick="selectAll(true)" style="background:rgba(255,255,255,.06);border:1px solid var(--border);color:#94a3b8;border-radius:4px;padding:2px 8px;font-size:.72rem;cursor:pointer">Alle</button>'
    + '<button onclick="selectAll(false)" style="background:rgba(255,255,255,.06);border:1px solid var(--border);color:#94a3b8;border-radius:4px;padding:2px 8px;font-size:.72rem;cursor:pointer">Keine</button>'
    + tables.map(t => `<label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;padding:2px 8px;border:1px solid var(--border);border-radius:4px;font-size:.72rem;font-family:'JetBrains Mono',monospace;color:#94a3b8;background:rgba(255,255,255,.03)"><input type="checkbox" checked onchange="toggleTable('${t}')" style="width:12px;height:12px"> ${t}</label>`).join('');
  tables.forEach(t => selectedTables.add(t));
}

function selectAll(v) {
  availTables.forEach(t => { v ? selectedTables.add(t) : selectedTables.delete(t); });
  document.querySelectorAll('#tableSelector input[type=checkbox]').forEach(cb => cb.checked = v);
}
function toggleTable(t) { selectedTables.has(t) ? selectedTables.delete(t) : selectedTables.add(t); }

async function runSync() {
  const btn = document.getElementById('syncBtn');
  btn.disabled = true; btn.textContent = '⏳ Sync läuft...';
  document.getElementById('progressCard').style.display = '';
  document.getElementById('resultCard').style.display = 'none';
  document.getElementById('progressFill').style.width = '40%';
  document.getElementById('syncMsg').textContent = 'Kopiere Daten von Remote-DB → DBLIVE...';

  const tables = selectedTables.size > 0 && selectedTables.size < availTables.length ? [...selectedTables] : null;
  const res = await api('sync', { tables });

  document.getElementById('progressCard').style.display = 'none';
  document.getElementById('resultCard').style.display = '';

  const head = document.getElementById('resultHead');
  head.textContent = res.ok ? '✓ Sync abgeschlossen' : '✗ Sync mit Fehlern';
  head.style.color = res.ok ? '#22c55e' : '#ef4444';

  document.getElementById('resultSummary').textContent = res.message || res.error || '';

  if (res.details) {
    const tc = document.getElementById('resultTables');
    tc.innerHTML = (res.details.tables || []).map(t =>
      `<span class="tbl-chip" style="background:${t.ok?'rgba(34,197,94,.1)':'rgba(239,68,68,.1)'};border:1px solid ${t.ok?'rgba(34,197,94,.25)':'rgba(239,68,68,.25)'};color:${t.ok?'#86efac':'#fca5a5'}">`
      + `${t.name}${t.ok ? ' ('+t.rows+')' : ' ✗'}</span>`
    ).join('');

    if (res.details.errors && res.details.errors.length) {
      document.getElementById('resultErrors').innerHTML = res.details.errors
        .map(e => `<div style="color:#fca5a5;font-size:.78rem;font-family:'JetBrains Mono',monospace;margin-bottom:4px">✗ ${e}</div>`).join('');
    }
  }

  btn.disabled = false; btn.textContent = '▶ Sync starten';
  loadStatus();
}

async function loadStatus() {
  const res = await api('status');
  if (!res.ok) return;

  // Zieldatenbank aus API-Antwort anzeigen
  if (res.target_db) {
    document.getElementById('targetDbLabel').textContent = res.target_db;
    document.getElementById('targetDbDesc').textContent  = res.target_db;
  }
  if (res.target_user) {
    document.getElementById('targetUserLabel').textContent = '(User: ' + res.target_user + ')';
  }

  const log = res.log;
  if (log?.last) {
    const l = log.last;
    document.getElementById('statRows').textContent     = l.rows ? l.rows.toLocaleString('de') : '–';
    document.getElementById('statLast').textContent     = l.at || '–';
    document.getElementById('statDuration').textContent = l.duration_s ? l.duration_s + 's' : '–';
    if (l.target_db) {
      document.getElementById('targetDbLabel').textContent = l.target_db;
      document.getElementById('targetDbDesc').textContent  = l.target_db;
    }
  }

  const logEl = document.getElementById('syncLog');
  const runs  = log?.runs || [];
  if (!runs.length) { logEl.textContent = 'Noch kein Sync durchgeführt.'; return; }
  logEl.innerHTML = runs.map(r =>
    `<div class="log-entry">
      <span style="color:#4b5563;white-space:nowrap;font-family:'JetBrains Mono',monospace;font-size:.78rem">${r.at}</span>
      <span class="badge ${r.ok?'badge-ok':'badge-err'}">${r.ok?'OK':'FEHLER'}</span>
      <span style="color:#e2e8f0">${r.tables || 0} Tabellen · ${(r.rows||0).toLocaleString('de')} Zeilen · ${r.duration_s||'–'}s</span>
      ${r.target_db ? `<span style="color:#86efac;font-size:.72rem">→ ${r.target_db}</span>` : ''}
      ${r.errors&&r.errors.length?`<span style="color:#fca5a5;font-size:.75rem">${r.errors.join(' | ')}</span>`:''}
    </div>`
  ).join('');
}

loadStatus();
</script>
</body>
</html>
