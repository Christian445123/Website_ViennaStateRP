<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/fivem_db.php';

require_login();
if (!has_permission('admin')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff – Admin-Rolle erforderlich'));
    exit;
}

$user       = current_user();
$fivemCfg   = fivem_db_load();
$hasFivemCfg = $fivemCfg !== null;
$liveDbName = env('DB_LIVE_NAME', 'db_live');
$liveDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $liveDbName);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DB_LIVE – ViennaStateRP Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    body { padding: 90px 0 80px; }

    /* ── Layout ── */
    .ldb-grid   { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
    .ldb-full   { grid-column: 1 / -1; }
    .ldb-card   { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
    .ldb-head   { padding: 12px 18px; border-bottom: 1px solid var(--border); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #64748b; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .ldb-body   { padding: 18px; }

    /* ── Status Bar ── */
    .status-bar { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .stat-pill  { flex: 1; min-width: 120px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; }
    .stat-pill .val { font-family: 'Orbitron', sans-serif; font-size: 1.15rem; font-weight: 700; color: #e8b86d; }
    .stat-pill .lbl { font-size: .65rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

    /* ── Sidebar Table List ── */
    .tbl-list   { max-height: 65vh; overflow-y: auto; }
    .tbl-item   { padding: 8px 14px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,.04); transition: background .15s; font-size: .82rem; }
    .tbl-item:hover, .tbl-item.active { background: rgba(232,184,109,.08); }
    .tbl-item.active { border-left: 2px solid #e8b86d; }
    .tbl-rows   { font-size: .7rem; color: #64748b; font-family: 'JetBrains Mono', monospace; }

    /* ── Table Browser ── */
    .data-table-wrap { overflow-x: auto; max-height: 55vh; }
    .data-table      { width: 100%; border-collapse: collapse; font-size: .78rem; font-family: 'JetBrains Mono', monospace; }
    .data-table th   { position: sticky; top: 0; background: #0f1117; padding: 8px 12px; text-align: left; color: #64748b; font-size: .68rem; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); white-space: nowrap; }
    .data-table td   { padding: 7px 12px; border-bottom: 1px solid rgba(255,255,255,.04); color: #cbd5e1; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .data-table tr:hover td { background: rgba(255,255,255,.03); }
    .null-val        { color: #374151; font-style: italic; }

    /* ── Sync Controls ── */
    .sync-controls  { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; align-items: center; }
    .auto-label     { font-size: .8rem; color: #94a3b8; display: flex; align-items: center; gap: 6px; }

    /* ── Progress ── */
    .progress-bar   { height: 5px; background: rgba(255,255,255,.06); border-radius: 999px; overflow: hidden; margin: 10px 0; display: none; }
    .progress-fill  { height: 100%; background: linear-gradient(90deg, #7c3aed, #e8b86d); border-radius: 999px; transition: width .5s; }

    /* ── Log ── */
    .log-item       { padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,.04); font-size: .78rem; display: flex; gap: 10px; align-items: baseline; }
    .log-item:last-child { border-bottom: none; }
    .log-ok         { color: #86efac; }
    .log-err        { color: #fca5a5; }

    /* ── Badges ── */
    .badge          { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .68rem; font-weight: 700; }
    .badge-ok       { background: rgba(34,197,94,.15);   color: #86efac; border: 1px solid rgba(34,197,94,.3); }
    .badge-err      { background: rgba(239,68,68,.12);   color: #fca5a5; border: 1px solid rgba(239,68,68,.3); }
    .badge-warn     { background: rgba(232,184,109,.12); color: #e8b86d; border: 1px solid rgba(232,184,109,.3); }

    /* ── Pager ── */
    .pager { display: flex; gap: 6px; margin-top: 10px; align-items: center; font-size: .8rem; color: #64748b; }
    .pager button { background: var(--bg-card); border: 1px solid var(--border); color: #e2e8f0; border-radius: 6px; padding: 4px 12px; cursor: pointer; }
    .pager button:disabled { opacity: .35; cursor: default; }

    @media (max-width: 768px) { .ldb-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<nav class="navbar scrolled">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="admin.php">Admin</a></li>
      <li><a href="db_admin.php">DB Browser</a></li>
      <li><a href="fivem_sync.php">FiveM Sync</a></li>
      <li><a href="admin_live_db.php" class="active">DB_LIVE</a></li>
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

  <!-- Header -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:900">
        🗄️ DB_LIVE <span style="color:#e8b86d">Sync</span>
      </h1>
      <p style="color:#64748b;font-size:.82rem;margin-top:5px">
        FiveM Remote-Datenbank → Lokal
        <code style="color:#86efac;background:rgba(134,239,172,.08);padding:1px 6px;border-radius:4px"><?= htmlspecialchars($liveDbName) ?></code>
        <?php if (!$hasFivemCfg): ?>
          · <span style="color:#f59e0b">⚠ FiveM-Credentials nicht konfiguriert – zuerst <a href="fivem_sync.php" style="color:#e8b86d">FiveM DB Sync</a> einrichten</span>
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="fivem_sync.php" class="btn btn-ghost" style="font-size:.8rem">⚙ FiveM Config</a>
      <a href="admin.php" class="btn btn-outline" style="font-size:.8rem">← Admin</a>
    </div>
  </div>

  <!-- Status Pills -->
  <div class="status-bar" id="statusBar">
    <div class="stat-pill"><div class="val" id="stTables">–</div><div class="lbl">Tabellen in DB_LIVE</div></div>
    <div class="stat-pill"><div class="val" id="stSize">–</div><div class="lbl">Datenbankgröße</div></div>
    <div class="stat-pill"><div class="val" id="stLastSync" style="font-size:.85rem">–</div><div class="lbl">Letzter Sync</div></div>
    <div class="stat-pill"><div class="val" id="stDuration">–</div><div class="lbl">Letzte Dauer</div></div>
    <div class="stat-pill"><div class="val" id="stRows">–</div><div class="lbl">Zeilen (letzter Sync)</div></div>
  </div>

  <!-- Sync Controls -->
  <div class="sync-controls">
    <button onclick="runSync()" class="btn btn-primary" id="syncBtn" <?= !$hasFivemCfg ? 'disabled title="FiveM-Credentials zuerst konfigurieren"' : '' ?>>
      ▶ Jetzt synchronisieren
    </button>
    <button onclick="runSync(true)" class="btn btn-outline" id="syncSelBtn" <?= !$hasFivemCfg ? 'disabled' : '' ?> style="display:none">
      ▶ Auswahl synchronisieren
    </button>
    <label class="auto-label">
      <input type="checkbox" id="autoSync" onchange="toggleAutoSync()" />
      Auto-Sync alle
    </label>
    <select id="autoInterval" style="background:var(--bg);border:1px solid var(--border);color:#e2e8f0;border-radius:6px;padding:4px 8px;font-size:.8rem">
      <option value="60">1 Min</option>
      <option value="300" selected>5 Min</option>
      <option value="600">10 Min</option>
      <option value="1800">30 Min</option>
    </select>
    <span id="autoTimer" style="font-size:.75rem;color:#64748b;display:none"></span>
    <span id="syncStatus" style="font-size:.82rem;color:#64748b;margin-left:auto"></span>
  </div>

  <!-- Progress bar -->
  <div class="progress-bar" id="progressBar">
    <div class="progress-fill" id="progressFill" style="width:5%"></div>
  </div>

  <!-- Main Grid -->
  <div class="ldb-grid">

    <!-- Sidebar: Tabellenliste -->
    <div class="ldb-card">
      <div class="ldb-head">
        📋 Tabellen
        <button onclick="loadTables()" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:.8rem;padding:0">↻</button>
      </div>
      <div class="tbl-list" id="tableList">
        <div style="padding:16px;color:#4b5563;font-size:.8rem;font-style:italic">Sync ausführen um Tabellen zu sehen...</div>
      </div>
    </div>

    <!-- Content: Table Browser -->
    <div class="ldb-card" id="browserCard">
      <div class="ldb-head">
        <span id="browserTitle">🔍 Tabelle auswählen</span>
        <div style="display:flex;gap:8px;align-items:center">
          <input type="text" id="searchInput" placeholder="Suchen..." onkeyup="filterRows()"
            style="background:var(--bg);border:1px solid var(--border);color:#e2e8f0;border-radius:6px;padding:3px 10px;font-size:.75rem;width:140px;outline:none" />
          <span id="rowCount" style="font-size:.72rem;color:#64748b"></span>
        </div>
      </div>
      <div class="ldb-body" style="padding:12px">
        <div id="browserEmpty" style="color:#4b5563;font-size:.85rem;font-style:italic;padding:20px 0">
          Wähle eine Tabelle aus der Seitenleiste.
        </div>
        <div class="data-table-wrap" id="tableWrap" style="display:none">
          <table class="data-table" id="dataTable">
            <thead id="dataHead"></thead>
            <tbody id="dataBody"></tbody>
          </table>
        </div>
        <div class="pager" id="pager" style="display:none">
          <button onclick="browsePage(-1)" id="prevBtn">← Zurück</button>
          <span id="pageInfo"></span>
          <button onclick="browsePage(1)" id="nextBtn">Weiter →</button>
          <span style="margin-left:auto">Zeilen pro Seite:
            <select id="limitSel" onchange="currentPage=1;browseTable()" style="background:var(--bg);border:1px solid var(--border);color:#e2e8f0;border-radius:4px;padding:2px 6px;font-size:.75rem">
              <option value="25">25</option>
              <option value="50" selected>50</option>
              <option value="100">100</option>
            </select>
          </span>
        </div>
      </div>
    </div>

    <!-- Sync Log -->
    <div class="ldb-card ldb-full">
      <div class="ldb-head">
        📜 Sync-Verlauf
        <button onclick="loadStatus()" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:.8rem">↻ Aktualisieren</button>
      </div>
      <div id="syncLog" style="color:#64748b;font-size:.8rem;padding:16px;font-style:italic">Lade...</div>
    </div>

  </div>
</div>
</main>

<script src="assets/js/main.js"></script>
<script>
const CSRF        = '<?= csrf_token() ?>';
const FIVEM_OK    = <?= $hasFivemCfg ? 'true' : 'false' ?>;
let currentTable  = null;
let currentPage   = 1;
let totalRows     = 0;
let autoTimer     = null;
let autoCountdown = 0;
let autoInterval  = 300;
let selectedTables = new Set();

// ── API-Helfer ────────────────────────────────────────────────────────────────
async function api(action, extra = {}) {
  const res = await fetch('api/live_db_sync.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action, csrf_token: CSRF, ...extra }),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

// ── Status laden ──────────────────────────────────────────────────────────────
async function loadStatus() {
  try {
    const d = await api('status');
    if (!d.ok) return;

    document.getElementById('stTables').textContent   = d.live_tables  ?? '–';
    document.getElementById('stSize').textContent     = d.live_size    ?? '–';

    const log = d.log;
    if (log?.last) {
      document.getElementById('stLastSync').textContent  = log.last.at       ?? '–';
      document.getElementById('stDuration').textContent  = (log.last.duration_s ?? '–') + 's';
      document.getElementById('stRows').textContent      = (log.last.rows ?? 0).toLocaleString();
    }

    renderLog(log?.runs ?? []);
  } catch (e) {
    console.error('Status-Fehler:', e);
  }
}

function renderLog(runs) {
  const el = document.getElementById('syncLog');
  if (!runs.length) { el.innerHTML = '<div style="padding:10px;font-style:italic">Noch kein Sync ausgeführt.</div>'; return; }
  el.innerHTML = runs.map(r => `
    <div class="log-item">
      <span class="badge ${r.ok ? 'badge-ok' : 'badge-err'}">${r.ok ? 'OK' : 'FEHLER'}</span>
      <span style="color:#94a3b8">${r.at}</span>
      <span>${r.tables ?? 0} Tabellen · ${(r.rows ?? 0).toLocaleString()} Zeilen · ${r.duration_s ?? 0}s</span>
      ${r.errors?.length ? `<span style="color:#fca5a5;font-size:.72rem">${r.errors.slice(0,3).join(' | ')}</span>` : ''}
    </div>
  `).join('');
}

// ── Tabellenliste laden ───────────────────────────────────────────────────────
async function loadTables() {
  const el = document.getElementById('tableList');
  el.innerHTML = '<div style="padding:16px;color:#64748b;font-size:.78rem">Lade...</div>';
  try {
    const d = await api('tables');
    if (!d.ok || !d.tables?.length) {
      el.innerHTML = '<div style="padding:16px;color:#4b5563;font-size:.78rem;font-style:italic">Keine Tabellen gefunden.</div>';
      return;
    }
    el.innerHTML = d.tables.map(t => `
      <div class="tbl-item ${currentTable === t.name ? 'active' : ''}"
           onclick="selectTable('${t.name}')" id="ti_${t.name}">
        <span>${t.name}</span>
        <span class="tbl-rows">${Number(t.est_rows ?? 0).toLocaleString()}</span>
      </div>
    `).join('');
    document.getElementById('syncSelBtn').style.display = '';
  } catch (e) {
    el.innerHTML = '<div style="padding:16px;color:#fca5a5;font-size:.78rem">Fehler: ' + e.message + '</div>';
  }
}

// ── Tabelle auswählen ─────────────────────────────────────────────────────────
function selectTable(name) {
  currentTable = name;
  currentPage  = 1;
  document.querySelectorAll('.tbl-item').forEach(el => el.classList.toggle('active', el.id === 'ti_' + name));
  browseTable();
}

// ── Tabellendaten laden ───────────────────────────────────────────────────────
async function browseTable() {
  if (!currentTable) return;
  const limit = parseInt(document.getElementById('limitSel').value);
  document.getElementById('browserTitle').textContent = '🔍 ' + currentTable;
  document.getElementById('browserEmpty').style.display = 'none';
  document.getElementById('tableWrap').style.display    = 'block';
  document.getElementById('pager').style.display        = 'flex';
  document.getElementById('dataBody').innerHTML = '<tr><td colspan="99" style="padding:20px;color:#64748b">Lade...</td></tr>';

  try {
    const d = await api('browse', { table: currentTable, page: currentPage, limit });
    if (!d.ok) { document.getElementById('dataBody').innerHTML = `<tr><td style="color:#fca5a5;padding:10px">${d.error}</td></tr>`; return; }

    totalRows = d.total;
    const totalPages = Math.ceil(totalRows / limit);
    document.getElementById('rowCount').textContent = totalRows.toLocaleString() + ' Zeilen';
    document.getElementById('pageInfo').textContent = `Seite ${currentPage} / ${totalPages}`;
    document.getElementById('prevBtn').disabled     = currentPage <= 1;
    document.getElementById('nextBtn').disabled     = currentPage >= totalPages;

    // Header
    document.getElementById('dataHead').innerHTML = '<tr>' + d.cols.map(c => `<th>${c}</th>`).join('') + '</tr>';

    // Rows
    if (!d.rows.length) {
      document.getElementById('dataBody').innerHTML = '<tr><td colspan="99" style="padding:20px;color:#64748b;font-style:italic">Keine Daten.</td></tr>';
      return;
    }
    document.getElementById('dataBody').innerHTML = d.rows.map(row =>
      '<tr>' + d.cols.map(c => {
        const v = row[c];
        if (v === null || v === undefined) return '<td class="null-val">NULL</td>';
        const s = String(v);
        return `<td title="${s.length > 60 ? s : ''}">${s.length > 80 ? s.substring(0,77) + '…' : s}</td>`;
      }).join('') + '</tr>'
    ).join('');
  } catch (e) {
    document.getElementById('dataBody').innerHTML = `<tr><td style="color:#fca5a5;padding:10px">Fehler: ${e.message}</td></tr>`;
  }
}

function browsePage(dir) {
  const limit = parseInt(document.getElementById('limitSel').value);
  const totalPages = Math.ceil(totalRows / limit);
  currentPage = Math.max(1, Math.min(totalPages, currentPage + dir));
  browseTable();
}

function filterRows() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#dataBody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── Sync ausführen ────────────────────────────────────────────────────────────
async function runSync(selOnly = false) {
  if (!FIVEM_OK) { setSyncStatus('⚠ FiveM-Credentials nicht konfiguriert', 'warn'); return; }

  const btn = document.getElementById('syncBtn');
  btn.disabled = true; btn.textContent = '⏳ Sync läuft...';
  document.getElementById('progressBar').style.display = 'block';
  animateProgress();
  setSyncStatus('Verbinde mit FiveM-Datenbank...', 'info');

  try {
    const extra = selOnly && selectedTables.size > 0 ? { tables: [...selectedTables] } : {};
    const d = await api('sync', extra);

    document.getElementById('progressBar').style.display = 'none';
    document.getElementById('progressFill').style.width  = '100%';

    if (d.ok) {
      setSyncStatus('✓ ' + d.message, 'ok');
      await loadStatus();
      await loadTables();
      if (currentTable) browseTable();
    } else {
      setSyncStatus('✗ ' + (d.error ?? 'Unbekannter Fehler'), 'err');
    }
  } catch (e) {
    setSyncStatus('✗ Fehler: ' + e.message, 'err');
    document.getElementById('progressBar').style.display = 'none';
  }
  btn.disabled = false; btn.textContent = '▶ Jetzt synchronisieren';
}

function setSyncStatus(msg, type) {
  const el = document.getElementById('syncStatus');
  el.textContent = msg;
  el.style.color = type === 'ok' ? '#86efac' : type === 'err' ? '#fca5a5' : type === 'warn' ? '#e8b86d' : '#94a3b8';
}

function animateProgress() {
  let w = 5;
  const bar = document.getElementById('progressFill');
  const iv  = setInterval(() => {
    w = w + (95 - w) * 0.06;
    bar.style.width = w + '%';
    if (w >= 94) clearInterval(iv);
  }, 400);
}

// ── Auto-Sync ─────────────────────────────────────────────────────────────────
function toggleAutoSync() {
  if (document.getElementById('autoSync').checked) {
    autoInterval  = parseInt(document.getElementById('autoInterval').value);
    autoCountdown = autoInterval;
    document.getElementById('autoTimer').style.display = '';
    autoTimer = setInterval(() => {
      autoCountdown--;
      const m = Math.floor(autoCountdown / 60), s = autoCountdown % 60;
      document.getElementById('autoTimer').textContent = `Nächster Sync in ${m > 0 ? m + 'm ' : ''}${s}s`;
      if (autoCountdown <= 0) {
        autoCountdown = autoInterval;
        runSync();
      }
    }, 1000);
  } else {
    clearInterval(autoTimer);
    autoTimer = null;
    document.getElementById('autoTimer').style.display  = 'none';
    document.getElementById('autoTimer').textContent = '';
  }
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  await loadStatus();
  await loadTables();
});
</script>
</body>
</html>
