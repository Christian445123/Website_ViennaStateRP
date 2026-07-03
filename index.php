<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';

$user = current_user();

if (!function_exists('avatarUrl')) {
    function avatarUrl(?array $user): string {
        if (empty($user['avatar']) || empty($user['id'])) {
            return 'https://cdn.discordapp.com/embed/avatars/0.png';
        }
        return 'https://cdn.discordapp.com/avatars/' . $user['id'] . '/' . $user['avatar'] . '.png?size=64';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ViennaStateRP – GTA V Roleplay auf FiveM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="#about">Über uns</a></li>
      <li><a href="#features">Features</a></li>
      <li><a href="#rules">Regeln</a></li>
      <li><a href="#join">Beitreten</a></li>
      <li><a href="players.php">Server Status</a></li>
      <li><a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a></li>
    </ul>
    <div class="nav-actions">
      <?php if ($user): ?>
        <a href="dashboard.php" class="btn btn-ghost">
          <img src="<?= avatarUrl($user) ?>" alt="Avatar" class="nav-avatar" />
          <?= htmlspecialchars($user['global_name']) ?>
        </a>
        <a href="logout.php" class="btn btn-outline">Abmelden</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-discord">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:18px;height:18px"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
          Discord Login
        </a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-bg">
    <div class="hero-overlay"></div>
    <div class="hero-particles" id="particles"></div>
  </div>
  <div class="hero-content">
    <div class="hero-status" id="heroStatusBadge">
      <span class="hero-status-dot" id="heroStatusDot"></span>
      <span id="heroStatusText">Verbinde...</span>
    </div>
    <h1 class="hero-title">GTA V ROLEPLAY<br><span class="accent">AUF FIVEM</span></h1>
    <p class="hero-sub">Willkommen auf ViennaStateRP – Wiens realistischstem FiveM Roleplay-Server. Schreibe deine Geschichte in einer lebendigen Stadt.</p>

    <div class="hero-tags">
      <span class="hero-tag">🎮 FiveM / GTA V</span>
      <span class="hero-tag">🗺️ Custom MLOs</span>
      <span class="hero-tag">🇦🇹 Österreich</span>
      <span class="hero-tag">🎭 Whitelist</span>
      <span class="hero-tag">18+</span>
    </div>

    <div class="hero-actions">
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary btn-lg">▶ Server verbinden</a>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="btn btn-discord btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:20px;height:20px"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord beitreten
      </a>
    </div>

    <div class="hero-stats-row">
      <div class="hero-stat">
        <span class="hero-stat-num" id="heroPlayers">–</span>
        <span class="hero-stat-lbl">Aktive Spieler</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat-num">64</span>
        <span class="hero-stat-lbl">Max. Slots</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat-num">4+</span>
        <span class="hero-stat-lbl">Fraktionen</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat-num">1</span>
        <span class="hero-stat-lbl">Monat Online</span>
      </div>
    </div>
  </div>
  <div class="hero-scroll-hint">Scroll</div>
</section>

<!-- ABOUT -->
<section class="section" id="about">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Wer sind wir?</span>
      <h2>Uber <span class="accent">ViennaStateRP</span></h2>
      <p>Ein realistisches Roleplay-Erlebnis, aufgebaut von unserer Community.</p>
    </div>
    <div class="about-grid">
      <div class="about-card">
        <div class="about-icon">🏙️</div>
        <h3>Wien als Spielwelt</h3>
        <p>Eine detailreich gestaltete Spielwelt mit Custom MLOs und realistischen Locations.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">👮</div>
        <h3>Aktive Fraktionen</h3>
        <p>Polizei, EMS, Feuerwehr und Mechaniker – jede Fraktion mit eigener Struktur.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">🛠️</div>
        <h3>Custom Scripts</h3>
        <p>Eigens entwickelte Ressourcen für ein einzigartiges Gameplay-Erlebnis.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">🤝</div>
        <h3>Aktive Community</h3>
        <p>Freundliche Community und aktives Staff-Team mit regelmäßigen Events.</p>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="section section-dark" id="features">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Was wir bieten</span>
      <h2>Server <span class="accent">Features</span></h2>
    </div>
    <div class="features-list">
      <?php
      $features = [
        ['🏛️', 'Fraktionssystem',   'Polizei, EMS, Feuerwehr und mehr – mit eigenen Scripts, Rängen und Strukturen.'],
        ['💼', 'Wirtschaft & Jobs', 'Realistische Wirtschaft mit legalen und illegalen Jobs, eigenem Banking-System.'],
        ['🚘', 'Custom Fahrzeuge',  'Add-on-Fahrzeuge mit realistischer Schadensphysik und Tuning-System.'],
        ['🏠', 'Immobilien',        'Häuser und Wohnungen kaufen oder mieten und individuell einrichten.'],
        ['📱', 'Smartphone-System', 'In-Game Handy mit Kontakten, Anrufen, SMS und eigenem Social-Media-Feed.'],
        ['🗺️', 'Custom MLOs',      'Eigene Interior-Objekte für Fraktionsgebäude und immersive Locations.'],
        ['⚖️', 'Justizsystem',     'Staatsanwaltschaft, Richter und Anwälte – Verfahren vollständig im Roleplay.'],
        ['🛠️', 'Aktive Entwicklung','Regelmäßige Updates, Community-Feedback und ein aktives Dev-Team.'],
      ];
      foreach ($features as $f): ?>
      <div class="feature-item">
        <span class="feature-icon"><?= $f[0] ?></span>
        <div>
          <h3><?= $f[1] ?></h3>
          <p><?= $f[2] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- RULES -->
<section class="section" id="rules">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Fair Play</span>
      <h2>Grundlegendes <span class="accent">Regelwerk</span></h2>
      <p>Das Regelwerk gilt für alle Spieler. Unwissenheit schützt nicht vor Konsequenzen.</p>
    </div>
    <div class="rule-card">
      <h3>💡 1. Grundlegende Server-Philosophie</h3>
      <div class="rule-list">
        <div class="rule-item">Sei kein Idiot – behandle jeden so, wie du selbst behandelt werden möchtest.</div>
        <div class="rule-item">Dein Charakter hat Angst um sein Leben und handelt nachvollziehbar (Realitätsprinzip).</div>
      </div>
    </div>
    <div class="rule-card">
      <h3>🔫 2. Tödliche Gewalt (RDM & VDM)</h3>
      <div class="rule-list">
        <div class="rule-item">RDM: Grundloses Töten ohne vorheriges RP-Gespräch ist verboten. → BAN</div>
        <div class="rule-item">VDM: Fahrzeuge als primäre Waffe ohne RP-Hintergrund. → BAN</div>
      </div>
    </div>
    <div class="rule-card">
      <h3>🎭 3. Roleplay-Pflichten</h3>
      <div class="rule-list">
        <div class="rule-item">New Life Regel: Nach dem Tod vergisst dein Charakter alle Ereignisse die dazu führten.</div>
        <div class="rule-item">FailRP: Unlogische Handlungen im RP-Kontext. → WARN/KICK</div>
        <div class="rule-item">PowerGaming: Situationen erzwingen die dem Gegner keine Reaktionschance lassen. → KICK</div>
      </div>
    </div>
    <div class="rule-card">
      <h3>🚫 4. Verbotene Handlungen</h3>
      <div class="rule-list">
        <div class="rule-item">Cheating / Hacking / Bugusing jeglicher Art. → BAN</div>
        <div class="rule-item">NSFW-RP, Gewaltverherrlichung, rassistische Beleidigungen. → BAN</div>
        <div class="rule-item">Echtgeld-Handel (RMT) für Ingame-Güter. → BAN</div>
      </div>
    </div>
  </div>
</section>

<!-- JOIN -->
<section class="section section-dark" id="join">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Mitmachen</span>
      <h2>Zugang zum <span class="accent">Server</span></h2>
      <p>Kein komplizierter Bewerbungsprozess – einfach Discord beitreten und verifizieren.</p>
    </div>
    <div class="about-grid" style="max-width:800px;margin:0 auto">
      <div class="about-card" style="text-align:center">
        <div class="about-icon" style="font-size:2.5rem">01</div>
        <h3>Discord beitreten</h3>
        <p>Tritt unserem Discord bei und lerne die Community kennen.</p>
      </div>
      <div class="about-card" style="text-align:center">
        <div class="about-icon" style="font-size:2.5rem">02</div>
        <h3>Verifizieren</h3>
        <p>Verifiziere dich im Verification-Channel gemäß den Anweisungen.</p>
      </div>
      <div class="about-card" style="text-align:center">
        <div class="about-icon" style="font-size:2.5rem">03</div>
        <h3>Server joinen</h3>
        <p>Fertig! Verbinde dich und starte dein Roleplay-Abenteuer.</p>
      </div>
    </div>
    <div style="text-align:center;margin-top:48px">
      <p style="color:#4b5563;font-size:.85rem;margin-bottom:8px;font-family:'Orbitron',sans-serif;letter-spacing:1px">SERVER IP</p>
      <code style="font-size:1rem;padding:12px 24px;border-radius:8px"><?= htmlspecialchars(FIVEM_IP) ?>:<?= htmlspecialchars(FIVEM_PORT) ?></code>
      <div style="margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="btn btn-discord">Discord & Whitelist</a>
        <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary">Server verbinden</a>
      </div>
    </div>
  </div>
</section>

<!-- STATUS -->
<section class="section" id="status">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Live</span>
      <h2>Server <span class="accent">Status</span></h2>
    </div>
    <div class="index-status-wrap">
      <div class="index-status-top">
        <div class="index-status-indicator">
          <span class="status-dot" id="indexStatusDot"></span>
          <span id="indexStatusText" style="font-size:.9rem;color:#94a3b8">Verbinde...</span>
        </div>
        <a href="players.php" class="btn btn-outline" style="font-size:.82rem;padding:6px 14px">Details →</a>
      </div>
      <div class="index-status-stats">
        <div class="index-stat">
          <span class="index-stat-num" id="indexPlayers">–</span>
          <span class="index-stat-lbl">Aktive Spieler</span>
        </div>
        <div class="index-stat-divider"></div>
        <div class="index-stat">
          <span class="index-stat-num" id="indexMaxPlayers">–</span>
          <span class="index-stat-lbl">Slots gesamt</span>
        </div>
        <div class="index-stat-divider"></div>
        <div class="index-stat">
          <span class="index-stat-num" id="indexPct">–</span>
          <span class="index-stat-lbl">Auslastung</span>
        </div>
      </div>
      <div class="status-bar-wrap" style="margin-top:20px">
        <div class="status-bar" id="indexStatusBar" style="width:0%"></div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-bg"></div>
  <div class="container cta-content">
    <h2>Bereit, deine Geschichte zu schreiben?</h2>
    <p>Tritt noch heute unserer Community bei und erlebe Roleplay auf höchstem Niveau.</p>
    <div class="cta-actions">
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary btn-lg">Jetzt verbinden</a>
      <?php if (!$user): ?>
      <a href="login.php" class="btn btn-discord btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:20px;height:20px"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord Login
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server. Powered by GamingCommunity24/7.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="#about">Über uns</a>
      <a href="#features">Features</a>
      <a href="#rules">Regeln</a>
      <a href="#join">Beitreten</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="login.php">Login</a>
      <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="footer-links">
      <h5>Server</h5>
      <a href="players.php">Server Status</a>
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>">Verbinden</a>
      <p class="server-ip"><?= htmlspecialchars(FIVEM_IP) ?>:<?= htmlspecialchars(FIVEM_PORT) ?></p>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> ViennaStateRP / GamingCommunity24/7. Alle Rechte vorbehalten. | Kein offizielles Rockstar Games Produkt.</p>
  </div>
</footer>

<script src="assets/js/main.js"></script>
<script>
// Hero server status
(async function() {
  const dot  = document.getElementById('heroStatusDot');
  const txt  = document.getElementById('heroStatusText');
  const badge = document.getElementById('heroStatusBadge');
  const num  = document.getElementById('heroPlayers');
  try {
    const r = await fetch('api/server_status.php');
    const d = await r.json();
    if (d.online) {
      dot.style.background = '#22c55e';
      dot.style.boxShadow  = '0 0 8px #22c55e';
      txt.textContent = 'Server Online';
      if (num) num.textContent = d.players;
    } else {
      dot.style.background = '#ef4444';
      txt.textContent = 'Server Offline';
      badge.style.background = 'rgba(239,68,68,0.1)';
      badge.style.borderColor = 'rgba(239,68,68,0.25)';
      badge.style.color = '#ef4444';
    }
  } catch(e) {
    txt.textContent = 'Status unbekannt';
  }
})();

// Index status widget
(async function indexStatus() {
  const dot = document.getElementById('indexStatusDot');
  const txt = document.getElementById('indexStatusText');
  const p   = document.getElementById('indexPlayers');
  const m   = document.getElementById('indexMaxPlayers');
  const pct = document.getElementById('indexPct');
  const bar = document.getElementById('indexStatusBar');
  if (!dot) return;
  async function load() {
    try {
      const r = await fetch('api/server_status.php');
      const d = await r.json();
      if (d.online) {
        dot.className = 'status-dot online';
        txt.textContent = 'Online';
        const pc = d.max_players > 0 ? Math.round(d.players/d.max_players*100) : 0;
        p.textContent   = d.players;
        m.textContent   = d.max_players;
        pct.textContent = pc + '%';
        bar.style.width = pc + '%';
      } else {
        dot.className = 'status-dot offline';
        txt.textContent = 'Offline';
        p.textContent = m.textContent = pct.textContent = '–';
        bar.style.width = '0%';
      }
    } catch(e) {
      dot.className = 'status-dot offline';
      txt.textContent = 'Nicht erreichbar';
    }
  }
  load();
  setInterval(load, 30000);
})();
</script>
</body>
</html>