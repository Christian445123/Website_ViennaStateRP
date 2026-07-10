<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/permissions.php';

// ── Beta-Splash: Besucher einmalig auf beta.php umleiten ──────────────────────
if (!isset($_COOKIE['vsrp_beta_v2'])) {
    header('Location: beta.php');
    exit;
}

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
  <title>ViennaStateRP – GTA V Roleplay auf FiveM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a class="nav-logo" href="index.php"><span class="logo-accent">Vienna</span>StateRP</a>
    <ul class="nav-links" id="navLinks">
      <li><a href="#about">Über uns</a></li>
      <li><a href="#features">Features</a></li>
      <li><a href="#fraktionen">Fraktionen</a></li>
      <li><a href="#rules">Regeln</a></li>
      <li><a href="#join">Beitreten</a></li>
      <li><a href="#faq">FAQ</a></li>
      <li><a href="players.php">Server Status</a></li>
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
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:18px;height:18px"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
          Discord Login
        </a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-bg">
    <div class="hero-overlay"></div>
    <div class="hero-particles" id="particles"></div>
  </div>
  <div class="hero-content">
    <div class="hero-status" id="heroStatusBadge">
      <span class="hero-status-dot" id="heroStatusDot"></span>
      <span id="heroStatusText">Verbinde...</span>
    </div>
    <h1 class="hero-title">GTA V ROLEPLAY<br><span class="accent">AUF FIVEM</span></h1>
    <p class="hero-sub">Willkommen auf ViennaStateRP – Wiens realistischstem FiveM Roleplay-Server. Schreibe deine Geschichte in einer lebendigen Stadt.</p>

    <div class="hero-tags">
      <span class="hero-tag">🎮 FiveM / GTA V</span>
      <span class="hero-tag">🗺️ Custom MLOs</span>
      <span class="hero-tag">🇦🇹 Österreich</span>
      <span class="hero-tag">🎭 Whitelist</span>
      <span class="hero-tag">18+</span>
    </div>

    <div class="hero-actions">
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary btn-lg">▶ Server verbinden</a>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="btn btn-discord btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:20px;height:20px"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord beitreten
      </a>
    </div>

    <div class="hero-stats-row">
      <div class="hero-stat">
        <span class="hero-stat-num" id="heroPlayers">–</span>
        <span class="hero-stat-lbl">Aktive Spieler</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat-num">64</span>
        <span class="hero-stat-lbl">Max. Slots</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat-num">6+</span>
        <span class="hero-stat-lbl">Fraktionen</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat-num">20+</span>
        <span class="hero-stat-lbl">Custom Jobs</span>
      </div>
      <div class="hero-stat">
        <span class="hero-stat-num">18+</span>
        <span class="hero-stat-lbl">Altersgrenze</span>
      </div>
    </div>
  </div>
  <div class="hero-scroll-hint">Scroll</div>
</section>

