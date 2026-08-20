<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/site_settings.php';

require_login();
if (!has_permission('admin')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}

$user     = current_user();
$settings = settings_load();

$stats = ['total' => 0, 'verified' => 0, 'admins' => 0, 'banned' => 0];
if (isset($pdo)) {
    try {
        $stats['total']    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['verified'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn();
        $stats['admins']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        $stats['banned']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn();
    } catch (\Throwable $e) {
        error_log('[Admin Hub] Stats fehlgeschlagen: ' . $e->getMessage());
    }
}

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
  <title>Admin – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    .admin-page { padding: 90px 0 80px; min-height: 100vh; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 12px; }
    .admin-title { font-family: 'Orbitron', sans-serif; font-size: 1.6rem; font-weight: 900; }
    .admin-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }

    /* Stat-Kacheln */
    .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
    .stat-tile { background: var(--bg-card); border: 1px solid var(--border); border-top: 3px solid var(--stat-color, var(--purple)); border-radius: 12px; padding: 18px 20px; }
    .stat-num { font-family: 'Orbitron', sans-serif; font-size: 2rem; font-weight: 900; color: var(--stat-color, var(--purple)); line-height: 1; }
    .stat-label { font-size: .72rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 8px; }

    /* Hub-Layout: Wartungsmodus links, Navigationskarten rechts */
    .hub-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: start; }
    @media (max-width: 900px) { .hub-layout { grid-template-columns: 1fr; } }

    .maint-status { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 10px; font-size: .88rem; font-weight: 700; margin-bottom: 20px; }
    .maint-status.on  { background: rgba(34,197,94,.12);  color: var(--green); border: 1px solid rgba(34,197,94,.3); }
    .maint-status.off { background: rgba(239,68,68,.12);  color: var(--red);   border: 1px solid rgba(239,68,68,.3); }
    .maint-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
    .maint-msg {
      width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; color: var(--text); font-family: inherit; font-size: .88rem; resize: vertical; margin: 14px 0 18px;
    }
    .maint-btn {
      width: 100%; border: none; border-radius: 10px; padding: 13px; font-weight: 700; font-size: .9rem; cursor: pointer;
      background: var(--red); color: #fff; letter-spacing: .5px;
    }
    .maint-btn.is-active { background: var(--green); }
    .maint-hint { font-size: .74rem; color: var(--text-3); margin-top: 10px; }

    /* Live-Status: Online-Spieler & Einsatzkräfte */
    .live-card { margin-bottom: 28px; }
    .live-head { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; }
    .live-badge {
      display: inline-flex; align-items: center; gap: 5px; background: rgba(232,184,109,.12); border: 1px solid rgba(232,184,109,.35);
      color: var(--gold); font-family: 'Orbitron', sans-serif; font-size: .68rem; font-weight: 900; letter-spacing: 1.5px;
      padding: 4px 10px; border-radius: 999px;
    }
    .live-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: liveDotPulse 1.6s infinite; }
    @keyframes liveDotPulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
    .live-title { font-family: 'Orbitron', sans-serif; font-size: 1rem; font-weight: 900; color: var(--text); }
    .live-refresh { margin-left: auto; }

    .online-row { display: flex; align-items: center; gap: 16px; background: rgba(255,255,255,.02); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
    .online-num { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; font-weight: 900; color: var(--green); }
    .online-num.offline { color: var(--text-3); }
    .online-sub { font-size: .8rem; color: var(--text-2); }

    .job-mini-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; }
    @media (max-width: 900px) { .job-mini-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 560px) { .job-mini-grid { grid-template-columns: repeat(2, 1fr); } }
    .job-mini-card { background: rgba(255,255,255,.02); border: 1px solid var(--border); border-top: 3px solid var(--jc, var(--purple)); border-radius: 10px; padding: 14px; text-align: center; }
    .job-mini-icon { font-size: 1.3rem; margin-bottom: 6px; }
    .job-mini-num { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; color: var(--jc, var(--purple)); line-height: 1; }
    .job-mini-label { font-size: .68rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 1px; margin-top: 6px; }

    /* Navigationskarten */
    .nav-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 480px) { .nav-grid { grid-template-columns: 1fr; } }
    .nav-card {
      display: block; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
      padding: 18px; text-decoration: none; transition: border-color .15s, transform .15s;
    }
    .nav-card:hover { border-color: var(--border-2); transform: translateY(-2px); }
    .nav-card-icon { font-size: 1.4rem; margin-bottom: 10px; }
    .nav-card-title { font-family: 'Orbitron', sans-serif; font-size: .92rem; font-weight: 900; color: var(--text); margin-bottom: 4px; }
    .nav-card-sub { font-size: .76rem; color: var(--text-3); line-height: 1.4; }
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
        <div class="admin-title">⚙️ Admin <span class="accent">Panel</span></div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Angemeldet als <strong><?= htmlspecialchars($user['global_name']) ?></strong> · <?= date('d.m.Y, H:i') ?> Uhr</p>
      </div>
      <a href="dashboard.php" class="btn btn-outline">👤 Mitgliederbereich</a>
    </div>

    <!-- Stat-Kacheln -->
    <div class="stat-row">
      <div class="stat-tile" style="--stat-color:var(--purple)">
        <div class="stat-num"><?= $stats['total'] ?></div>
        <div class="stat-label">Mitglieder</div>
      </div>
      <div class="stat-tile" style="--stat-color:#22d3ee">
        <div class="stat-num"><?= $stats['verified'] ?></div>
        <div class="stat-label">Verifiziert</div>
      </div>
      <div class="stat-tile" style="--stat-color:var(--gold)">
        <div class="stat-num"><?= $stats['admins'] ?></div>
        <div class="stat-label">Admins</div>
      </div>
      <div class="stat-tile" style="--stat-color:var(--red)">
        <div class="stat-num"><?= $stats['banned'] ?></div>
        <div class="stat-label">Gesperrt</div>
      </div>
    </div>

    <!-- Live-Status: Online-Spieler & Einsatzkräfte -->
    <div class="admin-card live-card">
      <div class="live-head">
        <span class="live-badge"><span class="live-badge-dot"></span> LIVE</span>
        <span class="live-title">Einsatzkräfte im Dienst</span>
        <button onclick="loadLiveStatus()" class="btn btn-outline live-refresh" style="padding:4px 12px;font-size:.78rem">↻ Aktualisieren</button>
      </div>

      <div class="online-row">
        <div class="online-num offline" id="liveOnlineNum">–</div>
        <div class="online-sub" id="liveOnlineSub">Lade Spielerzahl...</div>
      </div>

      <div class="job-mini-grid" id="liveJobsGrid">
        <div style="grid-column:1/-1;color:var(--text-2);font-size:.85rem">Lade Einsatzkräfte...</div>
      </div>
    </div>

    <div class="hub-layout">
      <!-- Wartungsmodus -->
      <div class="admin-card">
        <div style="font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--text-2);margin-bottom:20px;display:flex;align-items:center;gap:8px">
          🔧 Wartungsmodus
        </div>

        <div class="maint-status <?= $settings['maintenance_mode'] ? 'off' : 'on' ?>" id="maintStatusPill">
          <span class="maint-dot"></span>
          <span id="maintStatusText"><?= $settings['maintenance_mode'] ? 'Wartungsmodus aktiv' : 'Website online' ?></span>
        </div>

        <div style="font-size:.72rem;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Nachricht (optional)</div>
        <textarea class="maint-msg" id="maintMessage" rows="2" placeholder="Wir führen Wartungsarbeiten durch..."><?= htmlspecialchars($settings['maintenance_message']) ?></textarea>

        <button class="maint-btn <?= $settings['maintenance_mode'] ? 'is-active' : '' ?>" id="maintToggleBtn" onclick="toggleMaintenance()">
          <span id="maintBtnLabel">🔧 <?= $settings['maintenance_mode'] ? 'Wartungsmodus deaktivieren' : 'Wartungsmodus aktivieren' ?></span>
        </button>
        <p class="maint-hint">Zeigt allen Besuchern einen Wartungshinweis-Banner auf der Website. Weitere Sichtbarkeits-Einstellungen unter „Server &amp; Sichtbarkeit“.</p>
      </div>

      <!-- Navigationskarten -->
      <div class="nav-grid">
        <a href="admin_users.php" class="nav-card">
          <div class="nav-card-icon">👥</div>
          <div class="nav-card-title">Benutzerverwaltung</div>
          <div class="nav-card-sub">Mitglieder, Rollen, Sperren, Passwort-Reset</div>
        </a>
        <a href="admin_settings.php" class="nav-card">
          <div class="nav-card-icon">🖥</div>
          <div class="nav-card-title">Server &amp; Sichtbarkeit</div>
          <div class="nav-card-sub">FiveM-Live-Daten, Statusseite, Discord</div>
        </a>
        <a href="admin_fraktionen.php" class="nav-card">
          <div class="nav-card-icon">🏙</div>
          <div class="nav-card-title">Fraktionen</div>
          <div class="nav-card-sub">Fraktionen auf der Startseite verwalten</div>
        </a>
        <a href="admin_changelog.php" class="nav-card">
          <div class="nav-card-icon">📝</div>
          <div class="nav-card-title">Changelog</div>
          <div class="nav-card-sub">Update-Ankündigungen &amp; Discord-Posts</div>
        </a>
        <a href="fivem_diagnose.php" class="nav-card">
          <div class="nav-card-icon">🔬</div>
          <div class="nav-card-title">FiveM Diagnose</div>
          <div class="nav-card-sub">Verbindungstest &amp; Diagnose-Export</div>
        </a>
        <a href="db_admin.php" class="nav-card" style="grid-column:1 / -1">
          <div class="nav-card-icon">🗄</div>
          <div class="nav-card-title">Datenbank-Browser</div>
          <div class="nav-card-sub">Tabellen einsehen &amp; DB-Verbindung prüfen</div>
        </a>
      </div>
    </div>

  </div>
