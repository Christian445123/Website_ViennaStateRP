<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/site_settings.php';

// Im Wartungsmodus macht die Beta-Ankündigung keinen Sinn – direkt zur Startseite (mit Banner).
if (setting('maintenance_mode')) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['proceed'])) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('vsrp_beta_v2', '1', [
        'expires'  => time() + 60 * 60 * 24 * 7,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: index.php');
    exit;
}

$user      = current_user();
$hasBanner = file_exists(__DIR__ . '/assets/img/beta-banner.jpg');

if (!function_exists('avatarUrl')) {
    function avatarUrl(?array $u): string {
        if (empty($u['avatar']) || empty($u['id']))
            return 'https://cdn.discordapp.com/embed/avatars/0.png';
        return 'https://cdn.discordapp.com/avatars/' . $u['id'] . '/' . $u['avatar'] . '.png?size=64';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Release-Ank&uuml;ndigung &ndash; ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
  <style>
    .beta-page { padding: 110px 0 80px; min-height: 100vh; }
    .beta-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--r-lg);
      max-width: 860px;
      margin: 0 auto;
      overflow: hidden;
    }
    .beta-banner img { width: 100%; max-height: 340px; object-fit: cover; object-position: center 20%; display: block; }
    .beta-inner { padding: 40px 56px 48px; }
    .beta-inner h1 { font-size: clamp(1.5rem, 3vw, 2rem); margin-bottom: 4px; }
    .beta-sub { color: var(--text-2); font-size: .9rem; margin-bottom: 32px; }
    .beta-inner p { color: var(--text-2); line-height: 1.8; font-size: .95rem; margin-bottom: 14px; }
    .beta-inner strong { color: var(--gold); }
    .beta-note {
      background: var(--purple-dim);
      border-left: 3px solid var(--purple);
      padding: 11px 16px;
      border-radius: 0 8px 8px 0;
      color: #c4b5fd;
      font-size: .9rem;
      margin-bottom: 14px;
    }
    .beta-note strong { color: #c4b5fd; }
    .beta-list { list-style: none; padding: 0; margin-bottom: 14px; }
    .beta-list li { padding-left: 20px; position: relative; color: var(--text-2); font-size: .95rem; line-height: 1.8; }
    .beta-list li::before { content: '-'; position: absolute; left: 0; color: var(--gold); }
    .info-box {
      background: var(--bg-card-2);
      border: 1px solid var(--border);
      border-left: 3px solid var(--discord);
      border-radius: 0 8px 8px 0;
      padding: 14px 16px;
      font-size: .9rem;
      color: var(--text-2);
      margin-bottom: 14px;
    }
    .info-box strong { color: var(--text); }
    .info-box a { color: var(--discord); text-decoration: underline; }
    .goal-box {
      background: rgba(34,197,94,.06);
      border: 1px solid rgba(34,197,94,.18);
      border-radius: 8px;
      padding: 14px 16px;
      font-size: .9rem;
      color: #86efac;
      margin-bottom: 14px;
    }
    .goal-box strong { color: var(--green); -webkit-text-fill-color: var(--green); }
    .time-box {
      background: var(--gold-dim);
      border: 1px solid rgba(232,184,109,.25);
      border-radius: 8px;
      padding: 14px 20px;
      font-size: 1rem;
      color: var(--text);
      text-align: center;
      margin-bottom: 24px;
    }
    .time-box strong { color: var(--gold); }
    hr.sep { border: none; border-top: 1px solid var(--border); margin: 20px 0; }
    .beta-sign { color: var(--text-3); font-size: .88rem; text-align: right; font-style: italic; margin-bottom: 32px; }
    .beta-actions { display: flex; gap: 12px; flex-wrap: wrap; padding-top: 4px; }
    .beta-actions .btn { min-width: 160px; justify-content: center; }
    @media (max-width: 640px) {
      .beta-inner { padding: 28px 20px 36px; }
      .beta-actions { flex-direction: column; }
    }
  </style>
</head>
<body>

<nav class="navbar scrolled" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#about">&Uuml;ber uns</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="regelwerk.php">Regeln</a></li>
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
        <a href="login.php" class="btn btn-discord" style="font-size:.85rem">Discord Login</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<?php include __DIR__ . '/includes/release_countdown.php'; ?>
<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<main class="beta-page">
  <div class="container">
    <div class="beta-card">

      <?php if ($hasBanner): ?>
      <div class="beta-banner">
        <img src="assets/img/beta-banner.jpg" alt="ViennaStateRP Beta-Start" />
      </div>
      <?php endif; ?>

      <div class="beta-inner">
        <div class="section-tag">Offizielle Ank&uuml;ndigung</div>
        <h1>ViennaStateRP &ndash; Release!</h1>
        <p class="beta-sub">Am 01.08.2026 um 20:00 Uhr ist es soweit!</p>

        <p>Liebe Community,<br>
        wir freuen uns, es euch endlich mitteilen zu k&ouml;nnen: <strong>ViennaStateRP geht am 01.08.2026 um 20:00 Uhr offiziell live!</strong></p>

        <p class="beta-note">Anmerkung: Zum Release wird der Server f&uuml;r alle <strong>Verifizierten</strong> freigeschaltet!</p>

        <p>Bis dahin befinden wir uns weiterhin in der <strong>Beta-Phase</strong> &ndash; wir entwickeln, testen
        und optimieren intensiv, damit ViennaStateRP zum Release stabil und bereit f&uuml;r euch ist.</p>

        <p>Bitte denkt daran, dass es sich bis zum Release noch um eine <strong>Beta</strong> handelt. Das bedeutet:</p>
        <ul class="beta-list">
          <li>Es k&ouml;nnen Bugs, Fehler oder unerwartete Probleme auftreten.</li>
          <li>Manche Systeme sind noch nicht final.</li>
          <li>Performance-Optimierungen laufen weiterhin im Hintergrund.</li>
        </ul>

        <hr class="sep" />

        <div class="info-box">
          <strong>&#128296; Probleme oder Bugs?</strong><br>
          Meldet euch &uuml;ber ein
          <a href="https://discord.com/channels/1520815251376050356/1520863619485536489" target="_blank" rel="noopener">Bugreport Ticket</a>
          oder den Channel
          <a href="https://discord.com/channels/1520815251376050356/1525093395771687054" target="_blank" rel="noopener">#bug-report</a>.
          Je genauer eure Beschreibung, desto schneller k&ouml;nnen wir reagieren.
        </div>

        <div class="goal-box">
          &#127919; <strong>Ziel bis zum Release:</strong><br>
          Gemeinsam mit euch wollen wir bis zum 01.08.2026 alle wichtigen Systeme finalisieren
          und ViennaStateRP stabil und sauber f&uuml;r den Release vorbereiten.
        </div>

        <div class="time-box">
          &#128640; Release: <strong>01.08.2026, 20:00 Uhr</strong>
        </div>

        <p>Wir freuen uns auf euch &ndash; und auf die ersten RP-Momente in Wien!<br>
        Danke f&uuml;r eure Unterst&uuml;tzung und viel Spa&szlig; beim Spielen!</p>

        <p class="beta-sign">Euer VSRP I Team</p>

        <div class="beta-actions">
          <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="btn btn-discord">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:18px;height:18px"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
            Discord beitreten
          </a>
          <a href="release.php?proceed=1" class="btn btn-primary">
            Zur Hauptseite &rarr;
          </a>
        </div>
      </div>

    </div>
  </div>
</main>

<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="index.php">Startseite</a>
      <a href="index.php#features">Features</a>
      <a href="players.php">Server Status</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="login.php">Login</a>
    </div>
    <div class="footer-links">
      <h5>Rechtliches</h5>
      <a href="impressum.php">Impressum</a>
      <a href="datenschutz.php">Datenschutzerkl&auml;rung</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten.</p>
  </div>
</footer>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>