<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';

$user  = current_user();
$error = htmlspecialchars($_GET['error'] ?? '', ENT_QUOTES);

function avatarUrl(?array $user): string {
    if (!$user || !$user['avatar']) return 'assets/img/default-avatar.png';
    return "https://cdn.discordapp.com/avatars/{$user['id']}/{$user['avatar']}.png?size=128";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ViennaStateRP – Willkommen</title>
  <meta name="description" content="ViennaStateRP – Wiens größter FiveM Roleplay Server. Tauche ein in eine lebendige Welt voller Möglichkeiten." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
</head>
<body>

<!-- ==================== NAVBAR ==================== -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php">
      <span class="logo-accent">Vienna</span>StateRP
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="#about">Über uns</a></li>
      <li><a href="#features">Features</a></li>
      <li><a href="#rules">Regeln</a></li>
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
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
          Mit Discord anmelden
        </a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menü">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- ==================== HERO ==================== -->
<section class="hero" id="hero">
  <div class="hero-bg">
    <div class="hero-overlay"></div>
    <div class="hero-particles" id="particles"></div>
  </div>
  <div class="hero-content">
    <div class="hero-badge">🎮 FiveM Roleplay Server</div>
    <h1 class="hero-title">
      <span class="accent">Vienna</span>State<span class="accent">RP</span>
    </h1>
    <p class="hero-sub">Wiens authentischstes GTA V Roleplay-Erlebnis.<br>Schreibe deine Geschichte in der Stadt der Träume.</p>
    <div class="hero-stats">
      <div class="stat"><span class="stat-num" data-target="500">0</span><span class="stat-label">Aktive Spieler</span></div>
      <div class="stat-divider"></div>
      <div class="stat"><span class="stat-num" data-target="120">0</span><span class="stat-label">Fraktionen</span></div>
      <div class="stat-divider"></div>
      <div class="stat"><span class="stat-num" data-target="2">0</span><span class="stat-label">Jahre Online</span></div>
    </div>
    <div class="hero-actions">
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary btn-lg">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        Jetzt spielen
      </a>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="btn btn-discord btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord beitreten
      </a>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-error">⚠ Login fehlgeschlagen: <?= $error ?></div>
    <?php endif; ?>
  </div>
  <div class="hero-scroll-hint">
    <span>Scroll</span>
    <div class="scroll-arrow"></div>
  </div>
</section>

<!-- ==================== ABOUT ==================== -->
<section class="section" id="about">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Wer sind wir?</span>
      <h2>Über <span class="accent">ViennaStateRP</span></h2>
      <p>Ein Roleplay-Server mit Leidenschaft gebaut – von der Community, für die Community.</p>
    </div>
    <div class="about-grid">
      <div class="about-card glass">
        <div class="about-icon">🏙️</div>
        <h3>Authentisches Wien</h3>
        <p>Erlebe eine detailreiche, von Wien inspirierte Spielwelt mit eigenen Locations, Jobs und einer lebendigen Wirtschaft.</p>
      </div>
      <div class="about-card glass">
        <div class="about-icon">👮</div>
        <h3>Vielfältige Fraktionen</h3>
        <p>Von der Polizei über den Rettungsdienst bis hin zu kriminellen Organisationen – finde deinen Platz in der Gesellschaft.</p>
      </div>
      <div class="about-card glass">
        <div class="about-icon">🛠️</div>
        <h3>Custom Scripts</h3>
        <p>Eigens entwickelte Skripte für ein einzigartiges Spielerlebnis, das du nirgendwo anders findest.</p>
      </div>
      <div class="about-card glass">
        <div class="about-icon">🤝</div>
        <h3>Starke Community</h3>
        <p>Eine aktive, freundliche Community mit regelmäßigen Events, Giveaways und direktem Kontakt zum Staff-Team.</p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FEATURES ==================== -->
<section class="section section-dark" id="features">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Was wir bieten</span>
      <h2>Server <span class="accent">Features</span></h2>
    </div>
    <div class="features-list">
      <?php
      $features = [
        ['icon'=>'🚗','title'=>'Fahrzeug-System','desc'=>'Hunderte lizenzierte Fahrzeuge mit realistischem Tuning & Schadensmodell'],
        ['icon'=>'💼','title'=>'Job-System','desc'=>'Legale & illegale Jobs – Mechaniker, Anwalt, Drogenhandel und vieles mehr'],
        ['icon'=>'🏠','title'=>'Housing','desc'=>'Kaufe oder miete deine eigene Immobilie und richte sie nach deinen Wünschen ein'],
        ['icon'=>'🏦','title'=>'Wirtschaft','desc'=>'Realistische Marktwirtschaft mit Banken, Aktien und eigenem Währungssystem'],
        ['icon'=>'📡','title'=>'MDT System','desc'=>'Modernes Multi-Display-Terminal für Polizei und Rettungskräfte'],
        ['icon'=>'🎭','title'=>'Charakter-System','desc'=>'Detaillierter Charakter-Editor mit individuellen Hintergrundgeschichten'],
        ['icon'=>'🎵','title'=>'Radio & Media','desc'=>'In-Game Radio, Twitch-Integration und eigene Boombox-Funktion'],
        ['icon'=>'⚖️','title'=>'Rechtssystem','desc'=>'Vollständiges Gerichtssystem mit Anwälten, Richtern und Prozessen'],
      ];
      foreach ($features as $f): ?>
      <div class="feature-item glass">
        <span class="feature-icon"><?= $f['icon'] ?></span>
        <div>
          <h4><?= $f['title'] ?></h4>
          <p><?= $f['desc'] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==================== RULES ==================== -->
<section class="section" id="rules">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Fair Play</span>
      <h2>Server <span class="accent">Regeln</span></h2>
      <p>Für ein faires und immersives Erlebnis bitten wir alle Spieler, diese Regeln zu respektieren.</p>
    </div>
    <div class="rules-grid">
      <?php
      $rules = [
        ['num'=>'01','title'=>'Roleplay first','desc'=>'Das Roleplay steht immer an erster Stelle. RDM, VDM und Powergaming sind verboten.'],
        ['num'=>'02','title'=>'In-Character bleiben','desc'=>'Außer in designierten OOC-Kanälen ist stets IC zu kommunizieren. Brüche sind zu minimieren.'],
        ['num'=>'03','title'=>'Respekt','desc'=>'Respektvoller Umgang ist Pflicht – sowohl IC als auch OOC. Beleidigungen werden nicht toleriert.'],
        ['num'=>'04','title'=>'No Metagaming','desc'=>'Informationen aus OOC-Quellen dürfen nicht IC verwendet werden.'],
        ['num'=>'05','title'=>'Fear RP','desc'=>'Dein Charakter hat Angst vor dem Tod. Handle entsprechend realistisch in gefährlichen Situationen.'],
        ['num'=>'06','title'=>'Staff Entscheidungen','desc'=>'Entscheidungen des Staff-Teams sind zu respektieren und können über das Ticketsystem angefochten werden.'],
      ];
      foreach ($rules as $r): ?>
      <div class="rule-card glass">
        <span class="rule-num"><?= $r['num'] ?></span>
        <h4><?= $r['title'] ?></h4>
        <p><?= $r['desc'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==================== CTA ==================== -->
<section class="cta-section">
  <div class="cta-bg"></div>
  <div class="container cta-content">
    <h2>Bereit, dein Abenteuer zu starten?</h2>
    <p>Tritt noch heute unserer Community bei und erlebe Roleplay auf höchstem Niveau.</p>
    <div class="cta-actions">
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary btn-lg">Jetzt verbinden</a>
      <?php if (!$user): ?>
      <a href="login.php" class="btn btn-discord btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord Login
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
<footer class="footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server.<br>Seit 2022 mit Leidenschaft entwickelt.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="#about">Über uns</a>
      <a href="#features">Features</a>
      <a href="#rules">Regeln</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="login.php">Login</a>
      <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="footer-links">
      <h5>Server</h5>
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>">Verbinden</a>
      <p class="server-ip"><?= htmlspecialchars(FIVEM_SERVER_IP) ?>:<?= htmlspecialchars(FIVEM_SERVER_PORT) ?></p>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten. | Kein offizielles Rockstar Games Produkt.</p>
  </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
