<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/changelog.php';

require_login();
if (!has_capability('changelog.view')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}
$canEditChangelog = has_capability('changelog.edit');

$user = current_user();

if (!function_exists('avatarUrl')) {
    function avatarUrl(?array $user): string {
        if (empty($user['avatar']) || empty($user['id'])) return 'https://cdn.discordapp.com/embed/avatars/0.png';
        return 'https://cdn.discordapp.com/avatars/' . $user['id'] . '/' . $user['avatar'] . '.png?size=64';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Changelog – Admin – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    .admin-page { padding: 90px 0 80px; min-height: 100vh; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
    .admin-title { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; }
    .toggle { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
    .toggle input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; inset: 0; background: rgba(255,255,255,0.1); border-radius: 24px; cursor: pointer; transition: 0.2s; }
    .toggle-slider::before { content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.2s; }
    .toggle input:checked + .toggle-slider { background: var(--green); }
    .toggle input:checked + .toggle-slider::before { transform: translateX(20px); }
  </style>
</head>
<body>

<nav class="navbar scrolled">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="players.php">Server Status</a></li>
      <li><a href="admin.php" class="active">Admin</a></li>
      <li><a href="db_admin.php">DB Browser</a></li>
    </ul>
    <div class="nav-actions">
      <a href="dashboard.php" class="btn btn-ghost">
        <img src="<?= avatarUrl($user) ?>" alt="" class="nav-avatar" />
        <?= htmlspecialchars($user['global_name']) ?>
      </a>
      <a href="logout.php" class="btn btn-outline">Abmelden</a>
    </div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
  </div>
</nav>

<main class="admin-page">
  <div class="container">
    <div class="admin-header">
      <div>
        <div class="admin-title">📝 Changelog</div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Einträge für die Changelog-Seite anlegen, bearbeiten oder löschen. Der neueste veröffentlichte Eintrag erscheint auf der Startseite. Sobald ein Eintrag sichtbar wird, geht automatisch eine Discord-Ankündigung raus.</p>
      </div>
      <a href="admin.php" class="btn btn-outline">← Admin</a>
    </div>

    <?php if ($canEditChangelog): ?>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-bottom:20px">
      <button onclick="processDueChangelogs()" class="btn btn-outline" style="font-size:.85rem" id="clProcessDueBtn">🔔 Fällige jetzt senden</button>
      <button onclick="openClModal()" class="btn btn-primary" style="font-size:.85rem">+ Eintrag hinzufügen</button>
    </div>
    <?php endif; ?>

    <div id="clList" style="display:flex;flex-direction:column;gap:8px">
      <div style="color:var(--text-2);font-size:.9rem">Lade Changelog...</div>
    </div>
  </div>
</main>

<!-- Modal: Changelog-Eintrag bearbeiten / neu -->
<div id="clModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:560px;width:90%;max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
      <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900" id="clModalTitle">Eintrag bearbeiten</div>
      <button onclick="closeClModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1">×</button>
    </div>
    <input type="hidden" id="clId" value="0" />
    <div style="display:grid;grid-template-columns:120px 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Version</label>
        <input type="text" id="clVersion" placeholder="v1.2.0" maxlength="30"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
      </div>
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Titel</label>
        <input type="text" id="clTitle" placeholder="Neues Bankensystem" maxlength="150"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 160px;gap:14px;margin-bottom:14px">
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Kategorie</label>
        <select id="clTag" style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem">
          <?php foreach (CHANGELOG_TAGS as $tagKey => $tagInfo): ?>
            <option value="<?= htmlspecialchars($tagKey) ?>"><?= htmlspecialchars($tagInfo['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Datum</label>
        <input type="date" id="clDate"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
      </div>
    </div>
    <div style="margin-bottom:14px">
      <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Änderungen <span style="color:#4b5563;font-weight:400">(ein Punkt pro Zeile)</span></label>
      <textarea id="clContent" rows="6" placeholder="Neues Bankensystem hinzugefügt&#10;Bug beim Einloggen behoben&#10;Performance verbessert"
        style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.88rem;resize:vertical"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px">
      <div style="display:flex;align-items:center;gap:10px;padding-top:20px">
        <label class="toggle"><input type="checkbox" id="clPublished" checked /><span class="toggle-slider"></span></label>
        <span style="font-size:.9rem;color:var(--text)">Veröffentlicht</span>
      </div>
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Sichtbar ab <span style="color:#4b5563;font-weight:400">(optional)</span></label>
        <input type="datetime-local" id="clVisibleAt"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.85rem" />
      </div>
    </div>
    <p style="color:#4b5563;font-size:.75rem;margin-bottom:20px">Leer lassen = sofort sichtbar sobald veröffentlicht. Ein Zeitpunkt in der Zukunft versteckt den Eintrag bis dahin und macht ihn danach automatisch sichtbar (inkl. Discord-Ankündigung).</p>
    <div id="clModalMsg" style="display:none;margin-bottom:14px;padding:8px 12px;border-radius:8px;font-size:.85rem"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button onclick="closeClModal()" class="btn btn-outline">Abbrechen</button>
      <button onclick="saveChangelog()" class="btn btn-primary" id="clSaveBtn">💾 Speichern</button>
    </div>
  </div>
</div>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
// ── Changelog-Verwaltung ─────────────────────────────────────────────────────
const CAN_EDIT_CHANGELOG = <?= $canEditChangelog ? 'true' : 'false' ?>;
let clData = [];
const CL_TAG_META = <?= json_encode(CHANGELOG_TAGS, JSON_UNESCAPED_UNICODE) ?>;

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadChangelogs() {
  const el = document.getElementById('clList');
  try {
    const r = await fetch('api/changelogs.php?all=1');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (!d.ok) {
      el.innerHTML = '<span style="color:var(--red)">Fehler: ' + escHtml(d.error) + '</span>';
      return;
    }
    clData = d.changelogs;
    renderClList();
  } catch(e) {
    el.innerHTML = '<span style="color:#fca5a5;font-size:.85rem">❌ Changelog konnte nicht geladen werden: ' + escHtml(e.message) + '</span>';
  }
}

function clFormatDateTime(mysqlStr) {
  if (!mysqlStr) return '';
  const d = new Date(mysqlStr.replace(' ', 'T'));
  if (isNaN(d)) return mysqlStr;
  const p = n => String(n).padStart(2, '0');
  return `${p(d.getDate())}.${p(d.getMonth()+1)}.${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function clStatusBadge(c) {
  if (c.status === 'draft') {
    return `<span style="font-size:.72rem;padding:2px 8px;border-radius:999px;background:rgba(100,116,139,.15);color:#94a3b8;border:1px solid rgba(100,116,139,.3)">Entwurf</span>`;
  }
  if (c.status === 'scheduled') {
    return `<span style="font-size:.72rem;padding:2px 8px;border-radius:999px;background:rgba(124,58,237,.15);color:#c4b5fd;border:1px solid rgba(124,58,237,.3)">Geplant · ${escHtml(clFormatDateTime(c.visible_at))}</span>`;
  }
  return `<span style="font-size:.72rem;padding:2px 8px;border-radius:999px;background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.3)">Veröffentlicht</span>`;
}

function renderClList() {
  const el = document.getElementById('clList');
  if (!clData.length) { el.innerHTML = '<div style="color:var(--text-2);font-size:.9rem">Keine Changelog-Einträge vorhanden.</div>'; return; }
  el.innerHTML = clData.map(c => {
    const meta = CL_TAG_META[c.tag] || { label: c.tag, color: '#64748b' };
    return `
    <div style="display:flex;align-items:center;gap:14px;background:var(--bg-card);border:1px solid var(--border);border-left:3px solid ${escHtml(meta.color)};border-radius:10px;padding:12px 16px" data-id="${c.id}">
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
          <span style="font-size:.68rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:2px 8px;border-radius:999px;background:${escHtml(meta.color)}1a;color:${escHtml(meta.color)}">${escHtml(meta.label)}</span>
          ${c.version ? `<span style="font-family:'Orbitron',sans-serif;font-size:.75rem;color:var(--gold)">${escHtml(c.version)}</span>` : ''}
          <span style="font-size:.75rem;color:var(--text-3)">${escHtml(c.entry_date)}</span>
          ${c.discord_sent_at ? `<span title="Discord-Ankündigung verschickt am ${escHtml(clFormatDateTime(c.discord_sent_at))}" style="font-size:.78rem">📨</span>` : ''}
        </div>
        <div style="font-size:.9rem;font-weight:700">${escHtml(c.title)}</div>
      </div>
      <div style="flex-shrink:0;text-align:right">
        ${clStatusBadge(c)}
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        ${CAN_EDIT_CHANGELOG ? `
        <button onclick="openClModal(${c.id})" class="btn btn-outline" style="padding:5px 12px;font-size:.8rem">✏️</button>
        <button onclick="deleteChangelog(${c.id})" style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:8px;padding:5px 10px;font-size:.8rem;cursor:pointer">🗑</button>
        ` : ''}
      </div>
    </div>
  `; }).join('');
}

function toDatetimeLocal(mysqlStr) {
  if (!mysqlStr) return '';
  return mysqlStr.replace(' ', 'T').slice(0, 16);
}

function openClModal(id) {
  const modal = document.getElementById('clModal');
  modal.style.display = 'flex';
  document.getElementById('clModalMsg').style.display = 'none';
  if (id) {
    const c = clData.find(x => x.id == id);
    if (!c) return;
    document.getElementById('clModalTitle').textContent = 'Eintrag bearbeiten';
    document.getElementById('clId').value = c.id;
    document.getElementById('clVersion').value = c.version;
    document.getElementById('clTitle').value = c.title;
    document.getElementById('clTag').value = c.tag;
    document.getElementById('clDate').value = c.entry_date;
    document.getElementById('clContent').value = c.content;
    document.getElementById('clPublished').checked = c.published == 1;
    document.getElementById('clVisibleAt').value = toDatetimeLocal(c.visible_at);
  } else {
    document.getElementById('clModalTitle').textContent = 'Neuer Eintrag';
    document.getElementById('clId').value = '0';
    document.getElementById('clVersion').value = '';
    document.getElementById('clTitle').value = '';
    document.getElementById('clTag').value = 'feature';
    document.getElementById('clDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('clContent').value = '';
    document.getElementById('clPublished').checked = true;
    document.getElementById('clVisibleAt').value = '';
  }
}

function closeClModal() {
  document.getElementById('clModal').style.display = 'none';
}

async function saveChangelog() {
  const id = parseInt(document.getElementById('clId').value);
  const payload = {
    version:    document.getElementById('clVersion').value.trim(),
    title:      document.getElementById('clTitle').value.trim(),
    tag:        document.getElementById('clTag').value,
    entry_date: document.getElementById('clDate').value,
    content:    document.getElementById('clContent').value.trim(),
    published:  document.getElementById('clPublished').checked,
    visible_at: document.getElementById('clVisibleAt').value,
  };
  const msg = document.getElementById('clModalMsg');
  const btn = document.getElementById('clSaveBtn');
  btn.disabled = true;
  msg.style.display = 'none';

  const url = id > 0 ? 'api/changelogs.php?id=' + id : 'api/changelogs.php';
  const method = id > 0 ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const d = await r.json();
    if (d.ok) {
      closeClModal();
      await loadChangelogs();
    } else {
      msg.style.display = 'block';
      msg.style.background = 'rgba(239,68,68,.15)';
      msg.style.color = '#f87171';
      msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
    }
  } catch(e) {
    msg.style.display = 'block';
    msg.style.background = 'rgba(239,68,68,.15)';
    msg.style.color = '#f87171';
    msg.textContent = 'Netzwerkfehler: ' + e.message;
  } finally {
    btn.disabled = false;
  }
}

async function deleteChangelog(id) {
  if (!confirm('Changelog-Eintrag wirklich löschen?')) return;
  const r = await fetch('api/changelogs.php?id=' + id, { method: 'DELETE' });
  const d = await r.json();
  if (d.ok) await loadChangelogs();
  else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

async function processDueChangelogs() {
  const btn = document.getElementById('clProcessDueBtn');
  btn.disabled = true;
  const original = btn.textContent;
  btn.textContent = 'Sende...';
  try {
    const r = await fetch('api/changelogs.php?action=process_due', { method: 'POST' });
    const d = await r.json();
    if (d.ok) {
      btn.textContent = d.sent > 0 ? `✓ ${d.sent} gesendet` : '✓ Keine fällig';
      await loadChangelogs();
    } else {
      btn.textContent = '✗ Fehler';
    }
  } catch (e) {
    btn.textContent = '✗ Netzwerkfehler';
  } finally {
    setTimeout(() => { btn.textContent = original; btn.disabled = false; }, 2500);
  }
}

document.getElementById('clModal').addEventListener('click', function(e) {
  if (e.target === this) closeClModal();
});

loadChangelogs();
</script>
</body>
</html>
