<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/site_settings.php';

$user = current_user();

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
  <title>Regelwerk &ndash; ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
  <style>
    .regel-page { padding: 110px 0 80px; min-height: 100vh; }
    .regel-header { max-width: 720px; margin: 0 auto 48px; text-align: center; }
    .regel-header h1 { font-size: clamp(1.8rem, 4vw, 2.4rem); margin: 10px 0 14px; }
    .regel-header p { color: var(--text-2); font-size: 0.95rem; line-height: 1.7; }

    .regel-cat { margin-bottom: 44px; }
    .regel-cat-label { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
    .regel-cat-icon { font-size: 1.3rem; flex-shrink: 0; }
    .regel-cat-label h2 { font-family: 'Orbitron', sans-serif; font-size: clamp(0.95rem, 2vw, 1.15rem); font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; margin: 0; white-space: nowrap; }
    .regel-cat-line { flex: 1; height: 1px; background: linear-gradient(90deg, var(--border), transparent); }

    .regel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 800px) { .regel-grid { grid-template-columns: 1fr; } }

    .regel-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; margin-bottom: 0; }
    .regel-card-head { display: flex; align-items: center; gap: 10px; padding: 14px 20px; background: var(--bg-card-2); border-bottom: 1px solid var(--border); }
    .regel-card-head .ico { font-size: 1rem; }
    .regel-card-head h3 { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text); margin: 0; }
    .regel-list { list-style: none; margin: 0; padding: 10px 0; }
    .regel-list li { display: flex; align-items: flex-start; gap: 10px; padding: 9px 20px; font-size: 0.87rem; color: var(--text-2); line-height: 1.55; border-bottom: 1px solid var(--border); }
    .regel-list li:last-child { border-bottom: none; }
    .regel-list li strong { color: var(--text); font-weight: 600; }
    .penalty { display: inline-block; margin-left: auto; align-self: center; flex-shrink: 0; font-size: 0.62rem; font-family: 'Orbitron', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; padding: 2px 8px; border-radius: 4px; white-space: nowrap; }
    .penalty-warn { background: var(--gold-dim); color: var(--gold); border: 1px solid rgba(232,184,109,0.3); }
    .penalty-kick { background: rgba(249,115,22,0.1); color: #fb923c; border: 1px solid rgba(249,115,22,0.3); }
    .penalty-ban  { background: rgba(239,68,68,0.12); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }

    .regel-callout { background: var(--gold-dim); border: 1px solid rgba(232,184,109,0.25); border-left: 3px solid var(--gold); border-radius: 0 10px 10px 0; padding: 14px 20px; margin-bottom: 20px; font-size: 0.88rem; color: var(--text-2); line-height: 1.6; }
    .regel-callout strong { color: var(--gold); }

    .guideline-list { list-style: none; padding: 0; margin: 0 0 8px; display: flex; flex-direction: column; gap: 10px; }
    .guideline-list li { display: flex; gap: 14px; align-items: flex-start; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; font-size: 0.88rem; color: var(--text-2); line-height: 1.55; }
    .guideline-list li strong:first-child { color: var(--text); display: block; margin-bottom: 2px; font-size: 0.9rem; }
    .guideline-icon { font-size: 1.15rem; flex-shrink: 0; }

    .regel-cta { text-align: center; background: linear-gradient(135deg, var(--gold-dim), var(--purple-dim)); border: 1px solid rgba(232,184,109,0.25); border-radius: 14px; padding: 36px 24px; }
    .regel-cta .icon { font-size: 1.6rem; margin-bottom: 8px; }
    .regel-cta h3 { margin-bottom: 8px; }
    .regel-cta p { color: var(--text-2); font-size: 0.9rem; margin: 0 auto 20px; max-width: 480px; }
    .regel-cta .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar scrolled" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#about">&Uuml;ber uns</a></li>
      <li><a href="index.php#features">Features</a></li>
      <li><a href="index.php#fraktionen">Fraktionen</a></li>
      <li><a href="regelwerk.php" class="active">Regeln</a></li>
      <li><a href="changelog.php">Changelog</a></li>
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

<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<main class="regel-page">
  <div class="container">

    <div class="regel-header">
      <div class="section-tag">Fair Play</div>
      <h1>Regelwerk</h1>
      <p>
        Dieses Regelwerk gilt verbindlich f&uuml;r alle Spieler auf ViennaStateRP.
        Unwissenheit sch&uuml;tzt nicht vor Konsequenzen &ndash; mit dem Betreten des Servers
        erkl&auml;rst du dich mit allen Regeln einverstanden.
      </p>
    </div>

    <!-- 1. GRUNDLEGENDES ROLEPLAY -->
    <div class="regel-cat">
      <div class="regel-cat-label">
        <span class="regel-cat-icon">🎭</span>
        <h2>Grundlegendes Roleplay</h2>
        <div class="regel-cat-line"></div>
      </div>
      <div class="regel-grid">
        <div class="regel-card">
          <div class="regel-card-head"><span class="ico">🟢</span><h3>RP-Grundregeln</h3></div>
          <ul class="regel-list">
            <li><strong>Roleplay First:</strong> Das RP hat immer Vorrang &ndash; Entscheidungen werden im Charakter getroffen. <span class="penalty penalty-warn">Warn</span></li>
            <li><strong>New Life Regel:</strong> Nach dem Tod vergisst dein Charakter alle Ereignisse, die dazu f&uuml;hrten. <span class="penalty penalty-kick">Kick</span></li>
            <li><strong>Charakter-Konsistenz:</strong> Dein Charakter braucht eine glaubw&uuml;rdige, durchg&auml;ngige Pers&ouml;nlichkeit. <span class="penalty penalty-warn">Warn</span></li>
            <li><strong>Value of Life:</strong> Dein Charakter hat Angst um sein Leben und handelt entsprechend nachvollziehbar. <span class="penalty penalty-kick">Kick</span></li>
            <li><strong>PowerGaming:</strong> Situationen erzwingen, die dem Gegen&uuml;ber keine Reaktionschance lassen. <span class="penalty penalty-kick">Kick</span></li>
          </ul>
        </div>
        <div class="regel-card">
          <div class="regel-card-head"><span class="ico">🔴</span><h3>Absolute Verbote im RP</h3></div>
          <ul class="regel-list">
            <li><strong>Kein RDM:</strong> Grundloses T&ouml;ten ohne vorheriges RP-Gespr&auml;ch. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Kein VDM:</strong> Fahrzeuge als prim&auml;re Waffe ohne RP-Hintergrund. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Metagaming:</strong> OOC-Informationen (Discord, Stream) im RP verwenden. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Combat-Logging:</strong> Ausloggen w&auml;hrend einer aktiven RP-Szene. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Safe Zones:</strong> Kein PvP in Krankenh&auml;usern und Spawn-Bereichen. <span class="penalty penalty-ban">Ban</span></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- 2. KOMMUNIKATION & VERHALTEN -->
    <div class="regel-cat">
      <div class="regel-cat-label">
        <span class="regel-cat-icon">💬</span>
        <h2>Kommunikation &amp; Verhalten</h2>
        <div class="regel-cat-line"></div>
      </div>
      <div class="regel-grid">
        <div class="regel-card">
          <div class="regel-card-head"><span class="ico">🗣️</span><h3>Server-Kommunikation</h3></div>
          <ul class="regel-list">
            <li><strong>Sprache:</strong> Auf dem Server wird ausschlie&szlig;lich Deutsch gesprochen. <span class="penalty penalty-warn">Warn</span></li>
            <li><strong>Respekt:</strong> Pers&ouml;nliche Beleidigungen, Diskriminierung und Hate-Speech. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>OOC-Chat:</strong> Nur f&uuml;r dringende technische Probleme &ndash; kein Smalltalk. <span class="penalty penalty-warn">Warn</span></li>
            <li><strong>Admin-Entscheidungen:</strong> Werden akzeptiert, Einspruch nur &uuml;ber ein Ticket. <span class="penalty penalty-kick">Kick</span></li>
            <li><strong>Streaming:</strong> Erlaubt &ndash; Metagaming durch Zuschauer liegt beim Streamer. <span class="penalty penalty-warn">Warn</span></li>
          </ul>
        </div>
        <div class="regel-card">
          <div class="regel-card-head"><span class="ico">🚫</span><h3>Absolute Verbote (OOC)</h3></div>
          <ul class="regel-list">
            <li><strong>Cheating / Hacking / Bugusing:</strong> Jegliche Modifikationen mit Vorteil. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>NSFW-RP ohne Einwilligung:</strong> Absolut verboten, keine Ausnahmen. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Real-Life-Drohungen:</strong> Drohungen gegen Mitspieler au&szlig;erhalb des RP. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Real Money Trading (RMT):</strong> Echtgeld-Handel f&uuml;r Ingame-G&uuml;ter. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Doxing:</strong> Ver&ouml;ffentlichen privater Daten anderer Spieler. <span class="penalty penalty-ban">Ban</span></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- 3. ÜBERFÄLLE & CRIME-RP -->
    <div class="regel-cat">
      <div class="regel-cat-label">
        <span class="regel-cat-icon">🏦</span>
        <h2>&Uuml;berf&auml;lle &amp; Crime-RP</h2>
        <div class="regel-cat-line"></div>
      </div>

      <div class="regel-callout">
        <strong>⚠ Voraussetzung f&uuml;r &Uuml;berf&auml;lle:</strong>
        Ein &Uuml;berfall darf erst gestartet werden, wenn die Mindestanzahl an Polizisten im Dienst erreicht ist.
      </div>

      <div style="overflow-x:auto;margin-bottom:24px">
        <table style="width:100%;border-collapse:collapse;font-size:.88rem">
          <thead>
            <tr style="border-bottom:2px solid rgba(232,184,109,.3)">
              <th style="padding:12px 16px;text-align:left;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Event</th>
              <th style="padding:12px 16px;text-align:center;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Min. Cops</th>
              <th style="padding:12px 16px;text-align:center;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Max. R&auml;uber</th>
              <th style="padding:12px 16px;text-align:left;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Polizei</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $matrix = [
              ['24/7 / Tankstelle',                     '3', '2',  'Alle verfügbaren · MAX. 4'],
              ['Juwelier',                              '6', '4',  'Alle verfügbaren · MAX. 6 + SOKO bei krit. Lage'],
              ['Fleeca Bank (klein)',                   '6', '5',  'Alle verfügbaren · SOKO erlaubt'],
              ['💫 Pacific Bank / Humane Labs (Event)', '8', '10', 'Unbegrenzt · SOKO erlaubt'],
            ];
            foreach ($matrix as $i => [$ev, $cops, $rob, $pol]): ?>
            <tr style="border-bottom:1px solid var(--border);<?= $i % 2 === 0 ? 'background:rgba(255,255,255,.02)' : '' ?>">
              <td style="padding:12px 16px;color:#e2e8f0;font-weight:600"><?= $ev ?></td>
              <td style="padding:12px 16px;text-align:center"><span style="background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:#60a5fa;border-radius:6px;padding:2px 10px;font-family:'Orbitron',sans-serif;font-size:.85rem"><?= $cops ?></span></td>
              <td style="padding:12px 16px;text-align:center"><span style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:6px;padding:2px 10px;font-family:'Orbitron',sans-serif;font-size:.85rem"><?= $rob ?></span></td>
              <td style="padding:12px 16px;color:#94a3b8;font-size:.83rem"><?= $pol ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <ul class="guideline-list">
        <li>
          <span class="guideline-icon">🤝</span>
          <div><strong>Verhandlungspflicht</strong>Vor dem Schusswechsel muss eine Verhandlung zwischen Polizei und R&auml;ubern stattfinden. Kopfloser Beschuss ohne Forderungen gilt als FailRP.</div>
        </li>
        <li>
          <span class="guideline-icon">🚗</span>
          <div><strong>Fluchtfahrzeuge</strong>Unlogische Fluchtmittel (z.&nbsp;B. Helikopter am Dach bei einem kleinen Ladenraub) sind untersagt.</div>
        </li>
        <li>
          <span class="guideline-icon">⏱️</span>
          <div><strong>Cooldown</strong>Nach einem &Uuml;berfall gilt f&uuml;r die beteiligte Crime-Fraktion eine Sperre von <strong>1 Stunde</strong>, bevor das n&auml;chste Event gestartet werden darf.</div>
        </li>
        <li>
          <span class="guideline-icon">🔫</span>
          <div><strong>Cop-Baiting</strong>Polizisten absichtlich ohne RP-Absicht in Situationen locken, um einen &Uuml;berfall zu erm&ouml;glichen. <span class="penalty penalty-warn" style="margin-left:8px">Warn</span></div>
        </li>
        <li>
          <span class="guideline-icon">📡</span>
          <div><strong>Externe Kommunikation</strong>Absprachen zwischen R&auml;ubern und Au&szlig;enstehenden &uuml;ber externe Discord-Calls gelten als Metagaming. <span class="penalty penalty-ban" style="margin-left:8px">Ban</span></div>
        </li>
      </ul>
    </div>

    <!-- 4. FRAKTIONEN & WIRTSCHAFT -->
    <div class="regel-cat">
      <div class="regel-cat-label">
        <span class="regel-cat-icon">🏛️</span>
        <h2>Fraktionen &amp; Wirtschaft</h2>
        <div class="regel-cat-line"></div>
      </div>
      <div class="regel-grid">
        <div class="regel-card">
          <div class="regel-card-head"><span class="ico">👮</span><h3>Fraktionsregeln</h3></div>
          <ul class="regel-list">
            <li><strong>Loyalit&auml;t:</strong> Nur einer Fraktion gleichzeitig angeh&ouml;ren. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Fraktionswechsel:</strong> Nur &uuml;ber einen offiziellen RP-Abgang m&ouml;glich. <span class="penalty penalty-kick">Kick</span></li>
            <li><strong>Interne Hierarchie:</strong> Anweisungen von Fraktionsf&uuml;hrern werden im RP befolgt (sofern regelkonform). <span class="penalty penalty-warn">Warn</span></li>
            <li><strong>Geheimhaltung:</strong> Interne Fraktionsinfos nicht au&szlig;erhalb des RP weitergeben. <span class="penalty penalty-kick">Kick</span></li>
            <li><strong>Uniformen:</strong> Staatliche Mitglieder tragen im Dienst die vorgeschriebene Kleidung. <span class="penalty penalty-warn">Warn</span></li>
          </ul>
        </div>
        <div class="regel-card">
          <div class="regel-card-head"><span class="ico">🚘</span><h3>Fahrzeuge &amp; Wirtschaft</h3></div>
          <ul class="regel-list">
            <li><strong>Fahrzeuge im RP:</strong> Charaktereigentum &ndash; kein Verleihen ohne RP-Absprache. <span class="penalty penalty-warn">Warn</span></li>
            <li><strong>Stunts:</strong> Unrealistische Man&ouml;ver ohne RP-Kontext sind verboten. <span class="penalty penalty-warn">Warn</span></li>
            <li><strong>Fahrzeugdiebstahl:</strong> Nur mit RP-Vorgeschichte, nicht direkt vor dem Spawn. <span class="penalty penalty-kick">Kick</span></li>
            <li><strong>Wirtschafts-Bugs:</strong> Ausnutzen von Dupe-/Wirtschafts-Glitches. <span class="penalty penalty-ban">Ban</span></li>
            <li><strong>Bugusing generell:</strong> Melden statt ausnutzen &ndash; siehe Meldung &uuml;ber Discord. <span class="penalty penalty-ban">Ban</span></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- CTA -->
    <div class="regel-cta">
      <div class="icon">📜</div>
      <h3>Regelwerk verstanden?</h3>
      <p>Tritt jetzt unserem Discord bei, verifiziere dich und starte dein Roleplay-Abenteuer in Wien.</p>
      <div class="actions">
        <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="btn btn-discord">Discord beitreten</a>
        <a href="index.php" class="btn btn-outline">&larr; Zur Startseite</a>
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
      <a href="allgemeine-hinweise.php">Allgemeine Hinweise</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten.</p>
  </div>
</footer>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
