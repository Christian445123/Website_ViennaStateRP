<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_permission('supporter')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}

$user    = current_user();
$isAdmin = has_permission('admin');

if (!function_exists('avatarUrl')) {
    function avatarUrl(?array $u): string {
        if (empty($u['avatar']) || empty($u['id']))
            return 'https://cdn.discordapp.com/embed/avatars/0.png';
        return 'https://cdn.discordapp.com/avatars/' . $u['id'] . '/' . $u['avatar'] . '.png?size=64';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Strafenkatalog &ndash; Team &ndash; ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
  <style>
    .admin-page { padding: 90px 0 80px; min-height: 100vh; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .admin-title { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; }
    .admin-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }

    .team-note {
      display: flex; gap: 12px; align-items: flex-start; background: rgba(232,184,109,.08);
      border: 1px solid rgba(232,184,109,.25); border-left: 3px solid var(--gold);
      border-radius: 0 10px 10px 0; padding: 14px 18px; margin-bottom: 22px; font-size: .87rem;
      color: var(--text-2); line-height: 1.6;
    }
    .team-note strong { color: var(--gold); }

    .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; align-items: center; }
    .filter-bar input[type=text], .filter-bar select {
      background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; color: var(--text); font-family: inherit; font-size: .88rem;
    }
    .filter-bar input[type=text] { flex: 1; min-width: 200px; }
    .filter-bar .spacer { flex: 1; }

    .strafen-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .strafen-table th { text-align: left; color: var(--text-3); font-size: .68rem; text-transform: uppercase; letter-spacing: 1.5px; padding: 0 12px 10px; font-weight: 600; }
    .strafen-table td { padding: 12px; border-top: 1px solid var(--border); vertical-align: middle; }
    .strafen-table tr.hidden { display: none; }
    .s-paragraph { font-family: 'Orbitron', sans-serif; font-size: .78rem; color: var(--text-3); white-space: nowrap; }
    .s-dauer { color: var(--text-2); white-space: nowrap; }
    .s-count { margin-top: 12px; font-size: .78rem; color: var(--text-3); }
    .s-actions { display: flex; gap: 6px; justify-content: flex-end; white-space: nowrap; }
    .s-actions button { border-radius: 6px; padding: 4px 8px; font-size: .78rem; cursor: pointer; }
    .s-edit-btn { background: transparent; border: 1px solid var(--border-2); color: var(--text-2); }
    .s-del-btn  { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: var(--red); }

    .sanktion-badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .5px; white-space: nowrap; }
    .sanktion-warn  { background: rgba(232,184,109,.15); color: var(--gold); border: 1px solid rgba(232,184,109,.35); }
    .sanktion-kick  { background: rgba(249,115,22,.12);  color: #fb923c;    border: 1px solid rgba(249,115,22,.3); }
    .sanktion-ban   { background: rgba(239,68,68,.12);   color: var(--red); border: 1px solid rgba(239,68,68,.3); }
    .sanktion-perma { background: rgba(239,68,68,.22);   color: #fecaca;    border: 1px solid rgba(239,68,68,.55); font-weight: 900; }

    .s-modal-field { margin-bottom: 14px; }
    .s-modal-field label { font-size: .72rem; color: #94a3b8; display: block; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .5px; }
    .s-modal-field input, .s-modal-field select, .s-modal-field textarea {
      width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; color: #e2e8f0; font-family: inherit; font-size: .9rem;
    }
  </style>
</head>
<body>

<nav class="navbar scrolled" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="regelwerk.php">Regeln</a></li>
      <li><a href="players.php">Server Status</a></li>
      <?php if ($isAdmin): ?>
      <li><a href="admin.php">⚙️ Admin</a></li>
      <?php endif; ?>
      <li><a href="strafen.php" class="active">🔒 Strafen</a></li>
      <li><a href="teamregelwerk.php">📘 Teamregelwerk</a></li>
    </ul>
    <div class="nav-actions">
      <a href="dashboard.php" class="btn btn-ghost">
        <img src="<?= htmlspecialchars(avatarUrl($user)) ?>" alt="Avatar" class="nav-avatar" />
        <?= htmlspecialchars($user['global_name']) ?>
      </a>
      <a href="logout.php" class="btn btn-outline">Abmelden</a>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<main class="admin-page">
  <div class="container">

    <div class="admin-header">
      <div>
        <div class="admin-title">🔒 Strafenkatalog</div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Interne Sanktions&uuml;bersicht zum Regelwerk &middot; nur f&uuml;r Teammitglieder</p>
      </div>
      <a href="regelwerk.php" class="btn btn-outline">&larr; Zum Regelwerk</a>
    </div>

    <div class="team-note">
      <span>⚠️</span>
      <div>
        <strong>Nur zur internen Verwendung.</strong> Diese &Uuml;bersicht dient dem Team als Richtlinie beim Aussprechen von
        Sanktionen. Bei Wiederholungst&auml;tern, mehreren gleichzeitigen Verst&ouml;&szlig;en oder besonders schweren
        Einzelf&auml;llen kann die Serverleitung abweichend und h&ouml;her entscheiden.
        <?php if ($isAdmin): ?><br><strong>Als Admin</strong> kannst du Eintr&auml;ge unten direkt bearbeiten, hinzuf&uuml;gen oder l&ouml;schen.<?php endif; ?>
      </div>
    </div>

    <div class="admin-card">
      <div class="filter-bar">
        <input type="text" id="strafenSearch" placeholder="Verstoß oder §-Nummer suchen…" oninput="filterStrafen()" />
        <select id="strafenFilter" onchange="filterStrafen()">
          <option value="">Alle Sanktionen</option>
          <option value="warn">Warn</option>
          <option value="kick">Kick</option>
          <option value="ban">Ban</option>
          <option value="perma">Perm. Ausschluss</option>
        </select>
        <?php if ($isAdmin): ?>
        <div class="spacer"></div>
        <button onclick="openStrafModal()" class="btn btn-primary" style="font-size:.85rem">+ Eintrag hinzufügen</button>
        <?php endif; ?>
      </div>

      <table class="strafen-table" id="strafenTable">
        <thead>
          <tr>
            <th>§</th>
            <th>Verstoß</th>
            <th>Sanktion 1</th>
            <th>Sanktion 2</th>
            <th>Bemerkung</th>
            <?php if ($isAdmin): ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody id="strafenBody">
          <tr><td colspan="6" style="color:var(--text-2)">Lade Strafenkatalog...</td></tr>
        </tbody>
      </table>
      <p class="s-count" id="strafenCount">– Einträge</p>
    </div>

  </div>
</main>

<?php if ($isAdmin): ?>
<!-- Modal: Eintrag bearbeiten / neu -->
<div id="strafModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:520px;width:90%;max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
      <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900" id="strafModalTitle">Eintrag bearbeiten</div>
      <button onclick="closeStrafModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1">×</button>
    </div>
    <input type="hidden" id="strafId" value="0" />
    <div style="display:grid;grid-template-columns:100px 1fr;gap:14px">
      <div class="s-modal-field">
        <label>Paragraph</label>
        <input type="text" id="strafParagraph" placeholder="20" maxlength="20" />
      </div>
      <div class="s-modal-field">
        <label>Verstoß</label>
        <input type="text" id="strafVerstoss" placeholder="Beschreibung des Regelverstoßes" maxlength="255" />
      </div>
    </div>
    <div style="font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin:4px 0 6px">1. Sanktion</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="s-modal-field">
        <label>Sanktion</label>
        <select id="strafSanktion1">
          <option value="warn">Warn</option>
          <option value="kick">Kick</option>
          <option value="ban">Ban</option>
          <option value="perma">Perm. Ausschluss</option>
        </select>
      </div>
      <div class="s-modal-field">
        <label>Dauer</label>
        <input type="text" id="strafDauer1" placeholder="z. B. 3 Tage, Permanent, –" maxlength="100" />
      </div>
    </div>
    <div style="font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin:4px 0 6px">2. Sanktion (optional &ndash; z.&nbsp;B. bei Wiederholung)</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="s-modal-field">
        <label>Sanktion</label>
        <select id="strafSanktion2">
          <option value="">&ndash; Keine &ndash;</option>
          <option value="warn">Warn</option>
          <option value="kick">Kick</option>
          <option value="ban">Ban</option>
          <option value="perma">Perm. Ausschluss</option>
        </select>
      </div>
      <div class="s-modal-field">
        <label>Dauer</label>
        <input type="text" id="strafDauer2" placeholder="z. B. 14 Tage, Permanent, –" maxlength="100" />
      </div>
    </div>
    <div class="s-modal-field">
      <label>Bemerkung</label>
      <textarea id="strafBemerkung" rows="2" placeholder="Zusätzlicher Hinweis für das Team (optional)" maxlength="255"></textarea>
    </div>
    <div id="strafModalMsg" style="display:none;margin-bottom:14px;padding:8px 12px;border-radius:8px;font-size:.85rem"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">
      <button onclick="closeStrafModal()" class="btn btn-outline">Abbrechen</button>
      <button onclick="saveStrafe()" class="btn btn-primary" id="strafSaveBtn">💾 Speichern</button>
    </div>
  </div>
</div>
<?php endif; ?>

<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="index.php">Startseite</a>
      <a href="regelwerk.php">Regelwerk</a>
      <a href="players.php">Server Status</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="footer-links">
      <h5>Rechtliches</h5>
      <a href="impressum.php">Impressum</a>
      <a href="datenschutz.php">Datenschutzerkl&auml;rung</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten.</p>
  </div>
</footer>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const SANKTION_LABEL = { warn: 'Warn', kick: 'Kick', ban: 'Ban', perma: 'Perm. Ausschluss' };
let strafenData = [];

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadStrafen() {
  const body = document.getElementById('strafenBody');
  try {
    const r = await fetch('api/strafenkatalog.php');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Unbekannter Fehler');
    strafenData = d.strafen;
    renderStrafenTable();
  } catch (e) {
    body.innerHTML = '<tr><td colspan="6" style="color:var(--red)">Fehler beim Laden: ' + escHtml(e.message) + '</td></tr>';
  }
}

function sanktionCell(level, dauer) {
  if (!level) return '–';
  const dauerHtml = dauer ? ' <span class="s-dauer">&middot; ' + escHtml(dauer) + '</span>' : '';
  return '<span class="sanktion-badge sanktion-' + escHtml(level) + '">' + (SANKTION_LABEL[level] || level) + '</span>' + dauerHtml;
}

function renderStrafenTable() {
  const body = document.getElementById('strafenBody');
  if (!strafenData.length) {
    body.innerHTML = '<tr><td colspan="6" style="color:var(--text-2)">Keine Einträge vorhanden.</td></tr>';
    document.getElementById('strafenCount').textContent = '0 Einträge';
    return;
  }
  body.innerHTML = strafenData.map(s => {
    const text = (s.verstoss + ' ' + s.paragraph + ' ' + (s.bemerkung || '')).toLowerCase();
    const levels = [s.sanktion_1, s.sanktion_2].filter(Boolean).join(' ');
    const actions = IS_ADMIN
      ? `<td class="s-actions">
           <button class="s-edit-btn" onclick="openStrafModal(${s.id})">✏️</button>
           <button class="s-del-btn" onclick="deleteStrafe(${s.id})">🗑</button>
         </td>`
      : '';
    return `<tr data-level="${escHtml(levels)}" data-text="${escHtml(text)}">
      <td class="s-paragraph">§${escHtml(s.paragraph)}</td>
      <td>${escHtml(s.verstoss)}</td>
      <td>${sanktionCell(s.sanktion_1, s.dauer_1)}</td>
      <td>${sanktionCell(s.sanktion_2, s.dauer_2)}</td>
      <td style="color:var(--text-2)">${escHtml(s.bemerkung) || '–'}</td>
      ${actions}
    </tr>`;
  }).join('');
  filterStrafen();
}

function filterStrafen() {
  const q = document.getElementById('strafenSearch').value.trim().toLowerCase();
  const level = document.getElementById('strafenFilter').value;
  const rows = document.querySelectorAll('#strafenTable tbody tr[data-level]');
  let visible = 0;
  rows.forEach(row => {
    const show = (!q || row.dataset.text.includes(q)) && (!level || row.dataset.level.split(' ').includes(level));
    row.classList.toggle('hidden', !show);
    if (show) visible++;
  });
  document.getElementById('strafenCount').textContent = visible + ' Einträge';
}

<?php if ($isAdmin): ?>
function openStrafModal(id) {
  const modal = document.getElementById('strafModal');
  modal.style.display = 'flex';
  document.getElementById('strafModalMsg').style.display = 'none';
  if (id) {
    const s = strafenData.find(x => x.id == id);
    if (!s) return;
    document.getElementById('strafModalTitle').textContent = 'Eintrag bearbeiten';
    document.getElementById('strafId').value = s.id;
    document.getElementById('strafParagraph').value = s.paragraph;
    document.getElementById('strafVerstoss').value = s.verstoss;
    document.getElementById('strafSanktion1').value = s.sanktion_1;
    document.getElementById('strafDauer1').value = s.dauer_1 || '';
    document.getElementById('strafSanktion2').value = s.sanktion_2 || '';
    document.getElementById('strafDauer2').value = s.dauer_2 || '';
    document.getElementById('strafBemerkung').value = s.bemerkung || '';
  } else {
    document.getElementById('strafModalTitle').textContent = 'Neuer Eintrag';
    document.getElementById('strafId').value = '0';
    document.getElementById('strafParagraph').value = '';
    document.getElementById('strafVerstoss').value = '';
    document.getElementById('strafSanktion1').value = 'warn';
    document.getElementById('strafDauer1').value = '';
    document.getElementById('strafSanktion2').value = '';
    document.getElementById('strafDauer2').value = '';
    document.getElementById('strafBemerkung').value = '';
  }
}

function closeStrafModal() {
  document.getElementById('strafModal').style.display = 'none';
}

async function saveStrafe() {
  const id = parseInt(document.getElementById('strafId').value);
  const payload = {
    paragraph:  document.getElementById('strafParagraph').value.trim(),
    verstoss:   document.getElementById('strafVerstoss').value.trim(),
    sanktion_1: document.getElementById('strafSanktion1').value,
    dauer_1:    document.getElementById('strafDauer1').value.trim(),
    sanktion_2: document.getElementById('strafSanktion2').value,
    dauer_2:    document.getElementById('strafDauer2').value.trim(),
    bemerkung:  document.getElementById('strafBemerkung').value.trim(),
  };
  const msg = document.getElementById('strafModalMsg');
  const btn = document.getElementById('strafSaveBtn');
  btn.disabled = true;
  msg.style.display = 'none';

  const url    = id > 0 ? 'api/strafenkatalog.php?id=' + id : 'api/strafenkatalog.php';
  const method = id > 0 ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const d = await r.json();
    if (d.ok) {
      closeStrafModal();
      await loadStrafen();
    } else {
      msg.style.display = 'block';
      msg.style.background = 'rgba(239,68,68,.15)';
      msg.style.color = '#f87171';
      msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
    }
  } catch (e) {
    msg.style.display = 'block';
    msg.style.background = 'rgba(239,68,68,.15)';
    msg.style.color = '#f87171';
    msg.textContent = 'Netzwerkfehler: ' + e.message;
  } finally {
    btn.disabled = false;
  }
}

async function deleteStrafe(id) {
  if (!confirm('Eintrag wirklich löschen?')) return;
  const r = await fetch('api/strafenkatalog.php?id=' + id, { method: 'DELETE' });
  const d = await r.json();
  if (d.ok) await loadStrafen();
  else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

document.getElementById('strafModal').addEventListener('click', function(e) {
  if (e.target === this) closeStrafModal();
});
<?php endif; ?>

loadStrafen();
</script>
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