<!-- ABOUT -->
<section class="section" id="about">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Wer sind wir?</span>
      <h2>Uber <span class="accent">ViennaStateRP</span></h2>
      <p>Ein realistisches Roleplay-Erlebnis, aufgebaut von unserer Community.</p>
    </div>
    <div class="about-grid">
      <div class="about-card">
        <div class="about-icon">🏙️</div>
        <h3>Wien als Spielwelt</h3>
        <p>Eine detailreich gestaltete Spielwelt mit Custom MLOs und realistischen Locations.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">👮</div>
        <h3>Aktive Fraktionen</h3>
        <p>Polizei, EMS, Feuerwehr und Mechaniker – jede Fraktion mit eigener Struktur.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">🛠️</div>
        <h3>Custom Scripts</h3>
        <p>Eigens entwickelte Ressourcen für ein einzigartiges Gameplay-Erlebnis.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">🤝</div>
        <h3>Aktive Community</h3>
        <p>Freundliche Community und aktives Staff-Team mit regelmäßigen Events.</p>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="section section-dark" id="features">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Was wir bieten</span>
      <h2>Server <span class="accent">Features</span></h2>
    </div>
    <div class="features-list">
      <?php
      $features = [
        ['🏙️', 'Fraktionssystem',        'Polizei, EMS, Feuerwehr, Justiz und kriminelle Organisationen – jede Fraktion mit eigenen Scripts, Rängen und internen Strukturen.'],
        ['💼', 'Wirtschaft & Jobs',      'Über 20 legale Jobs, eigenes Banking-System, Aktienmarkt und ein funktionierender Schwarzmarkt.'],
        ['🚘', 'Custom Fahrzeuge',        'Hunderte Add-on-Fahrzeuge mit realistischer Schadensphysik, Tuning-System und Fahrzeugkauf/-verkauf.'],
        ['🏠', 'Immobilien',             'Häuser und Wohnungen kaufen oder mieten, individuell einrichten und als Lager nutzen.'],
        ['📱', 'Smartphone-System',       'In-Game Handy mit Kontakten, Anrufen, SMS, Kamera, Internet-Browser und eigenem Social-Media-Feed.'],
        ['🗺️', 'Custom MLOs',       'Eigene Interior-Objekte für Fraktionsgebäude, Krankenhäuser, Gerichte und immersive Locations.'],
        ['⚖️', 'Justizsystem',           'Staatsanwaltschaft, Richter und Anwälte – Verfahren werden vollständig im Roleplay verhandelt.'],
        ['🔫', 'Überfall-System',     'Geregelte Überfälle mit Cop-Anforderungen, Verhandlungspflicht und Beutelimits nach Event-Typ.'],
        ['🎭', 'Charakter-System',        'Mehrere Charaktere pro Spieler, eigene Backstory, Charakter-Entwicklung und New Life Regel.'],
        ['💰', 'Wirtschaft & Schwarzmarkt','Illegale Aktivitäten, Drogenanbau, Fahrzeugschmuggel und organisiertes Verbrechen.'],
        ['📰', 'Journalist / Reporter',   'Eigener Journalismus-Job mit Kamera, Berichten und einem In-Game Social-Media-Feed.'],
        ['🛠️', 'Aktive Entwicklung',  'Regelmäßige Updates, Community-Feedback-Runden und ein aktives Dev-Team mit transparenter Roadmap.'],
      ];
      foreach ($features as $f): ?>
      <div class="feature-item">
        <span class="feature-icon"><?= $f[0] ?></span>
        <div>
          <h3><?= $f[1] ?></h3>
          <p><?= $f[2] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FRAKTIONEN -->
