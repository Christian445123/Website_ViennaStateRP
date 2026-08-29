<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_permission('owner')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}

$user = current_user();

if (!function_exists('avatarUrl')) {
    function avatarUrl(?array $u): string {
        if (empty($u['avatar']) || empty($u['id'])) return 'https://cdn.discordapp.com/embed/avatars/0.png';
        return 'https://cdn.discordapp.com/avatars/' . $u['id'] . '/' . $u['avatar'] . '.png?size=64';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rollenverwaltung &ndash; Admin &ndash; ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    .admin-page { padding: 90px 0 80px; min-height: 100vh; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .admin-title { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; }
    .admin-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; margin-bottom: 18px; }
    .group-title { font-family: 'Orbitron', sans-serif; font-size: .82rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--gold); margin-bottom: 14px; }

    .team-note {
      display: flex; gap: 12px; align-items: flex-start; background: rgba(232,184,109,.08);
      border: 1px solid rgba(232,184,109,.25); border-left: 3px solid var(--gold);
      border-radius: 0 10px 10px 0; padding: 14px 18px; margin-bottom: 22px; font-size: .87rem;
      color: var(--text-2); line-height: 1.6;
    }
    .team-note strong { color: var(--gold); }

    .roles-table { width: 100%; border-collapse: collapse; font-size: .84rem; white-space: nowrap; }
    .roles-table th { text-align: center; color: var(--text-3); font-size: .66rem; text-transform: uppercase; letter-spacing: 1px; padding: 0 10px 10px; font-weight: 600; max-width: 140px; white-space: normal; }
    .roles-table th:first-child { text-align: left; }
    .roles-table td { padding: 8px 10px; border-top: 1px solid var(--border); vertical-align: middle; text-align: center; }
    .roles-table td:first-child { text-align: left; }
    .role-name-cell { display: flex; align-items: center; gap: 9px; }
    .role-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .roles-table input[type=checkbox] { width: 17px; height: 17px; cursor: pointer; accent-color: var(--gold); }
    .table-scroll { overflow-x: auto; }

    .save-bar { position: sticky; bottom: 16px; display: flex; align-items: center; gap: 14px; margin-top: 18px;
      background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; }
    #rolesMsg { font-size: .85rem; }
  </style>
</head>
<body>

<nav class="navbar scrolled">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="players.php">Server Status</a></li>
      <li><a href="admin.php">Admin</a></li>
      <li><a href="admin_rollen.php" class="active">Rollenverwaltung</a></li>
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
        <div class="admin-title">🛡️ Rollenverwaltung</div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Legt pro Rolle fest, welche Funktionen der Website nutzbar sind</p>
      </div>
      <a href="admin.php" class="btn btn-outline">&larr; Admin</a>
    </div>

    <div class="team-note">
      <span>ℹ️</span>
      <div>
        <strong>Websitenmanager</strong> hat immer uneingeschränkten Vollzugriff auf alle Funktionen und wird hier nicht
        aufgeführt. Für alle anderen 13 Rollen kannst du unten <strong>jede einzelne Funktion der Website</strong> separat
        an- oder abschalten (Benutzerverwaltung, Server-Einstellungen, Fraktionen, Changelog, FiveM-Diagnose,
        Datenbank-Browser, Strafenkatalog, Teamregelwerk). Ist für eine Rolle nichts umgestellt, gilt weiterhin die
        normale Rang-Reihenfolge.
      </div>
    </div>

    <div id="rolesGroups">
      <div class="admin-card"><span style="color:var(--text-2)">Lade Rollenrechte...</span></div>
    </div>

    <div class="save-bar">
      <button onclick="saveRoles()" class="btn btn-primary" id="rolesSaveBtn">💾 Alles speichern</button>
      <span id="rolesMsg"></span>
    </div>
  </div>
</main>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
let roleData = null;
let pending = {}; // { "role|gate": bool } – gesammelte Änderungen seit dem letzten Speichern

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadRoles() {
  const el = document.getElementById('rolesGroups');
  try {
    const r = await fetch('api/role_permissions.php');
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Unbekannter Fehler');
    roleData = d;
    renderGroups();
  } catch (e) {
    el.innerHTML = `<div class="admin-card" style="color:var(--red)">Fehler beim Laden: ${escHtml(e.message)}</div>`;
  }
}

function roleColor(role) {
  const idx = roleData.roles.indexOf(role);
  const hue = 200 + (idx * 18) % 160;
  return `hsl(${hue}, 55%, 55%)`;
}

function renderGroups() {
  const el = document.getElementById('rolesGroups');
  el.innerHTML = Object.entries(roleData.groups).map(([groupName, caps]) => {
    const head = caps.map(c => `<th title="${escHtml(c.key)}">${escHtml(c.label)}</th>`).join('');
    const rows = roleData.roles.map(role => {
      const cells = caps.map(c => {
        const checked = roleData.matrix[role]?.[c.key] ? 'checked' : '';
        return `<td><input type="checkbox" data-role="${role}" data-gate="${c.key}" ${checked} onchange="markPending(this)" /></td>`;
      }).join('');
      return `<tr>
        <td><div class="role-name-cell"><span class="role-dot" style="background:${roleColor(role)}"></span>${escHtml(roleData.labels[role] || role)}</div></td>
        ${cells}
      </tr>`;
    }).join('');
    return `<div class="admin-card">
      <div class="group-title">${escHtml(groupName)}</div>
      <div class="table-scroll">
        <table class="roles-table">
          <thead><tr><th>Rolle</th>${head}</tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    </div>`;
  }).join('');
}

function markPending(cb) {
  pending[cb.dataset.role + '|' + cb.dataset.gate] = cb.checked;
}

async function saveRoles() {
  const entries = Object.entries(pending).map(([key, enabled]) => {
    const [role, gate] = key.split('|');
    return { role, gate, enabled };
  });

  if (!entries.length) {
    document.getElementById('rolesMsg').textContent = 'Keine Änderungen.';
    return;
  }

  const btn = document.getElementById('rolesSaveBtn');
  const msg = document.getElementById('rolesMsg');
  btn.disabled = true;
  msg.textContent = '';

  try {
    const r = await fetch('api/role_permissions.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ entries }),
    });
    const d = await r.json();
    if (d.ok) {
      msg.style.color = 'var(--green)';
      msg.textContent = `✓ Gespeichert (${d.saved} Änderungen)`;
      pending = {};
    } else {
      msg.style.color = 'var(--red)';
      msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
    }
  } catch (e) {
    msg.style.color = 'var(--red)';
    msg.textContent = 'Netzwerkfehler: ' + e.message;
  } finally {
    btn.disabled = false;
    setTimeout(() => { msg.textContent = ''; }, 4000);
  }
}

loadRoles();
</script>
</body>
</html>
