<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/deploy.php';

require_login();
if (!has_capability('deploy.run')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}

$user = current_user();
$execOk = deploy_exec_available();

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
  <title>Deployment – Admin – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    .admin-page { padding: 90px 0 80px; min-height: 100vh; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
    .admin-title { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; }
    .dp-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; margin-bottom: 20px; }
    .dp-label { font-size: .72rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-2); font-weight: 700; margin-bottom: 8px; }
    .dp-mono { font-family: 'JetBrains Mono', monospace, monospace; font-size: .82rem; color: var(--text); }
    .dp-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
    .dp-list li { font-family: monospace; font-size: .82rem; color: var(--text-2); }
    .dp-out { background: #0a0a0f; border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; font-family: monospace; font-size: .8rem; white-space: pre-wrap; color: #e2e8f0; margin-top: 14px; display: none; }
    .dp-warn { color: #fbbf24; font-size: .85rem; margin-top: 10px; }
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
        <img src="<?= htmlspecialchars(avatarUrl($user), ENT_QUOTES) ?>" alt="" class="nav-avatar" />
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
        <div class="admin-title">🚀 Deployment</div>
        <p style="color:var(--text-2);font-size:.88rem;margin-top:4px">Holt den neuesten Stand von GitHub auf diese Website (<code>git pull --ff-only</code>).</p>
      </div>
      <a href="admin.php" class="btn btn-outline">← Admin</a>
    </div>

    <?php if (!$execOk): ?>
    <div class="dp-card">
      <div class="dp-warn">⚠ <code>exec()</code> ist auf diesem Server deaktiviert. Deployment bitte per SSH mit <code>./deploy.sh</code> ausführen.</div>
    </div>
    <?php else: ?>
    <div class="dp-card">
      <div class="dp-label">Aktueller Stand auf dem Server</div>
      <div class="dp-mono" id="dpCurrent">Lade …</div>
    </div>

    <div class="dp-card">
      <div class="dp-label">Neue Änderungen auf GitHub (<span id="dpBranch">main</span>)</div>
      <ul class="dp-list" id="dpPending"><li>Lade …</li></ul>
      <div class="dp-warn" id="dpDirty" style="display:none"></div>
      <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
        <button class="btn btn-primary" id="dpRun" disabled>🚀 Jetzt deployen</button>
        <button class="btn btn-outline" id="dpRefresh">↻ Aktualisieren</button>
      </div>
      <div class="dp-out" id="dpOut"></div>
    </div>
    <?php endif; ?>
  </div>
</main>

<?php if ($execOk): ?>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const elCur = document.getElementById('dpCurrent');
const elPend = document.getElementById('dpPending');
const elDirty = document.getElementById('dpDirty');
const elOut = document.getElementById('dpOut');
const btnRun = document.getElementById('dpRun');

function setText(el, t) { el.textContent = t; }

function render(d) {
  document.getElementById('dpBranch').textContent = d.branch || 'main';
  setText(elCur, d.current || '?');
  elPend.textContent = '';
  if (!d.fetch_ok) {
    const li = document.createElement('li');
    li.textContent = 'Abruf von GitHub fehlgeschlagen: ' + (d.fetch_msg || 'unbekannter Fehler');
    elPend.appendChild(li);
  } else if (!d.pending.length) {
    const li = document.createElement('li');
    li.textContent = '✓ Die Website ist auf dem neuesten Stand.';
    elPend.appendChild(li);
  } else {
    d.pending.forEach(line => { const li = document.createElement('li'); li.textContent = line; elPend.appendChild(li); });
  }
  if (d.dirty && d.dirty.length) {
    elDirty.style.display = 'block';
    elDirty.textContent = '⚠ Lokal auf dem Server geänderte Dateien blockieren den Pull: ' + d.dirty.map(l => l.trim()).join(', ');
  } else {
    elDirty.style.display = 'none';
  }
  btnRun.disabled = !(d.fetch_ok && d.pending.length);
}

async function load() {
  btnRun.disabled = true;
  setText(elCur, 'Lade …');
  try {
    const r = await fetch('api/deploy.php', { cache: 'no-store' });
    const d = await r.json();
    if (!d.ok) { setText(elCur, d.error || 'Fehler'); return; }
    render(d);
  } catch (e) { setText(elCur, 'Verbindungsfehler'); }
}

btnRun.addEventListener('click', async () => {
  if (!confirm('Neuesten Stand jetzt auf die Live-Website einspielen?')) return;
  btnRun.disabled = true;
  elOut.style.display = 'block';
  setText(elOut, 'Deployment läuft …');
  try {
    const r = await fetch('api/deploy.php', { method: 'POST', headers: { 'X-CSRF-Token': CSRF } });
    const d = await r.json();
    setText(elOut, (d.ok ? '✓ Erfolgreich\n\n' : '✗ Fehlgeschlagen\n\n') + (d.output || d.error || ''));
    if (d.current) render(d);
  } catch (e) { setText(elOut, 'Verbindungsfehler'); }
});
document.getElementById('dpRefresh').addEventListener('click', load);
load();
</script>
<?php endif; ?>
</body>
</html>