<section class="section" id="fraktionen">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Team</span>
      <h2>Unsere <span class="accent">Fraktionen</span></h2>
      <p>Wähle deine Rolle – staatlich, kriminell oder als unabhängiger Zivilist.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
      <?php
      // Fraktionen aus DB laden; Fallback auf hartcodierte Liste
      $fraktionen_db = [];
      if (isset($pdo)) {
        try {
          $fraktionen_db = $pdo->query(
            "SELECT emoji, name, typ, farbe, beschreibung, slots_label FROM fraktionen WHERE aktiv=1 ORDER BY sort_order ASC, id ASC"
          )->fetchAll();
        } catch (Exception $e) { /* Fallback greift */ }
      }
      if ($fraktionen_db) {
        $fraktionen = array_map(fn($r) => [$r['emoji'], $r['name'], $r['typ'], $r['farbe'], $r['beschreibung'], $r['slots_label']], $fraktionen_db);
      } else {
        $fraktionen = [
          ['👮', 'Landespolizeidirektion Wien', 'STAATLICH · LEGAL',   '#3b82f6', 'Die LSPD ist die wichtigste Strafverfolgungsbehörde. Beamte patrouillieren, ermitteln und sorgen für Recht und Ordnung.',       'Offen'],
          ['🚑', 'Emergency Medical Services',  'STAATLICH · LEGAL',   '#ef4444', 'Das EMS versorgt Verletzte, reanimiert Bewusstlose und betreibt das Krankenhaus im Schichtbetrieb.',                         'Offen'],
          ['🚒', 'Feuerwehr Wien',               'STAATLICH · LEGAL',   '#f97316', 'Die Feuerwehr bekämpft Brände, rettet eingeklemmte Personen und unterstützt das EMS bei Großeinsätzen.',                     'Offen'],
          ['⚖️', 'Staatsanwaltschaft & Justiz', 'STAATLICH · LEGAL',   '#8b5cf6', 'Staatsanwälte, Richter und Anwälte – Verfahren werden vollständig im Roleplay verhandelt.',                                   'Offen'],
          ['🔫', 'Kriminelle Organisationen',    'KRIMINELL · ILLEGAL', '#64748b', 'Mehrere kriminelle Gruppierungen mit eigenen Territorien, Hierarchien und illegalen Einnahmequellen.',                       'Bewährungsphase'],
          ['🧑‍💼', 'Unabhängige / Zivilisten', 'FREELANCE · NEUTRAL', '#22c55e', 'Als Zivilist betreibst du ein eigenes Unternehmen, arbeitest als Journalist, Anwalt oder Unternehmer.',                      'OFFEN'],
        ];
      }
      foreach ($fraktionen as [$ico, $name, $type, $color, $desc, $slots]): ?>
      <div style="background:var(--bg-card);border:1px solid rgba(255,255,255,.08);border-top:3px solid <?= $color ?>;border-radius:14px;padding:22px 20px;position:relative;transition:.2s">
        <div style="font-size:1.8rem;margin-bottom:10px"><?= $ico ?></div>
        <div style="font-size:.65rem;font-weight:700;letter-spacing:2px;color:<?= $color ?>;margin-bottom:6px"><?= $type ?></div>
        <h3 style="font-size:.95rem;margin-bottom:8px"><?= $name ?></h3>
        <p style="font-size:.83rem;color:#94a3b8;margin-bottom:14px;line-height:1.5"><?= $desc ?></p>
        <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:999px;padding:3px 12px;font-size:.72rem;color:#e2e8f0">
          <span style="width:6px;height:6px;border-radius:50%;background:<?= $color ?>"></span>
          SLOTS <?= $slots ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<section class="section" id="rules">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Fair Play</span>
      <h2>Grundlegendes <span class="accent">Regelwerk</span></h2>
      <p>Das Regelwerk gilt für alle Spieler. Unwissenheit schützt nicht vor Konsequenzen.</p>
    </div>
    <div class="rule-card">
      <h3>💡 1. Grundlegende Server-Philosophie</h3>
      <div class="rule-list">
        <div class="rule-item">Sei kein Idiot – behandle jeden so, wie du selbst behandelt werden möchtest.</div>
        <div class="rule-item">Dein Charakter hat Angst um sein Leben und handelt nachvollziehbar (Realitätsprinzip).</div>
      </div>
    </div>
    <div class="rule-card">
      <h3>🔫 2. Tödliche Gewalt (RDM & VDM)</h3>
      <div class="rule-list">
        <div class="rule-item">RDM: Grundloses Töten ohne vorheriges RP-Gespräch ist verboten. → BAN</div>
        <div class="rule-item">VDM: Fahrzeuge als primäre Waffe ohne RP-Hintergrund. → BAN</div>
      </div>
    </div>
    <div class="rule-card">
      <h3>🎭 3. Roleplay-Pflichten</h3>
      <div class="rule-list">
        <div class="rule-item">New Life Regel: Nach dem Tod vergisst dein Charakter alle Ereignisse die dazu führten.</div>
        <div class="rule-item">FailRP: Unlogische Handlungen im RP-Kontext. → WARN/KICK</div>
        <div class="rule-item">PowerGaming: Situationen erzwingen die dem Gegner keine Reaktionschance lassen. → KICK</div>
      </div>
    </div>
    <div class="rule-card">
      <h3>🚫 4. Verbotene Handlungen</h3>
      <div class="rule-list">
        <div class="rule-item">Cheating / Hacking / Bugusing jeglicher Art. → BAN</div>
        <div class="rule-item">NSFW-RP, Gewaltverherrlichung, rassistische Beleidigungen. → BAN</div>
        <div class="rule-item">Echtgeld-Handel (RMT) für Ingame-Güter. → BAN</div>
      </div>
    </div>
  </div>
