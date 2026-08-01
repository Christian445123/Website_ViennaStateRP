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
  <title>Allgemeine Hinweise – ViennaStateRP</title>
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
    .legal-card h3 { font-size: 0.9rem; color: var(--text); margin: 20px 0 8px; }
    .legal-card p, .legal-card ul, .legal-card ol, .legal-card address { color: var(--text-2); line-height: 1.8; font-size: 0.95rem; font-style: normal; }
    .legal-card ul, .legal-card ol { padding-left: 20px; margin-bottom: 12px; }
    .legal-card ul li, .legal-card ol li { margin-bottom: 4px; }
    .legal-card a { color: var(--gold); }
    .legal-card a:hover { text-decoration: underline; }
    .legal-card .highlight-box {
      background: var(--bg-card-2);
      border: 1px solid var(--border);
      border-left: 3px solid var(--gold);
      border-radius: 8px;
      padding: 16px 20px;
      margin: 20px 0;
      font-size: 0.9rem;
      color: var(--text-2);
    }
    .rule-badge {
      display: inline-block;
      background: rgba(232,184,109,0.1);
      border: 1px solid rgba(232,184,109,0.25);
      color: var(--gold);
      border-radius: 4px;
      padding: 2px 8px;
      font-size: 0.75rem;
      font-family: 'Rajdhani', sans-serif;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 6px;
    }
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
      <li><a href="changelog.php">Changelog</a></li>
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
      <a href="impressum.php">Impressum</a>
      <a href="datenschutz.php">Datenschutzerklärung</a>
      <a href="allgemeine-hinweise.php" class="active">Allgemeine Hinweise</a>
    </div>

    <div class="legal-card">
      <div class="section-tag">Nutzungsbedingungen</div>
      <h1>Allgemeine Hinweise &amp; Nutzungsbedingungen</h1>
      <p class="legal-sub">
        Diese Hinweise regeln die Nutzung von ViennaStateRP – Website, Discord-Server und FiveM-Gameserver.
        Mit der Nutzung unserer Dienste erkennst du diese Bedingungen an.
      </p>

      <div class="highlight-box">
        ViennaStateRP ist ein privates, nicht-kommerzielles Hobby-Projekt und steht in keinerlei Verbindung
        zu Rockstar Games, Take-Two Interactive Software oder FiveM / Cfx.re.
        Die Teilnahme ist kostenlos und freiwillig.
      </div>

      <!-- 1. Geltungsbereich -->
      <h2>1. Geltungsbereich</h2>
      <p>
        Diese Allgemeinen Hinweise gelten für alle Nutzer von:
      </p>
      <ul>
        <li>der Website <strong><?= htmlspecialchars(SITE_URL) ?></strong></li>
        <li>dem Discord-Server <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(DISCORD_INVITE) ?></a></li>
        <li>dem FiveM-Gameserver <strong><?= htmlspecialchars(FIVEM_IP) ?>:<?= htmlspecialchars(FIVEM_PORT) ?></strong></li>
      </ul>

      <!-- 2. Mindestalter -->
      <h2>2. Mindestalter &amp; Zustimmung</h2>
      <p>
        Die Nutzung unserer Dienste setzt voraus, dass du mindestens <strong>16 Jahre alt</strong> bist
        (Art. 8 DSGVO – Mindestalter für digitale Dienste in Österreich). Personen unter 16 Jahren
        benötigen die nachweisliche Zustimmung eines Erziehungsberechtigten. Mit der Registrierung
        bzw. dem Login bestätigst du, das erforderliche Mindestalter zu erfüllen oder eine entsprechende
        Zustimmung vorzuweisen.
      </p>

      <!-- 3. Nutzerkonto -->
      <h2>3. Nutzerkonto &amp; Whitelist</h2>
      <p>
        Für den Zugang zum FiveM-Gameserver ist eine Whitelist-Freischaltung erforderlich. Dabei gilt:
      </p>
      <ul>
        <li>Pro Person ist nur <strong>ein Account</strong> erlaubt (kein Multi-Accounting).</li>
        <li>Accountdaten dürfen nicht an Dritte weitergegeben werden.</li>
        <li>Du bist für alle Aktivitäten verantwortlich, die unter deinem Account stattfinden.</li>
        <li>Das Team behält sich vor, Accounts bei Regelverstößen zu sperren oder zu löschen.</li>
        <li>Ein Anspruch auf Whitelist-Freischaltung besteht nicht.</li>
      </ul>

      <!-- 4. Verhaltensregeln -->
      <h2>4. Verhaltensregeln</h2>
      <p>
        Die Nutzung aller Plattformen von ViennaStateRP setzt respektvolles Verhalten voraus.
        Folgendes ist <strong>ausdrücklich untersagt</strong>:
      </p>
      <ul>
        <li>Beleidigungen, Diskriminierung, Rassismus, Sexismus oder Hassrede jeglicher Art</li>
        <li>Belästigung, Stalking oder Einschüchterung anderer Nutzer</li>
        <li>Verbreitung illegaler, jugendgefährdender oder urheberrechtlich geschützter Inhalte</li>
        <li>Cheating, Exploiting, Modding oder jegliche Manipulation des Spielgeschehens</li>
        <li>Spam, Werbung für externe Projekte oder Abwerbung von Community-Mitgliedern</li>
        <li>Weitergabe oder Veröffentlichung privater Daten anderer Nutzer (Doxing)</li>
        <li>Impersonation von Team-Mitgliedern oder anderen Personen</li>
      </ul>

      <!-- 5. Server-Regeln (RP) -->
      <h2>5. Roleplay-Grundsätze (FiveM-Server)</h2>
      <p>
        Auf dem Gameserver gelten zusätzlich zu den allgemeinen Verhaltensregeln folgende
        Roleplay-Grundsätze:
      </p>
      <ol>
        <li><strong>Roleplay geht vor (RGV):</strong> Das Spielgeschehen hat Priorität gegenüber
        OOC (Out-of-Character) Interaktionen. Nutze den OOC-Chat nur für technische Probleme.</li>
        <li><strong>Kein Random Deathmatch (RDM):</strong> Angriffe auf andere Spieler ohne
        Roleplay-Begründung sind verboten.</li>
        <li><strong>Kein Vehicle Deathmatch (VDM):</strong> Absichtliches Überfahren ohne
        Roleplay-Kontext ist verboten.</li>
        <li><strong>New Life Rule (NLR):</strong> Nach dem Tod deines Charakters vergisst du alle
        Ereignisse, die zum Tod geführt haben.</li>
        <li><strong>Powergaming:</strong> Das Erzwingen von Aktionen bei anderen Spielern ohne
        reale Möglichkeit ist untersagt.</li>
        <li><strong>Metagaming:</strong> Die Nutzung von Informationen aus externen Quellen (z. B.
        Streams, Discord) im Spiel ist verboten.</li>
        <li><strong>Charakter-Konsistenz:</strong> Spiele deinen Charakter realistisch und konsistent.
        Passende Namen und Hintergrundgeschichten werden erwartet.</li>
      </ol>
      <p>
        Detaillierte Serverregeln werden auf unserem
        <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord-Server</a>
        veröffentlicht und gelten als verbindlich.
      </p>

      <!-- 6. Haftungsausschluss -->
      <h2>6. Haftungsausschluss &amp; Verfügbarkeit</h2>
      <p>
        ViennaStateRP wird als Hobby-Projekt ohne kommerzielle Absicht betrieben. Es besteht kein
        Anspruch auf:
      </p>
      <ul>
        <li>Ununterbrochene Verfügbarkeit von Website oder Gameserver</li>
        <li>Erhalt von Spielständen, Charakterdaten oder erworbenen In-Game-Gegenständen bei Datenverlust</li>
        <li>Entschädigung bei Serverausfällen oder erzwungenen Resets</li>
      </ul>
      <p>
        Wir sind bemüht, Ausfallzeiten so kurz wie möglich zu halten, übernehmen jedoch keine
        Haftung für technisch bedingte Unterbrechungen.
      </p>

      <!-- 7. Geistiges Eigentum -->
      <h2>7. Geistiges Eigentum</h2>
      <p>
        Alle selbst erstellten Inhalte dieser Website (Texte, Designs, Logos) sind Eigentum von
        ViennaStateRP / GamingCommunity24/7 und dürfen ohne Genehmigung nicht übernommen werden.
        Folgende Marken und Produkte sind Eigentum ihrer jeweiligen Inhaber und werden hier nur
        referenziert:
      </p>
      <ul>
        <li>Grand Theft Auto V (GTA V) – Rockstar Games / Take-Two Interactive</li>
        <li>FiveM – Cfx.re (CitizenFX Collective)</li>
        <li>Discord – Discord Inc.</li>
        <li>ESX Framework – ESX-Framework Contributors</li>
      </ul>

      <!-- 8. Sanktionen -->
      <h2>8. Sanktionen bei Verstößen</h2>
      <p>
        Bei Verstößen gegen diese Nutzungsbedingungen oder die Serverregeln behält sich das Team vor:
      </p>
      <ul>
        <li>Verwarnungen auszusprechen</li>
        <li>temporäre oder permanente Bans auf dem Gameserver zu verhängen</li>
        <li>Discord-Ausschlüsse vorzunehmen</li>
        <li>Accounts auf der Website zu sperren</li>
      </ul>
      <p>
        Entscheidungen des Teams sind final. Ein Widerspruch kann über ein Ticket auf dem
        Discord-Server eingereicht werden.
      </p>

      <!-- 9. Änderungen -->
      <h2>9. Änderungen dieser Bedingungen</h2>
      <p>
        Das Team behält sich vor, diese Allgemeinen Hinweise jederzeit zu aktualisieren.
        Änderungen werden auf dem Discord-Server bekannt gegeben. Die fortgesetzte Nutzung
        der Dienste nach Änderungen gilt als Zustimmung zu den aktualisierten Bedingungen.
      </p>

      <!-- 10. Kontakt -->
      <h2>10. Kontakt &amp; Beschwerden</h2>
      <p>
        Bei Fragen, Beschwerden oder zur Ausübung deiner Datenschutzrechte:
      </p>
      <ul>
        <li>E-Mail: <a href="mailto:[DEINE-EMAIL@DOMAIN.AT]">[DEINE-EMAIL@DOMAIN.AT]</a></li>
        <li>Discord: <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Support-Ticket auf unserem Server</a></li>
      </ul>

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
