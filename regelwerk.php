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

    <div class="regel-header">
      <div class="section-tag">Fair Play</div>
      <h1>Regelwerk</h1>
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

      <div style="overflow-x:auto;margin-bottom:24px">
        <table style="width:100%;border-collapse:collapse;font-size:.88rem">
          <thead>
            <tr style="border-bottom:2px solid rgba(232,184,109,.3)">
              <th style="padding:12px 16px;text-align:left;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">&Uuml;berfall</th>
              <th style="padding:12px 16px;text-align:center;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Polizei</th>
              <th style="padding:12px 16px;text-align:center;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">R&auml;uber</th>
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
            <tr style="border-bottom:1px solid var(--border);<?= $i % 2 === 0 ? 'background:rgba(255,255,255,.02)' : '' ?>">
              <td style="padding:12px 16px;color:#e2e8f0;font-weight:600"><?= $ev ?></td>
              <td style="padding:12px 16px;text-align:center"><span style="background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:#60a5fa;border-radius:6px;padding:2px 10px;font-family:'Orbitron',sans-serif;font-size:.85rem"><?= $cops ?></span></td>
              <td style="padding:12px 16px;text-align:center"><span style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:6px;padding:2px 10px;font-family:'Orbitron',sans-serif;font-size:.85rem"><?= $rob ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="color:#64748b;font-size:.78rem;margin-top:8px">Richtwerte &ndash; k&ouml;nnen von der Serverleitung an die aktuelle Situation angepasst werden.</p>
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
      <div class="regel-callout" style="margin-top:20px">
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
    <div class="regel-callout" style="margin-bottom:44px">
      <strong>23. Abschluss &ndash;</strong> Mit dem Betreten von ViennaStateRP akzeptiert jeder Spieler dieses Regelwerk.
      Die Serverleitung beh&auml;lt sich vor, Regeln jederzeit zu erg&auml;nzen, anzupassen oder zu &auml;ndern.
      &Auml;nderungen werden nach M&ouml;glichkeit &uuml;ber den offiziellen Changelog kommuniziert.
      Bei Unklarheiten entscheidet die Serverleitung im Sinne von: <strong>Realismus &middot; Fairness &middot; Roleplay &middot; Gemeinschaft</strong>
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