</section>

<!-- JOIN -->
<section class="section section-dark" id="join">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Mitmachen</span>
      <h2>Zugang zum <span class="accent">Server</span></h2>
      <p>Kein komplizierter Bewerbungsprozess – einfach Discord beitreten und verifizieren.</p>
    </div>
    <div class="about-grid" style="max-width:800px;margin:0 auto">
      <div class="about-card" style="text-align:center">
        <div class="about-icon" style="font-size:2.5rem">01</div>
        <h3>Discord beitreten</h3>
        <p>Tritt unserem Discord bei und lerne die Community kennen.</p>
      </div>
      <div class="about-card" style="text-align:center">
        <div class="about-icon" style="font-size:2.5rem">02</div>
        <h3>Verifizieren</h3>
        <p>Verifiziere dich im Verification-Channel gemäß den Anweisungen.</p>
      </div>
      <div class="about-card" style="text-align:center">
        <div class="about-icon" style="font-size:2.5rem">03</div>
        <h3>Server joinen</h3>
        <p>Fertig! Verbinde dich und starte dein Roleplay-Abenteuer.</p>
      </div>
    </div>

    <!-- Voraussetzungen -->
    <div style="max-width:600px;margin:40px auto 0;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:28px 32px">
      <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b;margin-bottom:18px">Voraussetzungen</div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach(['Discord-Mitglied &amp; verifiziert','Lizenziertes GTA V','FiveM installiert','Funktionsfähiges Mikrofon','Regelwerk gelesen &amp; akzeptiert'] as $req): ?>
        <div style="display:flex;align-items:center;gap:12px;color:#e2e8f0;font-size:.9rem">
          <span style="width:20px;height:20px;border-radius:50%;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.7rem;color:#22c55e">✓</span>
          <?= $req ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="text-align:center;margin-top:48px">
      <p style="color:#4b5563;font-size:.85rem;margin-bottom:8px;font-family:'Orbitron',sans-serif;letter-spacing:1px">SERVER IP</p>
      <code style="font-size:1rem;padding:12px 24px;border-radius:8px"><?= htmlspecialchars(FIVEM_IP) ?>:<?= htmlspecialchars(FIVEM_PORT) ?></code>
      <div style="margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener" class="btn btn-discord">Discord & Whitelist</a>
        <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary">Server verbinden</a>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section" id="faq">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Häufige Fragen</span>
      <h2>FAQ <span class="accent">Antworten</span></h2>
    </div>
    <div style="max-width:800px;margin:0 auto;display:flex;flex-direction:column;gap:8px" id="faqList">
      <?php
      $faqs = [
        ['Muss ich Mitglied bei GC24/7 sein?',           'Nein. Jeder kann auf ViennaStateRP spielen – du musst nur unserem Discord beitreten und dich verifizieren.'],
        ['Kann ich mehrere Charaktere spielen?',           'Ja. Du kannst mehrere Charaktere erstellen, jeder mit seiner eigenen Geschichte und Entwicklung.'],
        ['Was passiert wenn mein Charakter stirbt?',       'New Life Regel: Dein Charakter vergisst alle Ereignisse die direkt zu seinem Tod geführt haben und kehrt nicht an den Tatort zurück.'],
        ['Wie bewerbe ich mich für eine Fraktion?',        'Direkt über Discord. Jede Fraktion hat einen eigenen Bewerbungskanal mit Anforderungen und Leitfaden.'],
        ['Ich wurde gebannt – was kann ich tun?',          'Öffne ein Support-Ticket auf unserem Discord. Das Staff-Team prüft deinen Fall und gibt dir eine Rückmeldung.'],
        ['Ist der Server kostenlos?',                      'Ja, vollständig kostenlos. Du brauchst nur ein lizenziertes GTA V und FiveM.'],
        ['Wird das Regelwerk aktualisiert?',               'Ja. Regeländerungen werden in Discord angekündigt. Es gilt immer das aktuellste Regelwerk.'],
        ['Ab welchem Alter darf ich spielen?',             'Der Server ist ab 18 Jahren. Ausnahmen können in begründeten Fällen beim Staff beantragt werden.'],
      ];
      foreach ($faqs as $i => [$q, $a]): ?>
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;overflow:hidden">
        <button onclick="toggleFaq(<?= $i ?>)" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:none;border:none;color:#e2e8f0;cursor:pointer;font-family:'Rajdhani',sans-serif;font-size:.95rem;font-weight:600;text-align:left;gap:12px">
          <span><?= $q ?></span>
          <span id="faqIcon<?= $i ?>" style="font-size:1.1rem;flex-shrink:0;color:#64748b;transition:.2s">+</span>
        </button>
        <div id="faqBody<?= $i ?>" style="display:none;padding:0 20px 16px;color:#94a3b8;font-size:.9rem;line-height:1.6"><?= $a ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- UEBERFALLS-MATRIX -->
