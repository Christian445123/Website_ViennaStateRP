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

    <div class="admin-card">
      <div class="group-title">🔗 Discord-Rollen-Zuordnung</div>
      <p style="color:var(--text-2);font-size:.86rem;margin-bottom:14px">
        Ordnet Discord-Rollen automatisch einer Website-Rolle zu &ndash; z.&nbsp;B. bekommt jemand mit der Discord-Rolle
        „Test-Supporter&ldquo; beim n&auml;chsten Login automatisch auch „Test-Supporter&ldquo; auf der Website.
        Es wird dabei <strong>nur hochgestuft, nie automatisch degradiert</strong> &ndash; eine manuell vergebene h&ouml;here
        Rolle bleibt erhalten. Hat jemand mehrere zugeordnete Discord-Rollen, gewinnt die h&ouml;chste.
        <strong>Websitenmanager</strong> kann aus Sicherheitsgr&uuml;nden nicht per Discord-Rolle vergeben werden &ndash;
        nur manuell &uuml;ber die Benutzerverwaltung.
      </p>
      <div id="discordBotWarning" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-left:3px solid var(--red);border-radius:0 8px 8px 0;padding:10px 14px;margin-bottom:14px;font-size:.82rem;color:var(--text-2)">
        ⚠️ Kein Discord-Bot konfiguriert (<code>DISCORD_BOT_TOKEN</code> / <code>DISCORD_GUILD_ID</code> in der <code>.env</code>) &ndash;
        die Zuordnungen unten werden erst wirksam, sobald beides gesetzt ist.
      </div>

      <div id="discordMapList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">
        <span style="color:var(--text-2);font-size:.86rem">Lade Zuordnungen...</span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 180px 1fr auto;gap:10px;align-items:end">
        <div>
          <label style="font-size:.7rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Discord-Rollen-ID</label>
          <input type="text" id="dmRoleId" placeholder="z. B. 123456789012345678" style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.88rem" />
        </div>
        <div>
          <label style="font-size:.7rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Website-Rolle</label>
          <select id="dmWebsiteRole" style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.88rem"></select>
        </div>
        <div>
          <label style="font-size:.7rem;color:#94a3b8;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Bezeichnung (optional)</label>
          <input type="text" id="dmLabel" placeholder="z. B. @Test-Supporter" style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:#e2e8f0;font-family:inherit;font-size:.88rem" />
        </div>
        <button onclick="addDiscordMapping()" class="btn btn-primary" style="white-space:nowrap">+ Hinzufügen</button>
      </div>
      <div id="dmMsg" style="font-size:.82rem;margin-top:10px"></div>
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

// ── Discord-Rollen-Zuordnung ─────────────────────────────────────────────────
let discordMapData = null;

async function loadDiscordMap() {
  const el = document.getElementById('discordMapList');
  try {
    const r = await fetch('api/discord_role_map.php');
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Unbekannter Fehler');
    discordMapData = d;
    document.getElementById('discordBotWarning').style.display = d.bot_configured ? 'none' : 'block';

    const roleSelect = document.getElementById('dmWebsiteRole');
    roleSelect.innerHTML = d.assignable_roles.map(r => `<option value="${r}">${escHtml(d.labels[r] || r)}</option>`).join('');

    renderDiscordMap();
  } catch (e) {
    el.innerHTML = `<span style="color:var(--red)">Fehler beim Laden: ${escHtml(e.message)}</span>`;
  }
}

function renderDiscordMap() {
  const el = document.getElementById('discordMapList');
  if (!discordMapData.mappings.length) {
    el.innerHTML = '<span style="color:var(--text-2);font-size:.86rem">Noch keine Zuordnungen &ndash; f&uuml;ge unten die erste hinzu.</span>';
    return;
  }
  el.innerHTML = discordMapData.mappings.map(m => `
    <div style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;padding:9px 14px">
      <code style="font-size:.8rem;color:var(--text-2)">${escHtml(m.discord_role_id)}</code>
      <span style="color:var(--text-3)">→</span>
      <span class="role-badge" style="background:rgba(232,184,109,.15);color:var(--gold);border:1px solid rgba(232,184,109,.35);padding:3px 10px;border-radius:999px;font-size:.76rem;font-weight:700">${escHtml(discordMapData.labels[m.website_role] || m.website_role)}</span>
      ${m.label ? `<span style="color:var(--text-3);font-size:.8rem">${escHtml(m.label)}</span>` : ''}
      <button onclick="deleteDiscordMapping(${m.id})" style="margin-left:auto;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:var(--red);border-radius:6px;padding:4px 10px;font-size:.78rem;cursor:pointer">🗑</button>
    </div>
  `).join('');
}

async function addDiscordMapping() {
  const discordRoleId = document.getElementById('dmRoleId').value.trim();
  const websiteRole   = document.getElementById('dmWebsiteRole').value;
  const label          = document.getElementById('dmLabel').value.trim();
  const msg = document.getElementById('dmMsg');
  msg.textContent = '';

  try {
    const r = await fetch('api/discord_role_map.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ discord_role_id: discordRoleId, website_role: websiteRole, label }),
    });
    const d = await r.json();
    if (d.ok) {
      document.getElementById('dmRoleId').value = '';
      document.getElementById('dmLabel').value = '';
      await loadDiscordMap();
    } else {
      msg.style.color = 'var(--red)';
      msg.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
    }
  } catch (e) {
    msg.style.color = 'var(--red)';
    msg.textContent = 'Netzwerkfehler: ' + e.message;
  }
}

async function deleteDiscordMapping(id) {
  if (!confirm('Zuordnung wirklich löschen?')) return;
  const r = await fetch('api/discord_role_map.php?id=' + id, { method: 'DELETE' });
  const d = await r.json();
  if (d.ok) await loadDiscordMap();
  else alert('Fehler: ' + (d.error || 'Unbekannt'));
}

loadDiscordMap();
</script>
</body>
</html>
