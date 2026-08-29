<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_capability('fraktionen.view')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}
$canEditFraktionen = has_capability('fraktionen.edit');

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
  <title>Fraktionen – Admin – ViennaStateRP</title>
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
        <div class="admin-title">🏙 Fraktionen</div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Fraktionen auf der Startseite bearbeiten, hinzufügen oder löschen</p>
      </div>
      <a href="admin.php" class="btn btn-outline">← Admin</a>
    </div>

    <?php if ($canEditFraktionen): ?>
    <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:20px">
      <button onclick="openFrakModal()" class="btn btn-primary" style="font-size:.85rem">+ Fraktion hinzufügen</button>
    </div>
    <?php endif; ?>

    <div id="frakList" style="display:flex;flex-direction:column;gap:8px">
      <div style="color:var(--text-2);font-size:.9rem">Lade Fraktionen...</div>
    </div>
  </div>
</main>

<!-- Modal: Fraktion bearbeiten / neu -->
<div id="frakModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:540px;width:90%;max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
      <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900" id="frakModalTitle">Fraktion bearbeiten</div>
      <button onclick="closeFrakModal()" style="background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1">×</button>
    </div>
    <input type="hidden" id="frakId" value="0" />
    <div style="display:grid;grid-template-columns:80px 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Emoji</label>
        <input type="text" id="frakEmoji" placeholder="👮" maxlength="5"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:1.3rem;text-align:center" />
      </div>
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Name</label>
        <input type="text" id="frakName" placeholder="Landespolizeidirektion Wien" maxlength="100"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 120px;gap:14px;margin-bottom:14px">
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Typ-Label</label>
        <input type="text" id="frakTyp" placeholder="STAATLICH · LEGAL" maxlength="60"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
      </div>
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Farbe</label>
        <input type="color" id="frakFarbe" value="#3b82f6"
          style="width:100%;height:39px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:2px 4px;cursor:pointer" />
      </div>
    </div>
    <div style="margin-bottom:14px">
      <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Beschreibung</label>
      <textarea id="frakBeschreibung" rows="3" placeholder="Kurze Beschreibung der Fraktion..."
        style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.88rem;resize:vertical"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
      <div>
        <label style="font-size:.72rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Slots-Label</label>
        <input type="text" id="frakSlots" placeholder="Offen" maxlength="50"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.9rem" />
      </div>
      <div style="display:flex;align-items:flex-end;padding-bottom:2px">
        <label class="toggle" style="margin-right:10px"><input type="checkbox" id="frakAktiv" checked /><span class="toggle-slider"></span></label>
        <span style="font-size:.9rem;color:var(--text)">Sichtbar</span>
      </div>
    </div>
    <div id="frakModalMsg" style="display:none;margin-bottom:14px;padding:8px 12px;border-radius:8px;font-size:.85rem"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button onclick="closeFrakModal()" class="btn btn-outline">Abbrechen</button>
      <button onclick="saveFraktion()" class="btn btn-primary" id="frakSaveBtn">💾 Speichern</button>
    </div>
  </div>
</div>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
const CAN_EDIT_FRAKTIONEN = <?= $canEditFraktionen ? 'true' : 'false' ?>;

// ── Fraktionen-Verwaltung ──────────────────────────────────────────────────────
let frakData = [];

async function loadFraktionen() {
  const el = document.getElementById('frakList');
  try {
    const r = await fetch('api/fraktionen.php');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (!d.ok) {
      el.innerHTML = '<span style="color:var(--red)">Fehler: ' + escHtml(d.error) + '</span>';
      return;
    }
    frakData = d.fraktionen;
    renderFrakList();
  } catch(e) {
    el.innerHTML = '<span style="color:#fca5a5;font-size:.85rem">❌ Fraktionen konnten nicht geladen werden: ' + escHtml(e.message) + '</span>';
  }
}