</main>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
// ── Live-Status: Online-Spieler & Einsatzkräfte ────────────────────────────────
const JOB_MINI = {
  police:    { color: '#3b82f6', icon: '👮', label: 'Polizei' },
  fire:      { color: '#f97316', icon: '🚒', label: 'Feuerwehr' },
  ambulance: { color: '#ef4444', icon: '🚑', label: 'Berufsrettung' },
  mechanic:  { color: '#a3a3a3', icon: '🔧', label: 'Mechaniker' },
  user:      { color: '#22c55e', icon: '🙂', label: 'User' },
};

async function loadLiveStatus() {
  const numEl = document.getElementById('liveOnlineNum');
  const subEl = document.getElementById('liveOnlineSub');
  const jobsEl = document.getElementById('liveJobsGrid');
  subEl.textContent = 'Lade Spielerzahl...';

  const controller = new AbortController();
  const abortTimer = setTimeout(() => controller.abort(), 12000);

  try {
    const r = await fetch('api/players_status.php', { signal: controller.signal, cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();

    if (!d.online) {
      numEl.textContent = '0';
      numEl.classList.add('offline');
      subEl.textContent = 'Server offline';
    } else {
      numEl.textContent = d.players;
      numEl.classList.remove('offline');
      subEl.textContent = 'Online-Spieler von ' + d.max_players + ' Slots';
    }

    const jobs = d.jobs || [];
    const jobByKey = {};
    jobs.forEach(j => { jobByKey[j.key] = j.count; });

    jobsEl.innerHTML = Object.entries(JOB_MINI).map(([key, meta]) => `
      <div class="job-mini-card" style="--jc:${meta.color}">
        <div class="job-mini-icon">${meta.icon}</div>
        <div class="job-mini-num">${jobByKey[key] ?? 0}</div>
        <div class="job-mini-label">${meta.label}</div>
      </div>
    `).join('');
  } catch (e) {
    subEl.textContent = 'Fehler beim Abrufen der Daten';
    jobsEl.innerHTML = '<div style="grid-column:1/-1;color:var(--red);font-size:.85rem">❌ ' + e.message + '</div>';
  } finally {
    clearTimeout(abortTimer);
  }
}

loadLiveStatus();

let maintActive = <?= $settings['maintenance_mode'] ? 'true' : 'false' ?>;

async function toggleMaintenance() {
  const btn = document.getElementById('maintToggleBtn');
  btn.disabled = true;
  const next = !maintActive;
  const message = document.getElementById('maintMessage').value;

  try {
    const r = await fetch('api/admin_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ maintenance_mode: next, maintenance_message: message }),
    });
    const d = await r.json();
    if (d.ok) {
      maintActive = next;
      const pill = document.getElementById('maintStatusPill');
      pill.className = 'maint-status ' + (maintActive ? 'off' : 'on');
      document.getElementById('maintStatusText').textContent = maintActive ? 'Wartungsmodus aktiv' : 'Website online';
      document.getElementById('maintBtnLabel').textContent = '🔧 ' + (maintActive ? 'Wartungsmodus deaktivieren' : 'Wartungsmodus aktivieren');
      btn.classList.toggle('is-active', maintActive);
    } else {
      alert('Fehler beim Speichern: ' + (d.error || 'Unbekannt'));
    }
  } catch (e) {
    alert('Netzwerkfehler: ' + e.message);
  } finally {
    btn.disabled = false;
  }
}
</script>
</body>
</html>
