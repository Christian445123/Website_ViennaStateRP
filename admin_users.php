<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_capability('users.view')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}
$canChangeRole = has_capability('users.change_role');
$canBan        = has_capability('users.ban');
$canResetPw    = has_capability('users.reset_password');
$canVerify     = has_capability('users.verify');

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
  <title>Benutzerverwaltung – Admin – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    .admin-page { padding: 90px 0 80px; min-height: 100vh; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
    .admin-title { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; }
    .admin-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }

    .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .filter-bar input[type=text], .filter-bar select {
      background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; color: var(--text); font-family: inherit; font-size: .88rem;
    }
    .filter-bar input[type=text] { flex: 1; min-width: 200px; }

    .user-table { width: 100%; border-collapse: collapse; font-size: .87rem; }
    .user-table th { text-align: left; color: var(--text-3); font-size: .68rem; text-transform: uppercase; letter-spacing: 1.5px; padding: 0 10px 10px; font-weight: 600; }
    .user-table td { padding: 10px; border-top: 1px solid var(--border); vertical-align: middle; }
    .u-identity { display: flex; align-items: center; gap: 10px; }
    .u-avatar { width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; }
    .u-name { font-weight: 700; color: var(--text); }
    .u-sub { font-size: .75rem; color: var(--text-3); }

    .role-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .5px; white-space: nowrap; }

    .status-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .7rem; font-weight: 700; margin-right: 4px; }
    .status-ok      { background: rgba(34,197,94,.15);  color: #86efac; border: 1px solid rgba(34,197,94,.3); }
    .status-warn    { background: rgba(100,116,139,.15);color: #94a3b8; border: 1px solid rgba(100,116,139,.3); }
    .status-danger  { background: rgba(239,68,68,.15);  color: #f87171; border: 1px solid rgba(239,68,68,.3); }

    .role-select {
      background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
      color: var(--text); font-family: inherit; font-size: .8rem; padding: 5px 8px;
    }
    .u-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .u-btn {
      border-radius: 7px; padding: 5px 10px; font-size: .76rem; cursor: pointer; border: 1px solid var(--border);
      background: rgba(255,255,255,.03); color: var(--text-2);
    }
    .u-btn:hover { background: var(--bg-hover); }
    .u-btn.danger { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.3); color: #f87171; }
    .u-btn.ok     { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.3); color: #86efac; }

    .pagination { display: flex; align-items: center; gap: 10px; justify-content: flex-end; margin-top: 18px; font-size: .85rem; color: var(--text-2); }

    /* Modal */
    .u-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 999; align-items: center; justify-content: center; }
    .u-modal-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; max-width: 420px; width: 90%; }
    .u-modal-title { font-family: 'Orbitron', sans-serif; font-size: 1rem; font-weight: 900; margin-bottom: 18px; }
    .u-modal-box input, .u-modal-box textarea {
      width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; color: var(--text); font-family: inherit; font-size: .88rem; margin-bottom: 14px; resize: vertical;
    }
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
        <div class="admin-title">👥 Benutzerverwaltung</div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Mitglieder verwalten, Rollen vergeben, sperren, verifizieren</p>
      </div>
      <a href="admin.php" class="btn btn-outline">← Admin</a>
    </div>

    <div class="admin-card">
      <div class="filter-bar">
        <input type="text" id="uSearch" placeholder="Suche nach Name, Username oder E-Mail..." />
        <select id="uRoleFilter">
          <option value="">Alle Rollen</option>
          <?php foreach (array_reverse(role_options(), true) as $slug => $label): ?>
          <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="uStatusFilter">
          <option value="">Alle Status</option>
          <option value="verified">Verifiziert</option>
          <option value="unverified">Nicht verifiziert</option>
          <option value="banned">Gesperrt</option>
        </select>
      </div>

      <div style="overflow-x:auto">
        <table class="user-table">
          <thead>
            <tr>
              <th>Mitglied</th>
              <th>Rolle</th>
              <th>Status</th>
              <th>Registriert</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody id="uTableBody">
            <tr><td colspan="5" style="color:var(--text-2)">Lade Mitglieder...</td></tr>
          </tbody>
        </table>
      </div>

      <div class="pagination" id="uPagination"></div>
    </div>
  </div>
</main>

<!-- Modal: Sperren -->
<div class="u-modal" id="banModal">
  <div class="u-modal-box">
    <div class="u-modal-title">🔒 Mitglied sperren</div>
    <input type="hidden" id="banUserId" />
    <textarea id="banReason" rows="3" placeholder="Grund (optional)"></textarea>
    <div id="banModalMsg" style="display:none;margin-bottom:14px;padding:8px 12px;border-radius:8px;font-size:.85rem"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="closeBanModal()">Abbrechen</button>
      <button class="btn btn-primary" style="background:var(--red);border-color:var(--red)" onclick="confirmBan()">Sperren</button>
    </div>
  </div>
</div>

<!-- Modal: Passwort zurücksetzen -->
<div class="u-modal" id="pwModal">
  <div class="u-modal-box">
    <div class="u-modal-title">🔑 Passwort zurücksetzen</div>
    <input type="hidden" id="pwUserId" />
    <input type="text" id="pwNew" placeholder="Neues Passwort (min. 8 Zeichen)" autocomplete="off" />
    <div id="pwModalMsg" style="display:none;margin-bottom:14px;padding:8px 12px;border-radius:8px;font-size:.85rem"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="closePwModal()">Abbrechen</button>
      <button class="btn btn-primary" onclick="confirmPw()">Speichern</button>
    </div>
  </div>
</div>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
const ROLE_LABELS = <?= json_encode(VSRP_ROLE_LABELS, JSON_UNESCAPED_UNICODE) ?>;
const ROLE_COLORS = <?= json_encode(VSRP_ROLE_COLORS, JSON_UNESCAPED_UNICODE) ?>;
const CAN_CHANGE_ROLE = <?= $canChangeRole ? 'true' : 'false' ?>;
const CAN_BAN         = <?= $canBan ? 'true' : 'false' ?>;
const CAN_RESET_PW    = <?= $canResetPw ? 'true' : 'false' ?>;
const CAN_VERIFY      = <?= $canVerify ? 'true' : 'false' ?>;

let uPage = 1;
let uTotal = 0;
const U_PER_PAGE = 50;
let uSearchTimer = null;

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function roleBadge(role) {
  const label = ROLE_LABELS[role] || role;
  const color = ROLE_COLORS[role] || '#94a3b8';
  return `<span class="role-badge" style="background:${color}1f;color:${color};border:1px solid ${color}4d">${escHtml(label)}</span>`;
}

function fmtDate(mysqlStr) {
  if (!mysqlStr) return '–';
  const d = new Date(mysqlStr.replace(' ', 'T'));
  if (isNaN(d)) return mysqlStr;
  const p = n => String(n).padStart(2, '0');
  return `${p(d.getDate())}.${p(d.getMonth()+1)}.${d.getFullYear()}`;
}

function avatarUrl(u) {
  if (!u.avatar || !u.discord_id) return 'https://cdn.discordapp.com/embed/avatars/0.png';
  return `https://cdn.discordapp.com/avatars/${u.discord_id}/${u.avatar}.png?size=64`;
}

function buildQuery() {
  const params = new URLSearchParams();
  const q = document.getElementById('uSearch').value.trim();
  const role = document.getElementById('uRoleFilter').value;
  const status = document.getElementById('uStatusFilter').value;
  if (q) params.set('q', q);
  if (role) params.set('role', role);
  if (status) params.set('status', status);
  params.set('page', uPage);
  return params.toString();
}

async function loadUsers() {
  const tbody = document.getElementById('uTableBody');
  tbody.innerHTML = '<tr><td colspan="5" style="color:var(--text-2)">Lade Mitglieder...</td></tr>';
  try {
    const r = await fetch('api/admin_users.php?' + buildQuery());
    const d = await r.json();
    if (!d.ok) {
      tbody.innerHTML = `<tr><td colspan="5" style="color:var(--red)">Fehler: ${escHtml(d.error)}</td></tr>`;
      return;
    }
    uTotal = d.total;
    renderUsers(d.users);
    renderPagination();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5" style="color:#fca5a5">❌ Netzwerkfehler: ${escHtml(e.message)}</td></tr>`;
  }
}

function renderUsers(users) {
  const tbody = document.getElementById('uTableBody');
  if (!users.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="color:var(--text-2)">Keine Mitglieder gefunden.</td></tr>';
    return;
  }
  tbody.innerHTML = users.map(u => `
    <tr data-id="${u.id}">
      <td>
        <div class="u-identity">
          <img class="u-avatar" src="${avatarUrl(u)}" alt="" />
          <div>
            <div class="u-name">${escHtml(u.global_name || u.username)}</div>
            <div class="u-sub">@${escHtml(u.username)}</div>
          </div>
        </div>
      </td>
      <td>
        ${CAN_CHANGE_ROLE
          ? `<select class="role-select" onchange="changeRole(${u.id}, this.value)">
               ${Object.keys(ROLE_LABELS).map(r => `<option value="${r}" ${r === u.role ? 'selected' : ''}>${escHtml(ROLE_LABELS[r])}</option>`).join('')}
             </select>`
          : roleBadge(u.role)}
      </td>
      <td>
        ${u.is_verified == 1 ? '<span class="status-pill status-ok">Verifiziert</span>' : '<span class="status-pill status-warn">Unverifiziert</span>'}
        ${u.is_banned == 1 ? `<span class="status-pill status-danger" title="${escHtml(u.ban_reason || '')}">Gesperrt</span>` : ''}
      </td>
      <td class="u-sub">${fmtDate(u.created_at)}</td>
      <td>
        <div class="u-actions">
          ${CAN_BAN ? (u.is_banned == 1
            ? `<button class="u-btn ok" onclick="unban(${u.id})">Entsperren</button>`
            : `<button class="u-btn danger" onclick="openBanModal(${u.id})">Sperren</button>`) : ''}
          ${CAN_VERIFY ? `<button class="u-btn" onclick="toggleVerify(${u.id}, ${u.is_verified == 1 ? 'false' : 'true'})">${u.is_verified == 1 ? 'Verifizierung entfernen' : 'Verifizieren'}</button>` : ''}
          ${CAN_RESET_PW ? `<button class="u-btn" onclick="openPwModal(${u.id})">Passwort</button>` : ''}
        </div>
      </td>
    </tr>
  `).join('');
}

function renderPagination() {
  const el = document.getElementById('uPagination');
  const pages = Math.max(1, Math.ceil(uTotal / U_PER_PAGE));
  el.innerHTML = `
    <span>Seite ${uPage} / ${pages} · ${uTotal} Mitglieder</span>
    <button class="btn btn-outline" style="padding:4px 12px" ${uPage <= 1 ? 'disabled' : ''} onclick="changePage(-1)">‹</button>
    <button class="btn btn-outline" style="padding:4px 12px" ${uPage >= pages ? 'disabled' : ''} onclick="changePage(1)">›</button>
  `;
}

function changePage(delta) {
  uPage = Math.max(1, uPage + delta);
  loadUsers();
}

async function changeRole(id, role) {
  const r = await fetch(`api/admin_users.php?id=${id}&action=role`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ role }),
  });
  const d = await r.json();
  if (!d.ok) { alert('Fehler: ' + (d.error || 'Unbekannt')); loadUsers(); }
}

