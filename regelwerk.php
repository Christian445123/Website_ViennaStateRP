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

    /* ── "PDF viewer" chrome ─────────────────────────────────────── */
    .pdf-toolbar {
      max-width: 840px; margin: 0 auto; background: linear-gradient(#3a3a3f, #2c2c30);
      border-radius: 8px 8px 0 0; padding: 11px 20px; display: flex; align-items: center; gap: 10px;
      font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      box-shadow: 0 -1px 0 rgba(255,255,255,.06) inset, 0 6px 16px rgba(0,0,0,.35);
      position: sticky; top: 84px; z-index: 5;
    }
    .pdf-toolbar-dots { display: flex; gap: 6px; margin-right: 6px; }
    .pdf-toolbar-dots span { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .pdf-toolbar-dots span:nth-child(1) { background: #ff5f57; }
    .pdf-toolbar-dots span:nth-child(2) { background: #febc2e; }
    .pdf-toolbar-dots span:nth-child(3) { background: #28c840; }
    .pdf-toolbar-icon { font-size: 1rem; }
    .pdf-toolbar-name { color: #ececec; font-size: .82rem; font-weight: 600; }
    .pdf-toolbar-meta {
      margin-left: auto; color: #c9c5b8; font-size: .68rem; white-space: nowrap;
      background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
      padding: 3px 10px; border-radius: 20px;
    }

    .pdf-container { max-width: 840px; margin: 0 auto; padding: 22px 0 6px; }

    /* ── the paper page itself ───────────────────────────────────── */
    .pdf-page {
      background: #faf8f3; color: #262620; max-width: 840px; margin: 0 auto 34px;
      padding: 60px 68px 44px; min-height: 1040px; display: flex; flex-direction: column;
      box-shadow:
        0 1px 0 rgba(255,255,255,.7) inset,
        0 1px 2px rgba(0,0,0,.06),
        0 24px 50px -8px rgba(0,0,0,.55),
        0 4px 14px rgba(0,0,0,.35);
      border-radius: 2px; position: relative;
      font-family: Georgia, 'Times New Roman', Cambria, serif; line-height: 1.68;
    }
    .pdf-page::before {
      content: ''; position: absolute; inset: 16px; border: 1px solid rgba(32,31,26,.08);
      pointer-events: none;
    }
    .pdf-page::after {
      content: ''; position: absolute; top: 0; right: 0; width: 0; height: 0;
      border-style: solid; border-width: 0 30px 30px 0; border-color: transparent #ece6d6 transparent transparent;
      filter: drop-shadow(-2px 2px 2px rgba(0,0,0,.18));
    }
    .pdf-page:last-of-type { margin-bottom: 0; }
    @media (max-width: 900px) { .pdf-page { padding: 40px 24px 34px; min-height: 0; } }

    .pdf-page-header {
      display: flex; justify-content: space-between; align-items: center;
      font-family: Arial, Helvetica, sans-serif; font-size: .66rem; letter-spacing: .6px;
      color: #a3987e; text-transform: uppercase; border-bottom: 1px solid #e4dfd2;
      padding-bottom: 12px; margin-bottom: 30px; position: relative; z-index: 1;
    }
    .pdf-page-footer {
      margin-top: auto; padding-top: 14px; border-top: 1px solid #e4dfd2;
      display: flex; justify-content: space-between; align-items: center;
      font-family: Arial, Helvetica, sans-serif; font-size: .66rem; color: #a3987e; letter-spacing: .5px;
      position: relative; z-index: 1;
    }

    /* ── cover page ───────────────────────────────────────────────── */
    .pdf-cover { text-align: center; margin-bottom: 18px; position: relative; z-index: 1; }
    .pdf-cover .pdf-crest {
      width: 52px; height: 52px; margin: 0 auto 18px; border-radius: 50%;
      border: 2px solid #9c6b1f; display: flex; align-items: center; justify-content: center;
      font-family: Arial, Helvetica, sans-serif; font-size: 1rem; font-weight: 800;
      color: #8a6a2e; letter-spacing: .5px;
      box-shadow: 0 0 0 4px #faf8f3, 0 0 0 5px rgba(156,107,31,.3);
    }
    .pdf-cover .pdf-tag {
      display: inline-block; font-family: Arial, Helvetica, sans-serif; font-size: .66rem;
      letter-spacing: 2.5px; text-transform: uppercase; color: #8a6a2e; border: 1px solid #c9a968;
      border-radius: 20px; padding: 4px 14px; margin-bottom: 20px;
    }
    .pdf-cover h1 {
      font-family: Arial, Helvetica, sans-serif; font-size: clamp(1.9rem, 5vw, 2.6rem);
      letter-spacing: 1px; margin: 0 0 8px; color: #201f1a; text-transform: uppercase; font-weight: 800;
    }
    .pdf-cover .pdf-subtitle {
      font-family: Arial, Helvetica, sans-serif; font-size: .82rem; letter-spacing: 3px;
      text-transform: uppercase; color: #a08b5c; margin-bottom: 24px;
    }
    .pdf-cover hr { border: none; border-top: 3px double #201f1a; width: 90px; margin: 0 auto 24px; }
    .pdf-cover p {
      color: #4a453c; font-size: .95rem; max-width: 580px; margin: 0 auto;
      text-align: justify; hyphens: auto;
    }

    /* ── numbered sections ───────────────────────────────────────── */
    .regel-cat { margin-bottom: 32px; position: relative; z-index: 1; }
    .regel-cat:last-child { margin-bottom: 0; }
    .regel-cat-label {
      display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px;
      border-bottom: 3px double #201f1a; padding-bottom: 9px;
    }
    .regel-cat-icon { font-size: .95rem; }
    .regel-cat-label h2 {
      font-family: Arial, Helvetica, sans-serif; font-size: .95rem; font-weight: 800;
      letter-spacing: .4px; margin: 0; color: #5a3e14; text-transform: uppercase;
    }
    .regel-cat-line { display: none; }

    .regel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 26px; }
    @media (max-width: 760px) { .regel-grid { grid-template-columns: 1fr; } }

    .regel-card { background: transparent; border: 1px solid #e0dbcc; border-radius: 3px; overflow: hidden; box-shadow: 0 1px 2px rgba(32,31,26,.04); }
    .regel-card-head { padding: 8px 15px; background: #f1ede2; border-bottom: 1px solid #e0dbcc; display: flex; align-items: center; gap: 8px; }
    .regel-card-head .ico { font-size: .82rem; }
    .regel-card-head h3 { font-family: Arial, Helvetica, sans-serif; font-size: .7rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #4a3f27; margin: 0; }
    .regel-list { list-style: none; margin: 0; padding: 6px 0; }
    .regel-list li { padding: 7px 15px; font-size: .86rem; color: #332f26; border-bottom: 1px solid #ece7d9; text-align: justify; hyphens: auto; }
    .regel-list li:last-child { border-bottom: none; }
    .regel-list li strong { color: #201f1a; font-weight: 700; }

    .penalty {
      display: inline-block; margin-left: 6px; font-family: Arial, Helvetica, sans-serif;
      font-size: .56rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
      padding: 1px 8px; border-radius: 2px; border: 1.5px solid; background: transparent;
      white-space: nowrap; transform: rotate(-3deg); vertical-align: middle;
    }
    .penalty-warn { color: #9a6a10; border-color: #9a6a10; }
    .penalty-kick { color: #a1530f; border-color: #a1530f; }
    .penalty-ban  { color: #8f1f1f; border-color: #8f1f1f; }

    .regel-callout {
      background: #f1ede2; border: 1px solid #e0dbcc; border-left: 3px solid #9c6b1f;
      border-radius: 0 3px 3px 0; padding: 12px 18px; margin-bottom: 16px;
      font-size: .86rem; color: #332f26; line-height: 1.6; text-align: justify; hyphens: auto;
    }
    .regel-callout strong { color: #201f1a; }

    .guideline-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
    .guideline-list li { display: flex; gap: 12px; align-items: flex-start; border: 1px solid #e0dbcc; border-radius: 3px; padding: 10px 15px; font-size: .86rem; color: #332f26; line-height: 1.55; box-shadow: 0 1px 2px rgba(32,31,26,.04); }
    .guideline-list li div { text-align: justify; hyphens: auto; }
    .guideline-list li strong:first-child { color: #201f1a; display: block; margin-bottom: 2px; font-family: Arial, Helvetica, sans-serif; font-size: .76rem; letter-spacing: .3px; text-transform: uppercase; }
    .guideline-icon { font-size: .95rem; flex-shrink: 0; }

    /* ── CTA (stays in normal site theme, below the "document") ──── */
    .regel-cta { text-align: center; background: linear-gradient(135deg, var(--gold-dim), var(--purple-dim)); border: 1px solid rgba(232,184,109,0.25); border-radius: 14px; padding: 36px 24px; max-width: 840px; margin: 34px auto 0; }
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

<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<main class="regel-page">
  <div class="container">

    <!-- PDF-Viewer-Chrome -->
    <div class="pdf-toolbar">
      <span class="pdf-toolbar-dots"><span></span><span></span><span></span></span>
      <span class="pdf-toolbar-icon">📄</span>
      <span class="pdf-toolbar-name">Regelwerk_ViennaStateRP.pdf</span>
      <span class="pdf-toolbar-meta">Stand: <?= date('d.m.Y') ?> · 5 Seiten</span>
    </div>

    <div class="pdf-container">

      <!-- ══════════════════ SEITE 1 ══════════════════ -->
      <div class="pdf-page">

        <div class="pdf-cover">
          <div class="pdf-crest">VS</div>
          <div class="pdf-tag">Offizielles Dokument</div>
          <h1>Regelwerk</h1>
          <div class="pdf-subtitle">ViennaStateRP · Roleplay Server</div>
          <hr>
          <p>
            Dieses Regelwerk gilt f&uuml;r s&auml;mtliche Spieler, Fraktionen und Teammitglieder auf ViennaStateRP.
            Mit dem Betreten des Servers best&auml;tigt jeder Spieler, dass er das Regelwerk gelesen, verstanden
            und akzeptiert hat &ndash; Unwissenheit sch&uuml;tzt nicht vor Konsequenzen.
            Unser Ziel ist es nicht, jede Situation zu gewinnen, sondern gemeinsam eine glaubw&uuml;rdige Geschichte zu schaffen.
          </p>
        </div>

        <!-- 1. ALLGEMEINES -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">📖</span>
            <h2>1. Allgemeines</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-grid">
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">🧠</span><h3>1.1 Grundsatz</h3></div>
              <ul class="regel-list">
                <li>Dein Charakter kennt nur das, was dein Charakter auch tats&auml;chlich wissen kann.</li>
                <li>OOC-Wissen darf niemals zum Vorteil des eigenen Charakters verwendet werden.</li>
              </ul>
            </div>
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">⚖️</span><h3>1.2 Hausrecht</h3></div>
              <ul class="regel-list">
                <li>Das Serverteam entscheidet bei Situationen, die nicht ausdr&uuml;cklich im Regelwerk stehen, im Sinne eines fairen und realistischen Roleplays.</li>
                <li>Das bewusste Ausnutzen von Regel-L&uuml;cken oder Umgehen des Regelwerks ist nicht gestattet.</li>
                <li>„Es steht nicht ausdr&uuml;cklich in den Regeln&ldquo; ist kein Freifahrtschein.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 2. GRUNDLEGENDES ROLEPLAY -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🎭</span>
            <h2>2. Grundlegendes Roleplay</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-grid">
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">🟢</span><h3>RP-Pflicht &amp; Value of Life</h3></div>
              <ul class="regel-list">
                <li><strong>2.1 Roleplay-Pflicht:</strong> Jederzeit aktives und nachvollziehbares RP betreiben. OOC-Gespr&auml;che sind auf das n&ouml;tige Minimum zu beschr&auml;nken und d&uuml;rfen laufendes RP nicht st&ouml;ren.</li>
                <li><strong>2.4 Sch&uuml;tze dein Leben:</strong> Das eigene Leben ist jederzeit angemessen zu sch&uuml;tzen. In lebensbedrohlichen Situationen muss entsprechend reagiert werden.</li>
                <li><strong>Kein Helden-RP:</strong> Bewusstes „Helden-RP&ldquo; oder unn&ouml;tiges Provozieren in lebensbedrohlichen Situationen ist verboten.</li>
              </ul>
            </div>
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">🔴</span><h3>FailRP &amp; Powergaming</h3></div>
              <ul class="regel-list">
                <li><strong>2.2 FailRP:</strong> Unrealistisches Verhalten, das nicht zur RP-Situation bzw. zum eigenen Charakter passt &ndash; z.&nbsp;B. nach einem schweren Unfall folgenlos weiterspielen oder trotz schwerer Verletzungen normal k&auml;mpfen/rennen.</li>
                <li><strong>2.3 Powergaming:</strong> Das Erzwingen von Handlungen gegen&uuml;ber anderen Spielern, ohne ihnen eine angemessene Reaktionsm&ouml;glichkeit zu geben.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 3. NEUE LEBENSREGEL -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">💤</span>
            <h2>3. Neue Lebensregel (nach Tot)</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🌀</span><h3>New Life Rule</h3></div>
            <ul class="regel-list">
              <li>Man kann auf ViennaStateRP nicht sterben, sondern nur ins Koma fallen. Nach dem Erwachen beginnt eine neue Lebenssituation.</li>
              <li>Der Charakter darf sich nicht an die Situation erinnern und daraus keinen weiteren RP-Vorteil ziehen &ndash; dazu z&auml;hlen insbesondere Informationen &uuml;ber beteiligte Personen, Orte, Fahrzeuge oder Ereignisse.</li>
              <li>Ein erneutes Aufgreifen der Situation ist erst nach einem nachvollziehbaren RP-Neueinstieg erlaubt.</li>
            </ul>
          </div>
        </div>

        <!-- 4. RDM, VDM & GEWALT -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🔫</span>
            <h2>4. RDM, VDM &amp; Gewalt</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-grid">
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">🚫</span><h3>4.1 RDM &amp; 4.2 VDM</h3></div>
              <ul class="regel-list">
                <li><strong>RDM:</strong> Grundloses Verletzen oder T&ouml;ten eines Spielers ohne nachvollziehbaren RP-Hintergrund ist verboten. Gewalt und Schusswechsel m&uuml;ssen aus einer bestehenden RP-Situation entstehen. <span class="penalty penalty-ban">Ban</span></li>
                <li><strong>VDM:</strong> Fahrzeuge d&uuml;rfen nicht absichtlich als Waffe gegen andere Spieler eingesetzt werden. Bewusstes &Uuml;berfahren oder grundloses Rammen ist verboten. <span class="penalty penalty-ban">Ban</span></li>
              </ul>
            </div>
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">💥</span><h3>4.3 Schusswechsel</h3></div>
              <ul class="regel-list">
                <li>Schusswechsel m&uuml;ssen aus einem nachvollziehbaren Konflikt entstehen. Grundloses oder kopfloses Schie&szlig;en ohne RP-Hintergrund ist verboten.</li>
                <li>Ein Schusswechsel muss nicht zwingend mit einem langen Gespr&auml;ch beginnen, jedoch muss eine nachvollziehbare Situation bzw. ein entsprechender Konflikt bestehen.</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Seite 1 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 2 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Abschnitt 5&ndash;9</span>
        </div>

        <!-- 5. METAGAMING -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🕵️</span>
            <h2>5. Metagaming</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📵</span><h3>OOC-Wissen im RP</h3></div>
            <ul class="regel-list">
              <li>Metagaming ist die Nutzung von Informationen, die der eigene Charakter nicht auf regul&auml;rem RP-Weg erhalten hat. <span class="penalty penalty-ban">Ban</span></li>
              <li>OOC-Informationen aus Discord, Streams, Chats, externen Gespr&auml;chen oder anderen Quellen d&uuml;rfen nicht f&uuml;r das Ingame-RP genutzt werden.</li>
              <li><em>Beispiel:</em> Ein Spieler sieht in einem Stream, dass sich ein Polizist an einem bestimmten Ort befindet, und f&auml;hrt anschlie&szlig;end mit seinem Charakter dorthin.</li>
            </ul>
          </div>
        </div>

        <!-- 6. OOC-KOMMUNIKATION -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">💬</span>
            <h2>6. OOC-Kommunikation</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-grid">
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">🗣️</span><h3>6.1 Voice</h3></div>
              <ul class="regel-list">
                <li>Auf ViennaStateRP wird grunds&auml;tzlich Deutsch/&Ouml;sterreichisch gesprochen.</li>
                <li>OOC-Kommunikation w&auml;hrend einer laufenden RP-Situation ist zu vermeiden und darf das RP anderer Spieler nicht st&ouml;ren.</li>
              </ul>
            </div>
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">⌨️</span><h3>6.2 OOC-Chat</h3></div>
              <ul class="regel-list">
                <li>Der OOC-Chat ist ausschlie&szlig;lich f&uuml;r wichtige technische oder serverbezogene Angelegenheiten vorgesehen.</li>
                <li>OOC-Streit, unn&ouml;tiger Smalltalk und Diskussionen &uuml;ber laufende RP-Situationen geh&ouml;ren nicht in den OOC-Chat.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 7. COMBAT LOGGING -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🔌</span>
            <h2>7. Combat Logging (RP-Flucht)</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🚪</span><h3>Ausloggen im RP</h3></div>
            <ul class="regel-list">
              <li>Das absichtliche Verlassen des Servers w&auml;hrend einer laufenden RP-Situation ist verboten. <span class="penalty penalty-ban">Ban</span></li>
              <li>Bei technischen Problemen ist die Situation nach M&ouml;glichkeit zu dokumentieren und dem Team zu melden.</li>
            </ul>
          </div>
        </div>

        <!-- 8. SAFEZONE -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🛡️</span>
            <h2>8. Safezone</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-callout">
            Auf ViennaStateRP gibt es grunds&auml;tzlich <strong>keine allgemeinen Safezones</strong>. Auch Krankenh&auml;user,
            Polizeistationen oder Gesch&auml;fte sind keine automatisch gesch&uuml;tzten Bereiche &ndash; das RP muss dort dennoch
            realistisch und angemessen ausgespielt werden.
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">✈️</span><h3>8.1 Ankunftsbereich</h3></div>
            <ul class="regel-list">
              <li>Der Ankunftsbereich am Flughafen ist die <strong>einzige offizielle Safezone</strong> und dient dem Schutz neu einreisender Spieler.</li>
              <li>Dort sind RDM, VDM, Entf&uuml;hrungen, Schie&szlig;ereien und grundlose Gewalt verboten. <span class="penalty penalty-ban">Ban</span></li>
            </ul>
          </div>
        </div>

        <!-- 9. ÜBERFÄLLE & CRIME-RP -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🏦</span>
            <h2>9. &Uuml;berf&auml;lle &amp; Crime-RP</h2>
            <div class="regel-cat-line"></div>
          </div>

          <div class="regel-callout">
            <strong>⚠ 9.1 Mindestanzahl Polizei:</strong>
            Ein &Uuml;berfall darf nur gestartet werden, wenn die erforderliche Anzahl an Polizisten im Dienst ist.
            Die Serverleitung kann diese Werte bei Bedarf an die aktuelle Serverauslastung anpassen.
          </div>

          <div style="overflow-x:auto;margin-bottom:20px">
            <table style="width:100%;border-collapse:collapse;font-size:.86rem">
              <thead>
                <tr style="border-bottom:2px solid #c9a968">
                  <th style="padding:10px 14px;text-align:left;color:#7a5a12;font-family:Arial,Helvetica,sans-serif;font-size:.72rem;text-transform:uppercase;letter-spacing:1.2px">&Uuml;berfall</th>
                  <th style="padding:10px 14px;text-align:center;color:#7a5a12;font-family:Arial,Helvetica,sans-serif;font-size:.72rem;text-transform:uppercase;letter-spacing:1.2px">Polizei</th>
                  <th style="padding:10px 14px;text-align:center;color:#7a5a12;font-family:Arial,Helvetica,sans-serif;font-size:.72rem;text-transform:uppercase;letter-spacing:1.2px">R&auml;uber</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $matrix = [
                  ['Mitmenschen',   '1', '1'],
                  ['Bankomat',      '2', '1'],
                  ['Bank',          '4', '3'],
                  ['Yacht',         '6', '4'],
                  ['Nationalbank',  '7', '5'],
                ];
                foreach ($matrix as $i => [$ev, $cops, $rob]): ?>
                <tr style="border-bottom:1px solid #ece7d9;<?= $i % 2 === 0 ? 'background:rgba(0,0,0,.02)' : '' ?>">
                  <td style="padding:10px 14px;color:#201f1a;font-weight:700"><?= $ev ?></td>
                  <td style="padding:10px 14px;text-align:center"><span style="background:#e3edf7;border:1px solid #a9c6de;color:#2f5f8f;border-radius:4px;padding:2px 10px;font-family:Arial,Helvetica,sans-serif;font-size:.82rem"><?= $cops ?></span></td>
                  <td style="padding:10px 14px;text-align:center"><span style="background:#f6dcdc;border:1px solid #c15a5a;color:#7a1f1f;border-radius:4px;padding:2px 10px;font-family:Arial,Helvetica,sans-serif;font-size:.82rem"><?= $rob ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p style="color:#7a7364;font-size:.76rem;margin-top:8px">Richtwerte &ndash; k&ouml;nnen von der Serverleitung an die aktuelle Situation angepasst werden.</p>
          </div>

          <ul class="guideline-list">
            <li>
              <span class="guideline-icon">🤝</span>
              <div><strong>9.2 Verhandlungen</strong>Vor einem Schusswechsel muss grunds&auml;tzlich eine angemessene M&ouml;glichkeit zur Verhandlung bestehen. Ein grundloser oder sofortiger Schusswechsel ohne nachvollziehbaren RP-Hintergrund ist verboten.</div>
            </li>
            <li>
              <span class="guideline-icon">🙋</span>
              <div><strong>9.3 Forderungen &amp; Geiseln</strong>Forderungen bei Geiselnahmen m&uuml;ssen realistisch und RP-konform sein und k&ouml;nnen vom Einsatzleiter abgelehnt werden. Fake-Geiseln oder abgesprochene Geiseln zur Erlangung eines spielerischen Vorteils sind verboten. <span class="penalty penalty-ban" style="margin-left:8px">Ban</span></div>
            </li>
          </ul>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Seite 2 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 3 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Abschnitt 10&ndash;14</span>
        </div>

        <!-- 10. FLUCHTFAHRZEUGE -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🚗</span>
            <h2>10. Fluchtfahrzeuge</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🏎️</span><h3>Einsatz von Fluchtmitteln</h3></div>
            <ul class="regel-list">
              <li>Fluchtfahrzeuge m&uuml;ssen zur jeweiligen RP-Situation passen und realistisch eingesetzt werden.</li>
              <li>Unrealistische Fluchtmittel oder Fahrzeugwechsel ohne nachvollziehbaren RP-Grund sind verboten.</li>
              <li>Das Fahrzeug darf nicht dazu genutzt werden, eine laufende RP-Situation durch unrealistische M&ouml;glichkeiten zu umgehen.</li>
            </ul>
          </div>
        </div>

        <!-- 11. COP-BAITING -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🎣</span>
            <h2>11. Cop-Baiting</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🚔</span><h3>Provokation der Polizei</h3></div>
            <ul class="regel-list">
              <li>Cop-Baiting ist das absichtliche Provozieren von Polizeikr&auml;ften, um eine Verfolgung oder einen Einsatz ohne nachvollziehbaren RP-Grund zu erzwingen. <span class="penalty penalty-warn">Warn</span></li>
              <li>Normales kriminelles RP und daraus entstehende Polizeiverfolgungen sind selbstverst&auml;ndlich erlaubt.</li>
            </ul>
          </div>
        </div>

        <!-- 12 & 13. FRAKTIONSREGELN -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🏛️</span>
            <h2>12. &amp; 13. Fraktionsregeln</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-grid">
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">👮</span><h3>12. Staatliche Fraktionen</h3></div>
              <ul class="regel-list">
                <li><strong>12.1 Fraktionszugeh&ouml;rigkeit:</strong> Ein Charakter darf grunds&auml;tzlich nur einer offiziellen Fraktion angeh&ouml;ren. Ein Fraktionswechsel muss nachvollziehbar ausgespielt werden. <span class="penalty penalty-ban">Ban</span></li>
                <li><strong>12.2 Interne Informationen:</strong> D&uuml;rfen nicht au&szlig;erhalb der Fraktion weitergegeben oder f&uuml;r OOC-Vorteile genutzt werden. <span class="penalty penalty-kick">Kick</span></li>
                <li><strong>12.3 Rangordnung:</strong> Anweisungen h&ouml;herrangiger Fraktionsmitglieder sind zu befolgen, sofern sie nicht gegen Serverregeln oder geltendes RP versto&szlig;en.</li>
                <li><strong>12.4 Korruption:</strong> Innerhalb staatlicher Fraktionen nur mit ausdr&uuml;cklicher Genehmigung der zust&auml;ndigen Fraktionsleitung erlaubt.</li>
              </ul>
            </div>
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">🕶️</span><h3>13. Illegale Fraktionen</h3></div>
              <ul class="regel-list">
                <li><strong>13.1 Fraktionszugeh&ouml;rigkeit:</strong> Ein Charakter darf grunds&auml;tzlich nur einer offiziellen Fraktion angeh&ouml;ren. Ein Fraktionswechsel muss nachvollziehbar ausgespielt werden. <span class="penalty penalty-ban">Ban</span></li>
                <li><strong>13.2 Interne Informationen:</strong> D&uuml;rfen nicht au&szlig;erhalb der Fraktion weitergegeben oder f&uuml;r OOC-Vorteile genutzt werden. <span class="penalty penalty-kick">Kick</span></li>
                <li><strong>13.3 Rangordnung:</strong> Anweisungen h&ouml;herrangiger Fraktionsmitglieder sind zu befolgen, sofern sie nicht gegen Serverregeln oder geltendes RP versto&szlig;en.</li>
                <li><strong>13.4 Blood Out:</strong> Die Gruppierung entscheidet selbst, ob ein Blood Out gegeben wird &ndash; verst&auml;ndlich dem Mitglied mitzuteilen. Der Charakter vergisst dann alle Infos/Aktivit&auml;ten der Familie (illegale Aktionen, interne Infos, Standorte, Abl&auml;ufe). Ausnahme: pers&ouml;nliche Beziehungen au&szlig;erhalb der Familie (z.&nbsp;B. eine Liebesbeziehung) bleiben in Erinnerung.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 14. STAATLICHE FRAKTIONEN & MEDIC RP -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🚑</span>
            <h2>14. Staatliche Fraktionen &amp; Medic RP</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-grid">
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">👮</span><h3>14.1 Im Dienst</h3></div>
              <ul class="regel-list">
                <li>Mitglieder staatlicher Fraktionen m&uuml;ssen sich jederzeit realistisch und entsprechend ihrer Rolle verhalten.</li>
                <li>Dienstfahrzeuge, Waffen und Ausr&uuml;stung d&uuml;rfen nur im Rahmen des Dienstes bzw. mit nachvollziehbarem RP-Grund verwendet werden.</li>
                <li>W&auml;hrend des Dienstes ist die vorgeschriebene Dienstkleidung zu tragen.</li>
              </ul>
            </div>
            <div class="regel-card">
              <div class="regel-card-head"><span class="ico">🩺</span><h3>14.2 Medic RP</h3></div>
              <ul class="regel-list">
                <li>Verletzungen m&uuml;ssen realistisch ausgespielt werden. Schwere Verletzungen d&uuml;rfen nicht ignoriert werden.</li>
                <li>Nach schweren Verletzungen sind z.&nbsp;B. Sprinten, K&auml;mpfen oder das Fahren eines Motorrads ohne nachvollziehbaren RP-Grund nicht erlaubt.</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Seite 3 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 4 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Abschnitt 15&ndash;19</span>
        </div>

        <!-- 15. FAHRZEUGE & STRASSENVERKEHR -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🚘</span>
            <h2>15. Fahrzeuge &amp; Stra&szlig;enverkehr</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🛣️</span><h3>Realistisches Fahrverhalten</h3></div>
            <ul class="regel-list">
              <li>Fahrzeuge sind grunds&auml;tzlich realistisch und der jeweiligen RP-Situation entsprechend zu fahren.</li>
              <li>Unrealistisches Rammen, Fahren als Waffe, unn&ouml;tige Spr&uuml;nge oder Stunts sowie dauerhaftes Fahren abseits geeigneter Wege ohne RP-Grund sind verboten.</li>
              <li>Stunts und riskante Fahrman&ouml;ver sind nur erlaubt, wenn sie einen nachvollziehbaren RP-Hintergrund haben.</li>
              <li><strong>15.1 Fahrzeugdiebstahl:</strong> Fahrzeuge d&uuml;rfen nur mit einem nachvollziehbaren RP-Grund gestohlen werden. Das Stehlen zum Trollen oder ohne RP-Hintergrund ist verboten.</li>
            </ul>
          </div>
        </div>

        <!-- 16. BUGS & EXPLOITS -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🐛</span>
            <h2>16. Bugs &amp; Exploits</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">⚠️</span><h3>Ausnutzen von Fehlern</h3></div>
            <ul class="regel-list">
              <li>Das absichtliche Ausnutzen von Bugs, Glitches oder Scriptfehlern zum eigenen Vorteil ist verboten. <span class="penalty penalty-ban">Ban</span></li>
              <li>Dazu z&auml;hlen insbesondere das Manipulieren oder Vervielf&auml;ltigen von Geld, Items oder Fahrzeugen.</li>
              <li>Gefundene Fehler sind dem Team zu melden und d&uuml;rfen nicht ausgenutzt werden.</li>
            </ul>
          </div>
        </div>

        <!-- 17. HACKING & CHEATING -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🛑</span>
            <h2>17. Hacking &amp; Cheating</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🖥️</span><h3>Unerlaubte Manipulationen</h3></div>
            <ul class="regel-list">
              <li>Jegliche Form von Cheats, Hacks oder unerlaubten Manipulationen, die einen unfairen Vorteil verschaffen, ist verboten &ndash; darunter Aimbots, Wallhacks, unerlaubte Modifikationen, manipulierte Dateien und Cheat-Men&uuml;s.</li>
              <li>Die Verwendung solcher Mittel f&uuml;hrt zum permanenten Ausschluss. <span class="penalty penalty-ban">Perm. Ausschluss</span></li>
            </ul>
          </div>
        </div>

        <!-- 18. REPORTS & BEWEISE -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">📋</span>
            <h2>18. Reports &amp; Beweise</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🎫</span><h3>Meldung von Regelverst&ouml;&szlig;en</h3></div>
            <ul class="regel-list">
              <li>Regelverst&ouml;&szlig;e sind m&ouml;glichst &uuml;ber das Ticketsystem zu melden.</li>
              <li>F&uuml;r die Bearbeitung sind, wenn m&ouml;glich, Aufnahmen, Screenshots, Zeitpunkt, beteiligte Spieler und eine kurze Beschreibung bereitzustellen.</li>
              <li>Das Team kann zur Aufkl&auml;rung auf vorhandene Logs, Aufzeichnungen und serverseitige Informationen zur&uuml;ckgreifen.</li>
              <li>Falsche oder absichtlich manipulierte Beweise k&ouml;nnen selbst sanktioniert werden. <span class="penalty penalty-ban">Ban</span></li>
            </ul>
          </div>
        </div>

        <!-- 19. STREAMING & CONTENT CREATOR -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🎥</span>
            <h2>19. Streaming &amp; Content Creator</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📡</span><h3>Streaming-Lizenz</h3></div>
            <ul class="regel-list">
              <li>Das Streamen von ViennaStateRP ist nur mit einer g&uuml;ltigen Streaming-Lizenz erlaubt, die &uuml;ber den offiziellen Discord beantragt werden kann.</li>
              <li>Die Weitergabe oder Nutzung von Informationen aus Streams f&uuml;r das eigene RP ist verboten und gilt als Metagaming. <span class="penalty penalty-ban">Ban</span></li>
              <li>Streamer sind selbst daf&uuml;r verantwortlich, geeignete Ma&szlig;nahmen gegen Streamsniping zu treffen.</li>
              <li>Bei Verst&ouml;&szlig;en gegen die Streaming-Regeln kann die Streaming-Lizenz entzogen werden.</li>
            </ul>
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Seite 4 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 5 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Abschnitt 20&ndash;23</span>
        </div>

        <!-- 20. RESPEKT, SICHERHEIT & COMMUNITY -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🤝</span>
            <h2>20. Respekt, Sicherheit &amp; Community</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🚫</span><h3>Nicht erlaubt</h3></div>
            <ul class="regel-list">
              <li>Beleidigungen und Bel&auml;stigungen <span class="penalty penalty-ban">Ban</span></li>
              <li>Rassismus, Diskriminierung und Extremismus <span class="penalty penalty-ban">Ban</span></li>
              <li>Mobbing und Hate Speech <span class="penalty penalty-ban">Ban</span></li>
              <li>OOC-Drohungen <span class="penalty penalty-ban">Ban</span></li>
              <li>Ver&ouml;ffentlichung oder Weitergabe privater Daten anderer Personen (Doxing) <span class="penalty penalty-ban">Perm. Ausschluss</span></li>
            </ul>
          </div>
          <div class="regel-callout" style="margin-top:16px">
            RP-Konflikte d&uuml;rfen niemals in pers&ouml;nliche OOC-Konflikte &uuml;bergehen. Das Ver&ouml;ffentlichen privater
            Informationen oder das Drohen mit realen Konsequenzen kann unabh&auml;ngig vom RP zu einem
            <strong>permanenten Ausschluss</strong> f&uuml;hren.
          </div>
        </div>

        <!-- 21. NSFW & UNANGEMESSENES ROLEPLAY -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🔞</span>
            <h2>21. NSFW &amp; unangemessenes Roleplay</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🚷</span><h3>Grenzen im RP</h3></div>
            <ul class="regel-list">
              <li>Sexuelles Roleplay ist nur mit dem ausdr&uuml;cklichen Einverst&auml;ndnis aller Beteiligten erlaubt.</li>
              <li>Sexuelle Gewalt, Bel&auml;stigung oder erzwungenes NSFW-RP sind verboten. <span class="penalty penalty-ban">Ban</span></li>
              <li>Sexualisiertes RP mit Minderj&auml;hrigen ist strikt untersagt. <span class="penalty penalty-ban">Ban</span></li>
              <li>Ein „Nein&ldquo; oder die Aufforderung zum Abbruch beendet die Situation sofort.</li>
            </ul>
          </div>
        </div>

        <!-- 22. STAFF & ADMINISTRATIVE ENTSCHEIDUNGEN -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🛠️</span>
            <h2>22. Staff &amp; administrative Entscheidungen</h2>
            <div class="regel-cat-line"></div>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📌</span><h3>Umgang mit dem Team</h3></div>
            <ul class="regel-list">
              <li>Anweisungen des Teams sind w&auml;hrend einer laufenden Support- oder Admin-Situation zu befolgen.</li>
              <li>Diskussionen &uuml;ber Sanktionen geh&ouml;ren nicht in den &ouml;ffentlichen OOC-Chat.</li>
              <li>Gegen Entscheidungen kann &uuml;ber das vorgesehene Ticketsystem Einspruch eingelegt werden.</li>
            </ul>
          </div>
        </div>

        <!-- 23. ABSCHLUSS -->
        <div class="regel-cat">
          <div class="regel-callout">
            <strong>23. Abschluss &ndash;</strong> Mit dem Betreten von ViennaStateRP akzeptiert jeder Spieler dieses Regelwerk.
            Die Serverleitung beh&auml;lt sich vor, Regeln jederzeit zu erg&auml;nzen, anzupassen oder zu &auml;ndern.
            &Auml;nderungen werden nach M&ouml;glichkeit &uuml;ber den offiziellen Changelog kommuniziert.
            Bei Unklarheiten entscheidet die Serverleitung im Sinne von: <strong>Realismus &middot; Fairness &middot; Roleplay &middot; Gemeinschaft</strong>
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Regelwerk</span>
          <span>Seite 5 von 5</span>
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
      <a href="allgemeine-hinweise.php">Allgemeine Hinweise</a>
      <a href="#" onclick="window.vsrpOpenCookieSettings &amp;&amp; window.vsrpOpenCookieSettings(); return false;">Cookie-Einstellungen</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> ViennaStateRP. Alle Rechte vorbehalten.</p>
  </div>
</footer>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