<section class="section section-dark" id="matrix">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Cop-Anforderungen</span>
      <h2>Überfalls<span class="accent">-Matrix</span></h2>
      <p>Ein Überfall darf erst gestartet werden wenn die Mindestanzahl an Polizisten im Dienst erreicht ist.</p>
    </div>
    <div style="max-width:900px;margin:0 auto;overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.88rem">
        <thead>
          <tr style="border-bottom:2px solid rgba(232,184,109,.3)">
            <th style="padding:12px 16px;text-align:left;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Event</th>
            <th style="padding:12px 16px;text-align:center;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Min. Cops</th>
            <th style="padding:12px 16px;text-align:center;color:#e8b86d;font-size:.75rem;text-transform:uppercase;letter-spacing:1.5px">Max. Räuber</th>
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
      <div style="margin-top:20px;display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;gap:10px;align-items:flex-start;color:#94a3b8;font-size:.85rem">
          <span style="color:#e8b86d;flex-shrink:0">🤝</span>
          <span><strong style="color:#e2e8f0">Verhandlungspflicht:</strong> Vor dem Schusswechsel muss eine Verhandlung zwischen Polizei und Räubern stattfinden.</span>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;color:#94a3b8;font-size:.85rem">
          <span style="color:#e8b86d;flex-shrink:0">⏱️</span>
          <span><strong style="color:#e2e8f0">Cooldown:</strong> Nach einem Überfall gilt für die beteiligte Crime-Fraktion eine Sperre von 1 Stunde.</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATUS -->
