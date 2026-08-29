<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_permission('supporter')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff'));
    exit;
}

$user    = current_user();
$isAdmin = has_permission('admin');

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
  <title>Teamregelwerk &ndash; Team &ndash; ViennaStateRP</title>
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
      border: 2px solid #6b4a9c; display: flex; align-items: center; justify-content: center;
      font-family: Arial, Helvetica, sans-serif; font-size: 1rem; font-weight: 800;
      color: #5c3f87; letter-spacing: .5px;
      box-shadow: 0 0 0 4px #faf8f3, 0 0 0 5px rgba(107,74,156,.3);
    }
    .pdf-cover .pdf-tag {
      display: inline-block; font-family: Arial, Helvetica, sans-serif; font-size: .66rem;
      letter-spacing: 2.5px; text-transform: uppercase; color: #5c3f87; border: 1px solid #b8a6d9;
      border-radius: 20px; padding: 4px 14px; margin-bottom: 20px;
    }
    .pdf-cover h1 {
      font-family: Arial, Helvetica, sans-serif; font-size: clamp(1.9rem, 5vw, 2.6rem);
      letter-spacing: 1px; margin: 0 0 8px; color: #201f1a; text-transform: uppercase; font-weight: 800;
    }
    .pdf-cover .pdf-subtitle {
      font-family: Arial, Helvetica, sans-serif; font-size: .82rem; letter-spacing: 3px;
      text-transform: uppercase; color: #7a6a9c; margin-bottom: 24px;
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
      letter-spacing: .4px; margin: 0; color: #4a3072; text-transform: uppercase;
    }
    .ht-badge {
      display: inline-block; font-family: Arial, Helvetica, sans-serif; font-size: .58rem;
      font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: #5c3f87;
      border: 1.5px solid #6b4a9c; border-radius: 3px; padding: 1px 7px; margin-left: 6px;
      vertical-align: middle; transform: rotate(-2deg);
    }

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
    .regel-list ul.sub-list { list-style: disc; margin: 6px 0 0 18px; padding: 0; }
    .regel-list ul.sub-list li { padding: 3px 0; border-bottom: none; font-size: .85rem; }

    .regel-callout {
      background: #f1ede2; border: 1px solid #e0dbcc; border-left: 3px solid #6b4a9c;
      border-radius: 0 3px 3px 0; padding: 12px 18px; margin-bottom: 16px;
      font-size: .86rem; color: #332f26; line-height: 1.6; text-align: justify; hyphens: auto;
    }
    .regel-callout strong { color: #201f1a; }

    .regel-page-intro { color: #4a453c; font-size: .88rem; margin: 0 0 14px; text-align: justify; hyphens: auto; }

    /* ── CTA (stays in normal site theme, below the "document") ──── */
    .regel-cta { text-align: center; background: linear-gradient(135deg, rgba(124,58,237,.12), var(--gold-dim)); border: 1px solid rgba(124,58,237,0.25); border-radius: 14px; padding: 36px 24px; max-width: 840px; margin: 34px auto 0; }
    .regel-cta .icon { font-size: 1.6rem; margin-bottom: 8px; }
    .regel-cta h3 { margin-bottom: 8px; }
    .regel-cta p { color: var(--text-2); font-size: 0.9rem; margin: 0 auto 20px; max-width: 480px; }
    .regel-cta .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  </style>
</head>
<body>

<nav class="navbar scrolled" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="regelwerk.php">Regeln</a></li>
      <li><a href="players.php">Server Status</a></li>
      <?php if ($isAdmin): ?>
      <li><a href="admin.php">⚙️ Admin</a></li>
      <?php endif; ?>
      <li><a href="strafen.php">🔒 Strafen</a></li>
      <li><a href="teamregelwerk.php" class="active">📘 Teamregelwerk</a></li>
    </ul>
    <div class="nav-actions">
      <a href="dashboard.php" class="btn btn-ghost">
        <img src="<?= htmlspecialchars(avatarUrl($user)) ?>" alt="Avatar" class="nav-avatar" />
        <?= htmlspecialchars($user['global_name']) ?>
      </a>
      <a href="logout.php" class="btn btn-outline">Abmelden</a>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<?php include __DIR__ . '/includes/maintenance_banner.php'; ?>

<main class="regel-page">
  <div class="container">

    <div class="pdf-toolbar">
      <span class="pdf-toolbar-dots"><span></span><span></span><span></span></span>
      <span>📘</span>
      <span class="pdf-toolbar-name">Teamregelwerk_ViennaStateRP.pdf</span>
      <span class="pdf-toolbar-meta">Stand: <?= date('d.m.Y') ?> &middot; nur f&uuml;r Team &middot; 5 Seiten</span>
    </div>

    <div class="pdf-container">

      <!-- ══════════════════ SEITE 1 ══════════════════ -->
      <div class="pdf-page">

        <div class="pdf-cover">
          <div class="pdf-crest">VS</div>
          <div class="pdf-tag">Nur f&uuml;r Teammitglieder</div>
          <h1>Teamregelwerk</h1>
          <div class="pdf-subtitle">Rangsystem &amp; Aufgabenerkl&auml;rung</div>
          <hr>
          <p>
            Dieses Teamregelwerk erkl&auml;rt die Rangstruktur von ViennaStateRP sowie die Aufgaben, Rechte und
            Verantwortlichkeiten jedes Teamranges &ndash; vom Server Owner bis zum Test-Supporter.
          </p>
        </div>

        <!-- WAS IST DAS HIGH TEAM -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">👑</span>
            <h2>Was ist das High Team?</h2>
          </div>

          <p class="regel-page-intro">
            Das High Team ist die h&ouml;chste Entscheidungsebene auf dem Server. Es besteht aus den Server Ownern und
            der Projektleitung, das f&uuml;r das gesamte Team verantwortlich ist.
          </p>
          <p class="regel-page-intro">
            Diese Personen haben besondere Rechte, die &uuml;ber das normale Team Regelwerk hinausgehen, und treffen
            wichtige Entscheidungen f&uuml;r den Server und das Team.
          </p>

          <div class="regel-card" style="margin-bottom:16px">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgaben des High Teams</h3></div>
            <ul class="regel-list">
              <li>Gesamtverantwortung f&uuml;r den Serverbetrieb</li>
              <li>Entscheidungen in Sonderf&auml;llen, bei denen das Regelwerk nicht ausreicht</li>
              <li>Regel-Ausnahmen oder &Auml;nderungen d&uuml;rfen nur vom High Team endg&uuml;ltig beschlossen werden</li>
              <li>Aufstiege in h&ouml;here Team-R&auml;nge (z.&nbsp;B. Supporter, Moderator, Admin oder dar&uuml;ber hinaus) werden nur vom High Team best&auml;tigt</li>
              <li>L&ouml;sen von Konflikten oder Problemen innerhalb des Teams</li>
              <li>Strukturelle Entscheidungen f&uuml;r die Zukunft des Servers</li>
            </ul>
          </div>

          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">💡</span><h3>Wichtig f&uuml;r dich als Teammitglied</h3></div>
            <ul class="regel-list">
              <li>Das High-Team darf immer eingreifen &ndash; z.&nbsp;B. bei Regel-Ausnahmen, Problemen oder Entscheidungen, die &uuml;ber den normalen Zust&auml;ndigkeitsbereich hinausgehen.</li>
              <li>Normale Teamaufgaben bleiben weiterhin beim jeweiligen Bereich (Support, Admin, Moderator etc.).</li>
              <li>Das High-Team ist immer ansprechbar, aber auch mit viel Verantwortung und Planung im Hintergrund besch&auml;ftigt.</li>
            </ul>
          </div>

          <div class="regel-callout" style="margin-top:16px">
            <strong>Kurz gesagt:</strong> Das High Team sorgt daf&uuml;r, dass der Server langfristig funktioniert, trifft
            wichtige Entscheidungen, es ist die oberste Instanz im Team und darf vom Regelwerk abweichen.
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>Seite 1 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 2 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>1. Server Owner &middot; 2. Projektleitung</span>
        </div>

        <!-- 1. SERVER OWNER -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🏆</span>
            <h2>1. Server Owner (Server Besitzer)<span class="ht-badge">High Team</span></h2>
          </div>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">👑</span><h3>Rechte &amp; Verantwortung</h3></div>
            <ul class="regel-list">
              <li><strong>Vollzugriff:</strong> Hat vollst&auml;ndige Kontrolle &uuml;ber alle Einstellungen, Berechtigungen und Inhalte.</li>
              <li><strong>Inhaber:</strong> Sind die Personen, die den Server erstellt haben.</li>
              <li><strong>Nicht entfernbar:</strong> Kann von niemandem gekickt oder degradiert werden.</li>
              <li><strong>Verantwortlich:</strong> Tr&auml;gt die Hauptverantwortung f&uuml;r den Server und dessen Regeln.</li>
            </ul>
          </div>
        </div>

        <!-- 2. PROJEKTLEITUNG -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🧭</span>
            <h2>2. Projektleitung<span class="ht-badge">High Team</span></h2>
          </div>
          <p class="regel-page-intro">
            Die Projektleitung ist die Schnittstelle zwischen den Server-Ownern und dem restlichen Team &ndash; und damit
            die zweith&ouml;chste Instanz im Server-Team. Sie sorgt daf&uuml;r, dass alles reibungslos funktioniert und
            jedes Teammitglied seine Aufgaben kennt und erf&uuml;llt.
          </p>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgaben der Projektleitung</h3></div>
            <ul class="regel-list">
              <li><strong>Vollzugriff:</strong> Kontrolle &uuml;ber s&auml;mtliche Server-Einstellungen, Berechtigungen und Inhalte.</li>
              <li>Enge Zusammenarbeit mit der Teamleitung.</li>
              <li><strong>Nicht entfernbar:</strong> Projektleitungen k&ouml;nnen in der Regel nicht gekickt oder degradiert werden.</li>
              <li>Kommunikation mit den Server-Ownern und dem gesamten Team.</li>
              <li>Planung und Leitung von Projekten.</li>
              <li>&Uuml;berwachung der Teamaktivit&auml;t und Sicherstellung der Qualit&auml;t der Arbeit.</li>
            </ul>
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>Seite 2 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 3 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>3. Teamleitung / Stv. Teamleitung</span>
        </div>

        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🧩</span>
            <h2>3. Teamleitung / Stv. Teamleitung</h2>
          </div>
          <p class="regel-page-intro">
            Die Teamleitung stellt eine zentrale organisatorische Schnittstelle innerhalb des Teams dar. Sie unterst&uuml;tzt
            die Projektleitung aktiv bei der Koordination, Qualit&auml;tskontrolle und Entwicklung des gesamten Teams,
            verf&uuml;gt jedoch nicht &uuml;ber High-Team-Status.
          </p>

          <div class="regel-card" style="margin-bottom:16px">
            <div class="regel-card-head"><span class="ico">⚖️</span><h3>Rechte &amp; Einschr&auml;nkungen</h3></div>
            <ul class="regel-list">
              <li><strong>Eingeschr&auml;nkte Rechte:</strong> Kein vollst&auml;ndiger Zugriff auf Server-Einstellungen, tiefgreifende Berechtigungen oder sensible Inhalte &ndash; diese bleiben dem High Team (Projektleitung/Owner) vorbehalten.</li>
              <li><strong>Keine High-Team-Zugeh&ouml;rigkeit:</strong> Die Teamleitung hat zwar erweiterte Befugnisse innerhalb des Teams, z&auml;hlt jedoch nicht zum High Team. Bei Problemen mit einer Teamleitung wendet euch bitte direkt an das High Team oder die Projektleitung.</li>
            </ul>
          </div>

          <div class="regel-card" style="margin-bottom:16px">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgaben und Verantwortungsbereich</h3></div>
            <ul class="regel-list">
              <li><strong>Koordination des Teams:</strong> Organisation und Steuerung der verschiedenen Teambereiche (z.&nbsp;B. Test-Supporter, Supporter, Moderatoren, Admins, Head Admins) &rarr; in enger Zusammenarbeit mit der Projektleitung.</li>
              <li><strong>Zusammenarbeit mit Projektleitung:</strong> Unterst&uuml;tzung bei der Entwicklung und Integration ins Team.</li>
              <li><strong>Projekt- und Meeting-Leitung:</strong> Planung und Durchf&uuml;hrung von internen Projekten oder Team-Meetings &rarr; entweder eigenst&auml;ndig oder im Auftrag der Projektleitung bzw. des High Teams.</li>
              <li><strong>Qualit&auml;tssicherung &amp; Aktivit&auml;ts&uuml;berwachung:</strong> Kontrolle der Teamaktivit&auml;t und Sicherstellung der Einhaltung von Standards &rarr; immer in Abstimmung mit der Projektleitung.</li>
              <li><strong>Bewerbungen &amp; Teamstruktur:</strong> Teambewerbungen abhalten sowie Auf- oder Abstufung der Teammitglieder.</li>
              <li><strong>Kommunikation &amp; Berichterstattung:</strong> Direkte Kommunikation mit der Projektleitung, regelm&auml;&szlig;iger Austausch mit dem gesamten Team. Die Teamleitung agiert als verl&auml;ngerter Arm der Projektleitung.</li>
            </ul>
          </div>

          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">🔻</span><h3>Entfernbarkeit &amp; Verantwortung</h3></div>
            <ul class="regel-list">
              <li>
                <strong>Entfernbar / Degradierbar:</strong> Im Gegensatz zur Projektleitung oder den Ownern kann die Teamleitung
                jederzeit abgesetzt oder degradiert werden, wenn:
                <ul class="sub-list">
                  <li>Aufgaben vernachl&auml;ssigt werden</li>
                  <li>Macht missbraucht wird</li>
                  <li>Der Rang durch unangemessenes Verhalten gesch&auml;digt wird</li>
                </ul>
              </li>
            </ul>
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>Seite 3 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 4 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>4. Fraktionsleitung &middot; 5. Admin &middot; 6. Moderator</span>
        </div>

        <!-- 4. FRAKTIONSLEITUNG -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🏙️</span>
            <h2>4. Fraktionsleitung</h2>
          </div>
          <p class="regel-page-intro">
            Die Fraktionsleitung ist der Ansprechpartner f&uuml;r alles, was Fraktionen betrifft, sowie die Verteilung
            der Fraktionsf&uuml;hrer IC.
          </p>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgaben</h3></div>
            <ul class="regel-list">
              <li>Die Fraktionsleitung hat beschr&auml;nkte IC-Rechte.</li>
              <li>Die Fraktionsleitung ist f&uuml;r die Fraktionen zust&auml;ndig.</li>
              <li>&Uuml;berwachung der Fraktionen.</li>
              <li>Ansprechpartner der Fraktionen.</li>
              <li>Bearbeitung von Fraktionstickets.</li>
            </ul>
          </div>
        </div>

        <!-- 5. ADMIN -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🛡️</span>
            <h2>5. Admin</h2>
          </div>
          <p class="regel-page-intro">
            Admins haben erweiterte Ingame-Rechte und sind verantwortlich f&uuml;r die Moderation des Servers, die
            Durchsetzung der Regeln und die Verwaltung von Spielern und Ressourcen im Spiel.
          </p>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgaben</h3></div>
            <ul class="regel-list">
              <li>Durchsetzung der Serverregeln und Sanktionierung von Verst&ouml;&szlig;en.</li>
              <li>&Uuml;berwachung der Spielaktivit&auml;ten und Kommunikation mit der Community.</li>
              <li>Hilfe bei technischen Problemen oder Ingame-Fragen der Spieler.</li>
              <li>Support f&uuml;r Spieler bei Problemen, Missverst&auml;ndnissen oder Anfragen.</li>
            </ul>
          </div>
          <div class="regel-callout" style="margin-top:12px">
            <strong>Kurz gesagt:</strong> Admins sorgen f&uuml;r die Ordnung auf dem Server, setzen Regeln durch und sind
            als Ansprechpartner f&uuml;r alle Spieler da &ndash; mit erweiterten Rechten f&uuml;r die Verwaltung des Spiels.
          </div>
        </div>

        <!-- 6. MODERATOR -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🎙️</span>
            <h2>6. Moderator</h2>
          </div>
          <p class="regel-page-intro">
            Moderatoren sind daf&uuml;r verantwortlich, f&uuml;r eine faire und angenehme Atmosph&auml;re auf dem Server
            zu sorgen, indem sie die Regeln &uuml;berwachen und durchsetzen. Sie haben eingeschr&auml;nkte Ingame-Rechte,
            fokussieren sich jedoch haupts&auml;chlich auf die Kommunikation und das Verhalten der Spieler.
          </p>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgaben</h3></div>
            <ul class="regel-list">
              <li>Durchsetzung der Serverregeln, insbesondere im Umgang zwischen den Spielern.</li>
              <li>Verwarnung von Spielern, die gegen Regeln versto&szlig;en.</li>
              <li>Unterst&uuml;tzung der Community bei Fragen oder Problemen.</li>
            </ul>
          </div>
          <div class="regel-callout" style="margin-top:12px">
            <strong>Kurz gesagt:</strong> Moderatoren sorgen f&uuml;r eine respektvolle und faire Spielerfahrung, indem sie
            das Verhalten &uuml;berwachen und bei Verst&ouml;&szlig;en eingreifen, ohne weitreichende technische Rechte zu haben.
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>Seite 4 von 5</span>
        </div>
      </div>

      <!-- ══════════════════ SEITE 5 ══════════════════ -->
      <div class="pdf-page">
        <div class="pdf-page-header">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>7. Supporter &middot; 8. Test-Supporter</span>
        </div>

        <!-- 7. SUPPORTER -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🎧</span>
            <h2>7. Supporter</h2>
          </div>
          <p class="regel-page-intro">
            Supporter sind die ersten Ansprechpartner f&uuml;r Spieler, die Hilfe ben&ouml;tigen. Sie bilden gemeinsam mit
            Test-Supportern und Moderatoren das Fundament des Support-Teams und tragen aktiv zur Aufrechterhaltung eines
            positiven Spielergebnisses bei. Dabei agieren sie auf Discord sowie im Spiel selbst, reagieren auf
            Regelverst&ouml;&szlig;e und leiten diese an h&ouml;hergestellte Teammitglieder weiter.
          </p>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgabenbereich</h3></div>
            <ul class="regel-list">
              <li><strong>Support-Tickets:</strong> Hauptverantwortlich f&uuml;r die Bearbeitung von Support-Tickets auf Discord &rarr; in Zusammenarbeit mit Moderatoren und Test-Supportern.</li>
              <li><strong>Regelverst&ouml;&szlig;e verwarnen:</strong> Durchf&uuml;hrung freundlicher, aber klarer Verwarnungen bei Regelverst&ouml;&szlig;en im Spiel &rarr; Pflicht: Dokumentation und Weiterleitung an einen Admin oder h&ouml;hergestellten Rang.</li>
              <li><strong>Kicks bei schwerwiegendem Verhalten:</strong> Befugnis, Spieler bei grobem Fehlverhalten aus dem Spiel zu kicken &rarr; Pflicht: Unverz&uuml;gliche Meldung an einen Admin oder h&ouml;her.</li>
              <li><strong>Ban-Antr&auml;ge stellen:</strong> Erstellung und Weiterleitung von Ban-Antr&auml;gen an Admins, wenn ein Regelverstoß eine dauerhafte Sanktion rechtfertigt.</li>
              <li><strong>Community-Support:</strong> Aktive Hilfe bei Fragen und Problemen rund um Spielregeln, technische Schwierigkeiten oder sonstige Anliegen der Community.</li>
              <li><strong>Dokumentation &amp; Kommunikation:</strong> Sorgf&auml;ltige Dokumentation aller Supportf&auml;lle, inklusive eskalierter Tickets und Entscheidungen &rarr; enge Abstimmung mit dem Admin-Team zur Sicherstellung eines reibungslosen Ablaufs.</li>
            </ul>
          </div>
        </div>

        <!-- 8. TEST-SUPPORTER -->
        <div class="regel-cat">
          <div class="regel-cat-label">
            <span class="regel-cat-icon">🌱</span>
            <h2>8. Test-Supporter</h2>
          </div>
          <p class="regel-page-intro">
            Ein Test-Supporter ist ein angehender Supporter in einer zweiw&ouml;chigen Probezeit. Ziel dieser Phase ist es,
            die Eignung, Motivation und Teamf&auml;higkeit des neuen Mitglieds zu pr&uuml;fen. Dabei wird der Test-Supporter
            intensiv von einem erfahrenen Mentor begleitet, der ihn in die Abl&auml;ufe des Supports einf&uuml;hrt und
            seine Entwicklung unterst&uuml;tzt.
          </p>
          <div class="regel-card">
            <div class="regel-card-head"><span class="ico">📋</span><h3>Aufgabenbereich</h3></div>
            <ul class="regel-list">
              <li>
                <strong>Bearbeitung von Support-Tickets:</strong> Hauptaufgabe ist die Unterst&uuml;tzung bei der Bearbeitung
                von Support-Tickets auf Discord, in enger Zusammenarbeit mit:
                <ul class="sub-list">
                  <li>Supportern</li>
                  <li>Mentoren</li>
                  <li>Admins</li>
                </ul>
              </li>
            </ul>
          </div>
        </div>

        <div class="pdf-page-footer">
          <span>ViennaStateRP &middot; Teamregelwerk</span>
          <span>Seite 5 von 5</span>
        </div>
      </div>

    </div>

    <!-- CTA -->
    <div class="regel-cta">
      <div class="icon">📘</div>
      <h3>Fragen zum Teamregelwerk?</h3>
      <p>Wende dich bei Unklarheiten zu deinem Rang oder deinen Aufgaben an deine Teamleitung oder das High Team.</p>
      <div class="actions">
        <a href="strafen.php" class="btn btn-outline">🔒 Zum Strafenkatalog</a>
        <a href="dashboard.php" class="btn btn-outline">&larr; Zum Dashboard</a>
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
      <a href="regelwerk.php">Regelwerk</a>
      <a href="players.php">Server Status</a>
    </div>
    <div class="footer-links">
      <h5>Team</h5>
      <a href="strafen.php">Strafenkatalog</a>
      <a href="dashboard.php">Dashboard</a>
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
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
