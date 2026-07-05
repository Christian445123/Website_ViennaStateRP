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
  <title>Datenschutzerklärung – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
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
    .legal-card p, .legal-card ul, .legal-card address { color: var(--text-2); line-height: 1.8; font-size: 0.95rem; font-style: normal; }
    .legal-card ul { padding-left: 20px; margin-bottom: 12px; }
    .legal-card ul li { margin-bottom: 4px; }
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
      <li><a href="index.php#rules">Regeln</a></li>
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

<!-- CONTENT -->
<main class="legal-page">
  <div class="container">

    <div class="legal-nav">
      <a href="impressum.php">Impressum</a>
      <a href="datenschutz.php" class="active">Datenschutzerklärung</a>
      <a href="allgemeine-hinweise.php">Allgemeine Hinweise</a>
    </div>

    <div class="legal-card">
      <div class="section-tag">DSGVO</div>
      <h1>Datenschutzerklärung</h1>
      <p class="legal-sub">
        Gemäß Art. 13 &amp; 14 DSGVO (Datenschutz-Grundverordnung, EU 2016/679) informieren wir dich
        über die Verarbeitung personenbezogener Daten auf dieser Website.
      </p>

      <div class="highlight-box">
        <strong>Kurzfassung:</strong> Wir betreiben kein Tracking, keine Werbung und verkaufen keine Daten.
        Personenbezogene Daten werden ausschließlich erhoben, um den Betrieb des Roleplay-Servers und
        dieser Website zu ermöglichen (Login, Whitelist, Server-Verwaltung).
      </div>

      <!-- 1. Verantwortlicher -->
      <h2>1. Verantwortlicher</h2>
      <address>
        <strong>[DEIN NAME / ORGANISATIONSNAME]</strong><br />
        [Straße und Hausnummer]<br />
        [PLZ] [Ort], Österreich<br />
        E-Mail: <a href="mailto:[DEINE-EMAIL@DOMAIN.AT]">[DEINE-EMAIL@DOMAIN.AT]</a>
      </address>

      <!-- 2. Datenerhebung & -verarbeitung -->
      <h2>2. Welche Daten wir verarbeiten</h2>

      <h3>a) Beim Besuch der Website (Server-Logs)</h3>
      <p>
        Beim Aufruf dieser Website übermittelt dein Browser automatisch folgende Daten an unseren Webserver:
      </p>
      <ul>
        <li>IP-Adresse (anonymisiert nach Sitzungsende)</li>
        <li>Datum und Uhrzeit des Zugriffs</li>
        <li>Aufgerufene URL</li>
        <li>HTTP-Statuscode, übertragene Datenmenge</li>
        <li>Referrer-URL (vorherige Seite) und User-Agent (Browser/Betriebssystem)</li>
      </ul>
      <p>
        Diese Daten sind für den sicheren und stabilen Betrieb technisch notwendig (Art. 6 Abs. 1 lit. f DSGVO –
        berechtigtes Interesse). Sie werden nicht mit anderen Datenquellen verknüpft und nach spätestens
        30 Tagen gelöscht.
      </p>

      <h3>b) Discord OAuth2-Login</h3>
      <p>
        Wenn du dich über den Button „Mit Discord anmelden" einloggst, wirst du zu Discord weitergeleitet.
        Nach erfolgreicher Authentifizierung übermittelt Discord folgende Daten an uns:
      </p>
      <ul>
        <li>Discord-Benutzer-ID</li>
        <li>Benutzername &amp; Anzeigename (global name)</li>
        <li>Avatar-Hash (für die Profilbild-URL)</li>
        <li>E-Mail-Adresse (wird in unserer Datenbank gespeichert)</li>
        <li>Mitgliedschaft in unserem Discord-Server (zur Rollen-Prüfung)</li>
      </ul>
      <p>
        Diese Daten werden in unserer MySQL-Datenbank gespeichert und ausschließlich für Folgendes verwendet:
        Benutzer-Authentifizierung, Whitelist-Verwaltung, Rollenvergabe und Administration.
        Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung / vorvertragliche Maßnahmen).
      </p>
      <p>
        Weitere Informationen zum Datenschutz bei Discord findest du in der
        <a href="https://discord.com/privacy" target="_blank" rel="noopener">Datenschutzerklärung von Discord</a>.
      </p>

      <h3>c) Passwort-Login (optionaler lokaler Account)</h3>
      <p>
        Alternativ kannst du dich mit Benutzername und Passwort registrieren. Dabei speichern wir:
      </p>
      <ul>
        <li>Gewählten Benutzernamen</li>
        <li>E-Mail-Adresse (optional)</li>
        <li>Passwort (bcrypt-gehasht – das Klartext-Passwort ist uns nicht bekannt)</li>
        <li>Registrierungsdatum und letzte Anmeldung</li>
      </ul>
      <p>Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO.</p>

      <h3>d) Whitelist-Antrag</h3>
      <p>
        Beim Ausfüllen eines Whitelist-Antrags werden die Antworten auf die Fragen sowie dein
        Discord- oder lokaler Account-Bezug gespeichert. Diese Daten werden für die Bearbeitung
        deines Antrags durch das Team verwendet und nach Ablehnung oder Löschung deines Accounts
        gelöscht. Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO.
      </p>

      <h3>e) FiveM-Server-Daten (Spielerdaten)</h3>
      <p>
        Beim Spielen auf unserem FiveM-Server werden spielrelevante Daten (Charakter, Inventar,
        Fraktionszugehörigkeit, Finanzen, Fahrzeuge etc.) in unserer Gameserver-Datenbank gespeichert.
        Identifizierungsmerkmal ist dein FiveM-Lizenzschlüssel (Rockstar-Account-Hash) sowie dein
        Discord-Account. Diese Daten werden benötigt, um das Fortschreiten deines Charakters zu
        speichern. Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO.
      </p>

      <!-- 3. Cookies & Sessions -->
      <h2>3. Cookies &amp; Sessions</h2>
      <p>
        Diese Website setzt ausschließlich einen technisch notwendigen Session-Cookie
        (<code>PHPSESSID</code>). Dieser Cookie enthält keine personenbezogenen Daten, sondern
        lediglich eine zufällige Session-ID, die beim Schließen des Browsers abläuft.
        Es werden keine Tracking-Cookies, Analytics- oder Werbe-Cookies verwendet.
        Rechtsgrundlage: § 165 Abs. 3 TKG 2021 (technisch notwendige Cookies bedürfen keiner Einwilligung).
      </p>

      <!-- 4. Weitergabe an Dritte -->
      <h2>4. Weitergabe an Dritte</h2>
      <p>
        Deine Daten werden nicht an Dritte verkauft oder vermietet. Eine Weitergabe erfolgt nur in
        folgenden Ausnahmefällen:
      </p>
      <ul>
        <li><strong>Discord:</strong> OAuth2-Authentifizierung – Discord erhält die Anfrage zur
        Authentifizierung gemäß deren Datenschutzrichtlinien.</li>
        <li><strong>Hosting-Anbieter:</strong> Unser Webserver (Hetzner) hat technischen Zugriff auf
        Serverlogs. Hetzner ist DSGVO-konform und hat einen Auftragsverarbeitungsvertrag (AVV) mit uns.</li>
        <li><strong>Gesetzliche Verpflichtung:</strong> Bei berechtigten behördlichen Anfragen.</li>
      </ul>

      <!-- 5. Speicherdauer -->
      <h2>5. Speicherdauer</h2>
      <ul>
        <li><strong>Server-Logs:</strong> 30 Tage, dann automatische Löschung</li>
        <li><strong>Session-Daten:</strong> bis Sitzungsende (Browser schließen) bzw. 2 Stunden Inaktivität</li>
        <li><strong>Account-Daten:</strong> bis zur Löschung des Accounts auf Anfrage</li>
        <li><strong>Whitelist-Anträge:</strong> bis zur Bearbeitung; abgelehnte Anträge werden nach
        90 Tagen gelöscht</li>
        <li><strong>FiveM-Spielerdaten:</strong> so lange du aktiv spielst bzw. auf deine Anfrage hin</li>
      </ul>

      <!-- 6. Deine Rechte -->
      <h2>6. Deine Rechte (Betroffenenrechte)</h2>
      <p>Gemäß DSGVO stehen dir folgende Rechte zu:</p>
      <ul>
        <li><strong>Auskunft (Art. 15 DSGVO):</strong> Du kannst jederzeit Auskunft über deine gespeicherten Daten verlangen.</li>
        <li><strong>Berichtigung (Art. 16 DSGVO):</strong> Du hast das Recht auf Korrektur unrichtiger Daten.</li>
        <li><strong>Löschung (Art. 17 DSGVO):</strong> Du kannst die Löschung deiner Daten verlangen, soweit keine gesetzliche Aufbewahrungspflicht entgegensteht.</li>
        <li><strong>Einschränkung der Verarbeitung (Art. 18 DSGVO):</strong> Du kannst die Einschränkung der Verarbeitung verlangen.</li>
        <li><strong>Datenübertragbarkeit (Art. 20 DSGVO):</strong> Du hast das Recht, deine Daten in einem maschinenlesbaren Format zu erhalten.</li>
        <li><strong>Widerspruch (Art. 21 DSGVO):</strong> Du kannst der Verarbeitung auf Basis berechtigter Interessen widersprechen.</li>
        <li><strong>Widerruf der Einwilligung (Art. 7 Abs. 3 DSGVO):</strong> Eine erteilte Einwilligung kann jederzeit ohne Angabe von Gründen widerrufen werden.</li>
      </ul>
      <p>
        Zur Ausübung deiner Rechte wende dich per E-Mail an:
        <a href="mailto:[DEINE-EMAIL@DOMAIN.AT]">[DEINE-EMAIL@DOMAIN.AT]</a>
        oder kontaktiere uns auf unserem
        <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord-Server</a>.
      </p>

      <!-- 7. Beschwerderecht -->
      <h2>7. Beschwerderecht bei der Aufsichtsbehörde</h2>
      <p>
        Du hast das Recht, Beschwerde bei einer Datenschutz-Aufsichtsbehörde einzulegen.
        In Österreich ist die zuständige Behörde die:
      </p>
      <address>
        <strong>Datenschutzbehörde (DSB)</strong><br />
        Barichgasse 40–42<br />
        1030 Wien, Österreich<br />
        <a href="https://www.dsb.gv.at" target="_blank" rel="noopener">www.dsb.gv.at</a>
      </address>

      <!-- 8. Datensicherheit -->
      <h2>8. Datensicherheit</h2>
      <p>
        Diese Website wird über HTTPS (TLS) ausgeliefert, um die Übertragung deiner Daten zu verschlüsseln.
        Datenbankzugriffe erfolgen ausschließlich über Prepared Statements (Schutz vor SQL-Injection).
        Passwörter werden mit bcrypt gehasht. Wir setzen CSRF-Token-Prüfungen und strikte Session-Konfigurationen
        (HttpOnly, Secure, SameSite) ein.
      </p>

      <!-- 9. Externe Dienste -->
      <h2>9. Externe Dienste &amp; Drittanbieter</h2>
      <ul>
        <li>
          <strong>Google Fonts:</strong> Diese Website lädt Schriftarten von Google Fonts
          (<code>fonts.googleapis.com</code>). Dabei wird deine IP-Adresse an Google übermittelt.
          Weitere Informationen: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Datenschutzrichtlinie</a>.
          Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einheitlichem Design).
        </li>
        <li>
          <strong>Discord CDN:</strong> Profilbilder werden direkt von
          <code>cdn.discordapp.com</code> geladen. Discord erhebt dabei ggf. Zugriffsdaten gemäß
          deren Datenschutzrichtlinie.
        </li>
        <li>
          <strong>FiveM / Cfx.re:</strong> Für die Verbindung zum Spielserver kommuniziert dein
          FiveM-Client direkt mit Cfx.re-Diensten. Wir haben keinen Einfluss auf deren Datenverarbeitung.
        </li>
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
      <a href="index.php#rules">Regeln</a>
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

<script src="assets/js/main.js"></script>
</body>
</html>