<section class="section" id="status">
  <div class="container">
    <div class="section-header">
      <span class="section-tag">Live</span>
      <h2>Server <span class="accent">Status</span></h2>
    </div>
    <div class="index-status-wrap">
      <div class="index-status-top">
        <div class="index-status-indicator">
          <span class="status-dot" id="indexStatusDot"></span>
          <span id="indexStatusText" style="font-size:.9rem;color:#94a3b8">Verbinde...</span>
        </div>
        <a href="players.php" class="btn btn-outline" style="font-size:.82rem;padding:6px 14px">Details →</a>
      </div>
      <div class="index-status-stats">
        <div class="index-stat">
          <span class="index-stat-num" id="indexPlayers">–</span>
          <span class="index-stat-lbl">Aktive Spieler</span>
        </div>
        <div class="index-stat-divider"></div>
        <div class="index-stat">
          <span class="index-stat-num" id="indexMaxPlayers">–</span>
          <span class="index-stat-lbl">Slots gesamt</span>
        </div>
        <div class="index-stat-divider"></div>
        <div class="index-stat">
          <span class="index-stat-num" id="indexPct">–</span>
          <span class="index-stat-lbl">Auslastung</span>
        </div>
      </div>
      <div class="status-bar-wrap" style="margin-top:20px">
        <div class="status-bar" id="indexStatusBar" style="width:0%"></div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-bg"></div>
  <div class="container cta-content">
    <h2>Bereit, deine Geschichte zu schreiben?</h2>
    <p>Tritt noch heute unserer Community bei und erlebe Roleplay auf höchstem Niveau.</p>
    <div class="cta-actions">
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>" class="btn btn-primary btn-lg">Jetzt verbinden</a>
      <?php if (!$user): ?>
      <a href="login.php" class="btn btn-discord btn-lg">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:20px;height:20px"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord Login
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <span class="logo-accent">Vienna</span>StateRP
      <p>Wiens #1 FiveM Roleplay Server. Powered by GamingCommunity24/7.</p>
    </div>
    <div class="footer-links">
      <h5>Navigation</h5>
      <a href="#about">Über uns</a>
      <a href="#features">Features</a>
      <a href="#rules">Regeln</a>
      <a href="#join">Beitreten</a>
    </div>
    <div class="footer-links">
      <h5>Community</h5>
      <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
      <a href="login.php">Login</a>
      <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="footer-links">
      <h5>Server</h5>
      <a href="players.php">Server Status</a>
      <a href="<?= htmlspecialchars(FIVEM_LINK) ?>">Verbinden</a>
      <p class="server-ip"><?= htmlspecialchars(FIVEM_IP) ?>:<?= htmlspecialchars(FIVEM_PORT) ?></p>
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
<script>
// Hero server status
(async function() {
  const dot  = document.getElementById('heroStatusDot');
  const txt  = document.getElementById('heroStatusText');
  const badge = document.getElementById('heroStatusBadge');
  const num  = document.getElementById('heroPlayers');
  try {
    const r = await fetch('api/server_status.php');
    const d = await r.json();
    if (d.online) {
      dot.style.background = '#22c55e';
      dot.style.boxShadow  = '0 0 8px #22c55e';
      txt.textContent = 'Server Online';
      if (num) num.textContent = d.players;
    } else {
      dot.style.background = '#ef4444';
      txt.textContent = 'Server Offline';
      badge.style.background = 'rgba(239,68,68,0.1)';
      badge.style.borderColor = 'rgba(239,68,68,0.25)';
      badge.style.color = '#ef4444';
    }
  } catch(e) {
    txt.textContent = 'Status unbekannt';
  }
})();

// Index status widget
(async function indexStatus() {
  const dot = document.getElementById('indexStatusDot');
  const txt = document.getElementById('indexStatusText');
  const p   = document.getElementById('indexPlayers');
  const m   = document.getElementById('indexMaxPlayers');
  const pct = document.getElementById('indexPct');
  const bar = document.getElementById('indexStatusBar');
  if (!dot) return;
  async function load() {
    try {
      const r = await fetch('api/server_status.php');
      const d = await r.json();
      if (d.online) {
        dot.className = 'status-dot online';
        txt.textContent = 'Online';
        const pc = d.max_players > 0 ? Math.round(d.players/d.max_players*100) : 0;
        p.textContent   = d.players;
        m.textContent   = d.max_players;
        pct.textContent = pc + '%';
        bar.style.width = pc + '%';
      } else {
        dot.className = 'status-dot offline';
        txt.textContent = 'Offline';
        p.textContent = m.textContent = pct.textContent = '–';
        bar.style.width = '0%';
      }
    } catch(e) {
      dot.className = 'status-dot offline';
      txt.textContent = 'Nicht erreichbar';
    }
  }
  load();
  setInterval(load, 30000);
})();
</script>
<script>
function toggleFaq(i) {
  const body = document.getElementById('faqBody' + i);
  const icon = document.getElementById('faqIcon' + i);
  const open = body.style.display === 'block';
  // Alle schließen
  document.querySelectorAll('[id^="faqBody"]').forEach(b => b.style.display = 'none');
  document.querySelectorAll('[id^="faqIcon"]').forEach(ic => { ic.textContent = '+'; ic.style.transform = ''; });
  if (!open) {
    body.style.display = 'block';
    icon.textContent = '–';
  }
}
</script>

</body>
</html>