async function unban(id) {
  if (!confirm('Mitglied wirklich entsperren?')) return;
  const r = await fetch(`api/admin_users.php?id=${id}&action=unban`, { method: 'POST' });
  const d = await r.json();
  if (d.ok) loadUsers(); else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

async function toggleVerify(id, verified) {
  const r = await fetch(`api/admin_users.php?id=${id}&action=verify`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ verified }),
  });
  const d = await r.json();
  if (d.ok) loadUsers(); else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

function openBanModal(id) {
  document.getElementById('banUserId').value = id;
  document.getElementById('banReason').value = '';
  document.getElementById('banModalMsg').style.display = 'none';
  document.getElementById('banModal').style.display = 'flex';
}
function closeBanModal() { document.getElementById('banModal').style.display = 'none'; }

async function confirmBan() {
  const id = document.getElementById('banUserId').value;
  const reason = document.getElementById('banReason').value.trim();
  const r = await fetch(`api/admin_users.php?id=${id}&action=ban`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ reason }),
  });
  const d = await r.json();
  if (d.ok) { closeBanModal(); loadUsers(); }
  else {
    const msg = document.getElementById('banModalMsg');
    msg.style.display = 'block'; msg.style.background = 'rgba(239,68,68,.15)'; msg.style.color = '#f87171';
    msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
  }
}