function renderFrakList() {
  const el = document.getElementById('frakList');
  if (!frakData.length) { el.innerHTML = '<div style="color:var(--text-2);font-size:.9rem">Keine Fraktionen vorhanden.</div>'; return; }
  el.innerHTML = frakData.map(f => `
    <div style="display:flex;align-items:center;gap:14px;background:var(--bg-card);border:1px solid var(--border);border-left:3px solid ${escHtml(f.farbe)};border-radius:10px;padding:12px 16px" data-id="${f.id}">
      <span style="font-size:1.5rem;flex-shrink:0">${escHtml(f.emoji)}</span>
      <div style="flex:1;min-width:0">
        <div style="font-size:.9rem;font-weight:700;margin-bottom:2px">${escHtml(f.name)}</div>
        <div style="font-size:.72rem;color:${escHtml(f.farbe)};font-weight:700;letter-spacing:1px">${escHtml(f.typ)}</div>
      </div>
      <div style="flex-shrink:0;text-align:right">
        <div style="font-size:.78rem;color:var(--text-2);margin-bottom:4px">SLOTS: <strong>${escHtml(f.slots_label)}</strong></div>
        <div style="font-size:.72rem;padding:2px 8px;border-radius:999px;display:inline-block;${f.aktiv == 1 ? 'background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.3)' : 'background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)'}">${f.aktiv == 1 ? 'Sichtbar' : 'Versteckt'}</div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        ${CAN_EDIT_FRAKTIONEN ? `
        <button onclick="openFrakModal(${f.id})" class="btn btn-outline" style="padding:5px 12px;font-size:.8rem">✏️</button>
        <button onclick="deleteFraktion(${f.id})" style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:8px;padding:5px 10px;font-size:.8rem;cursor:pointer">🗑</button>
        ` : ''}
      </div>
    </div>
  `).join('');
}

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function openFrakModal(id) {
  const modal = document.getElementById('frakModal');
  modal.style.display = 'flex';
  document.getElementById('frakModalMsg').style.display = 'none';
  if (id) {
    const f = frakData.find(x => x.id == id);
    if (!f) return;
    document.getElementById('frakModalTitle').textContent = 'Fraktion bearbeiten';
    document.getElementById('frakId').value = f.id;
    document.getElementById('frakEmoji').value = f.emoji;
    document.getElementById('frakName').value = f.name;
    document.getElementById('frakTyp').value = f.typ;
    document.getElementById('frakFarbe').value = f.farbe;
    document.getElementById('frakBeschreibung').value = f.beschreibung;
    document.getElementById('frakSlots').value = f.slots_label;
    document.getElementById('frakAktiv').checked = f.aktiv == 1;
  } else {
    document.getElementById('frakModalTitle').textContent = 'Neue Fraktion';
    document.getElementById('frakId').value = '0';
    document.getElementById('frakEmoji').value = '';
    document.getElementById('frakName').value = '';
    document.getElementById('frakTyp').value = '';
    document.getElementById('frakFarbe').value = '#3b82f6';
    document.getElementById('frakBeschreibung').value = '';
    document.getElementById('frakSlots').value = 'Offen';
    document.getElementById('frakAktiv').checked = true;
  }
}

function closeFrakModal() {
  document.getElementById('frakModal').style.display = 'none';
}

async function saveFraktion() {
  const id = parseInt(document.getElementById('frakId').value);
  const payload = {
    emoji:        document.getElementById('frakEmoji').value.trim(),
    name:         document.getElementById('frakName').value.trim(),
    typ:          document.getElementById('frakTyp').value.trim(),
    farbe:        document.getElementById('frakFarbe').value,
    beschreibung: document.getElementById('frakBeschreibung').value.trim(),
    slots_label:  document.getElementById('frakSlots').value.trim(),
    aktiv:        document.getElementById('frakAktiv').checked,
  };
  const msg = document.getElementById('frakModalMsg');
  const btn = document.getElementById('frakSaveBtn');
  btn.disabled = true;
  msg.style.display = 'none';

  const url = id > 0 ? 'api/fraktionen.php?id=' + id : 'api/fraktionen.php';
  const method = id > 0 ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const d = await r.json();
    if (d.ok) {
      closeFrakModal();
      await loadFraktionen();
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

async function deleteFraktion(id) {
  if (!confirm('Fraktion wirklich löschen?')) return;
  const r = await fetch('api/fraktionen.php?id=' + id, { method: 'DELETE' });
  const d = await r.json();
  if (d.ok) await loadFraktionen();
  else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

// Modal schließen bei Klick außerhalb
document.getElementById('frakModal').addEventListener('click', function(e) {
  if (e.target === this) closeFrakModal();
});

loadFraktionen();
</script>
</body>
</html>
