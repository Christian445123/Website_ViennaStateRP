<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/changelog.php';

$user    = current_user();
$entries = isset($pdo) ? changelog_all($pdo, true) : [];

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
  <title>Changelog &ndash; ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
</head>
<body>

<nav class="navbar scrolled" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#about">&Uuml;ber uns</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="index.php#fraktionen">Fraktionen</a></li>
      <li><a href="regelwerk.php">Regeln</a></li>
      <li><a href="changelog.php" class="active">Changelog</a></li>
      <li><a href="index.php#join">Beitreten</a></li>
      <li><a href="index.php#faq">FAQ</a></li>
      <li><a href="players.php">Server Status</a></li>
      <li><a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a></li>
    </ul>
    <div class="nav-actions">
      <a href="https://ko-fi.com/viennastaterp" target="_blank" rel="noopener" class="btn btn-kofi" style="font-size:.85rem">☕ Unterstützen</a>
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
<?php include __DIR__ . '/includes/restart_banner.php'; ?>

<main class="changelog-page">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Updates</span>
      <h2>Change<span class="accent">log</span></h2>
      <p>Alle Neuerungen, Verbesserungen und Fixes von ViennaStateRP im &Uuml;berblick.</p>
    </div>

    <?php if (!$entries): ?>
      <div class="changelog-empty">Es sind noch keine Changelog-Eintr&auml;ge vorhanden.</div>
    <?php else: ?>
      <div class="timeline">
        <?php foreach ($entries as $entry):
          $meta = changelog_tag_meta($entry['tag']);
          $date = DateTime::createFromFormat('Y-m-d', $entry['entry_date']);
        ?>
        <div class="timeline-item">
          <div class="timeline-dot" style="background:<?= htmlspecialchars($meta['color']) ?>"></div>
          <div class="timeline-card">
            <div class="timeline-card-head">
              <span class="timeline-tag" style="background:<?= htmlspecialchars($meta['color']) ?>1a;color:<?= htmlspecialchars($meta['color']) ?>;border-color:<?= htmlspecialchars($meta['color']) ?>4d"><?= htmlspecialchars($meta['label']) ?></span>
              <?php if ($entry['version'] !== ''): ?>
                <span class="timeline-version"><?= htmlspecialchars($entry['version']) ?></span>
              <?php endif; ?>
              <span class="timeline-date"><?= $date ? htmlspecialchars($date->format('d.m.Y')) : htmlspecialchars($entry['entry_date']) ?></span>
            </div>
            <h3 class="timeline-title"><?= htmlspecialchars($entry['title']) ?></h3>
            <?php if ($entry['points']): ?>
            <ul class="timeline-list">
              <?php foreach ($entry['points'] as $point): ?>
                <li><?= htmlspecialchars($point) ?></li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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
      <a href="index.php#faq">FAQ</a>
      <a href="players.php">Server Status</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="login.php">Login</a>
    </div>
    <div class="footer-links">
      <h5>Unterstützung</h5>
      <a href="https://www.paypal.com/paypalme/chrisgaming445" target="_blank" rel="noopener">PayPal</a>
      <a href="https://ko-fi.com/viennastaterp" target="_blank" rel="noopener">Ko-fi</a>
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
