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
  <title>Impressum – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
  <style>
    .legal-page { padding: 110px 0 80px; min-height: 100vh; }
    .legal-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--r-lg);
      padding: 48px 56px;
      max-width: 860px;
      margin: 0 auto;
    }
    .legal-card h1 { font-size: clamp(1.6rem, 3vw, 2.2rem); margin-bottom: 8px; }
    .legal-card .legal-sub { color: var(--text-2); font-family: 'Rajdhani', sans-serif; font-size: 0.9rem; margin-bottom: 40px; }
    .legal-card h2 { font-size: 1rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--gold); margin: 36px 0 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
    .legal-card p, .legal-card address { color: var(--text-2); line-height: 1.8; font-size: 0.95rem; font-style: normal; }
    .legal-card a { color: var(--gold); }
    .legal-card a:hover { text-decoration: underline; }
    .legal-nav { display: flex; gap: 12px; margin-bottom: 32px; flex-wrap: wrap; }
    .legal-nav a { font-family: 'Rajdhani', sans-serif; font-size: 0.85rem; padding: 6px 14px; border-radius: 6px; border: 1px solid var(--border-2); color: var(--text-2); transition: color 0.2s, border-color 0.2s; }
    .legal-nav a:hover, .legal-nav a.active { color: var(--gold); border-color: rgba(232,184,109,0.4); }
    @media (max-width: 640px) { .legal-card { padding: 28px 20px; } }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar scrolled" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#about">Über uns</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="index.php#fraktionen">Fraktionen</a></li>
      <li><a href="regelwerk.php">Regeln</a></li>
      <li><a href="index.php#join">Beitreten</a></li>
      <li><a href="players.php">Server Status</a></li>
      <li><a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a></li>
    </ul>
    <div class="nav-actions">
      <?php if ($user): ?>
        <a href="dashboard.php" class="btn btn-ghost">
          <img src="<?= htmlspecialchars(avatarUrl($user)) ?>" alt="Avatar" class="nav-avatar" />
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

<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<!-- CONTENT -->
<main class="legal-page">
  <div class="container">

    <div class="legal-nav">
      <a href="impressum.php" class="active">Impressum</a>
      <a href="datenschutz.php">Datenschutzerklärung</a>
      <a href="allgemeine-hinweise.php">Allgemeine Hinweise</a>
    </div>

    <div class="legal-card">
      <div class="section-tag">Rechtliches</div>
      <h1>Impressum</h1>
      <p class="legal-sub">Angaben gemäß § 5 ECG (E-Commerce-Gesetz, Österreich) und § 14 UGB</p>

      <h2>Betreiber & Verantwortlicher</h2>
      <address>
        <strong>[DEIN NAME / ORGANISATIONSNAME]</strong><br />
        [Straße und Hausnummer]<br />
        [PLZ] [Ort]<br />
        Österreich
      </address>

      <h2>Kontakt</h2>
      <p>
        E-Mail: <a href="mailto:[DEINE-EMAIL@DOMAIN.AT]">[DEINE-EMAIL@DOMAIN.AT]</a><br />
        Discord: <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(DISCORD_INVITE) ?></a>
      </p>

      <h2>Inhalt des Online-Angebots</h2>
      <p>
        ViennaStateRP ist ein privates, nicht-kommerzielles Hobby-Projekt und betreibt einen
        FiveM-Roleplay-Server (GTA V Multiplayer-Mod) sowie diese begleitende Website.
        Das Projekt steht in keiner Verbindung zu Rockstar Games, Take-Two Interactive oder FiveM/Cfx.re.
      </p>

      <h2>Haftungsausschluss</h2>
      <p>
        Die Inhalte dieser Website wurden mit größtmöglicher Sorgfalt erstellt. Für die Richtigkeit,
        Vollständigkeit und Aktualität der Inhalte übernehmen wir jedoch keine Gewähr. Als Dienstanbieter
        sind wir für eigene Inhalte nach § 18 ECG verantwortlich. Für externe Links (insbesondere
        Discord, FiveM-Verbindungslinks) übernehmen wir keine Haftung; für den Inhalt verlinkter Seiten
        sind ausschließlich deren Betreiber verantwortlich.
      </p>

      <h2>Urheberrecht</h2>
      <p>
        Die auf dieser Website verwendeten Grafiken, Texte und sonstigen Inhalte sind – soweit nicht
        anders angegeben – urheberrechtlich geschützt. GTA V und alle zugehörigen Marken sind Eigentum
        von Rockstar Games / Take-Two Interactive. FiveM ist ein Projekt von Cfx.re. Eine Nutzung oder
        Vervielfältigung ist ohne ausdrückliche Genehmigung des Betreibers nicht gestattet.
      </p>

      <h2>Streitschlichtung (Online-Streitbeilegung)</h2>
      <p>
        Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
        <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a>.
        Da es sich um ein nicht-kommerzielles Angebot handelt, besteht keine Verpflichtung zur
        Teilnahme an einem Streitbeilegungsverfahren. Wende dich bei Fragen direkt an uns.
      </p>

      <p style="margin-top:40px;font-size:0.8rem;color:var(--text-3)">Letzte Aktualisierung: <?= date('d.m.Y') ?></p>
    </div>

  </div>
</main>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server. Powered by GamingCommunity24/7.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="index.php">Startseite</a>
      <a href="index.php#features">Features</a>
      <a href="regelwerk.php">Regeln</a>
      <a href="players.php">Server Status</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="login.php">Login</a>
      <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="footer-links">
      <h5>Rechtliches</h5>
      <a href="impressum.php">Impressum</a>
      <a href="datenschutz.php">Datenschutzerklärung</a>
      <a href="allgemeine-hinweise.php">Allgemeine Hinweise</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> ViennaStateRP / GamingCommunity24/7. Alle Rechte vorbehalten. | Kein offizielles Rockstar Games Produkt. |
      <a href="impressum.php" style="color:inherit">Impressum</a> ·
      <a href="datenschutz.php" style="color:inherit">Datenschutz</a> ·
      <a href="allgemeine-hinweise.php" style="color:inherit">Hinweise</a>
    </p>
  </div>
</footer>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