function openPwModal(id) {
  document.getElementById('pwUserId').value = id;
  document.getElementById('pwNew').value = '';
  document.getElementById('pwModalMsg').style.display = 'none';
  document.getElementById('pwModal').style.display = 'flex';
}
function closePwModal() { document.getElementById('pwModal').style.display = 'none'; }

async function confirmPw() {
  const id = document.getElementById('pwUserId').value;
  const password = document.getElementById('pwNew').value;
  const r = await fetch(`api/admin_users.php?id=${id}&action=reset_password`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ password }),
  });
  const d = await r.json();
  if (d.ok) { closePwModal(); alert('Passwort erfolgreich zurückgesetzt.'); }
  else {
    const msg = document.getElementById('pwModalMsg');
    msg.style.display = 'block'; msg.style.background = 'rgba(239,68,68,.15)'; msg.style.color = '#f87171';
    msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
  }
}

document.getElementById('uSearch').addEventListener('input', () => {
  clearTimeout(uSearchTimer);
  uSearchTimer = setTimeout(() => { uPage = 1; loadUsers(); }, 350);
});
document.getElementById('uRoleFilter').addEventListener('change', () => { uPage = 1; loadUsers(); });
document.getElementById('uStatusFilter').addEventListener('change', () => { uPage = 1; loadUsers(); });

document.getElementById('banModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeBanModal(); });
document.getElementById('pwModal').addEventListener('click', e => { if (e.target === e.currentTarget) closePwModal(); });

loadUsers();
</script>
</body>
</html>
