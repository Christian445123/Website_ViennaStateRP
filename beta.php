<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/discord_config.php';

// ── Cookie setzen und zur Hauptseite weiterleiten ──────────────────────────────
if (isset($_GET['proceed'])) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('vsrp_beta_seen', '1', [
        'expires'  => time() + 60 * 60 * 24 * 7,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Beta‑Start – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }

    body {
      font-family: 'Rajdhani', system-ui, sans-serif;
      background: #0a0a0f;
      color: #e2e8f0;
      line-height: 1.65;
      overflow-x: hidden;
      min-height: 100vh;
    }

    /* ── Full-screen split layout ─────────────────────────────── */
    .page {
      display: grid;
      grid-template-columns: 1fr 520px;
      min-height: 100vh;
    }

    /* Left: Banner / Visual side */
    .visual {
      position: sticky;
      top: 0;
      height: 100vh;
      overflow: hidden;
    }
    .visual-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      display: block;
      filter: brightness(.7);
    }
    .visual-overlay {
      position: absolute;
      inset: 0;
      background:
        linear-gradient(to right,  transparent 55%, #0a0a0f 100%),
        linear-gradient(to bottom, transparent 55%, rgba(0,0,0,.6) 100%);
    }
    .visual-tagline {
      position: absolute;
      bottom: 48px;
      left: 48px;
      right: 0;
    }
    .visual-tagline h2 {
      font-family: 'Orbitron', sans-serif;
      font-size: clamp(1.4rem, 2.5vw, 2.4rem);
      font-weight: 900;
      line-height: 1.15;
      color: #fff;
      text-shadow: 0 2px 20px rgba(0,0,0,.8);
    }
    .visual-tagline h2 span { color: #e8b86d; }
    .visual-tagline p {
      margin-top: 8px;
      font-size: .95rem;
      color: rgba(255,255,255,.6);
    }

    /* Right: Content panel */
    .panel {
      background: #0f0f16;
      border-left: 1px solid rgba(255,255,255,.08);
      padding: 48px 40px 56px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    /* Badge */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(232,184,109,.12);
      border: 1px solid rgba(232,184,109,.3);
      color: #e8b86d;
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      padding: 5px 14px;
      border-radius: 999px;
      margin-bottom: 18px;
      width: fit-content;
    }

    /* Title */
    .title {
      font-family: 'Orbitron', sans-serif;
      font-size: clamp(1.3rem, 2.8vw, 1.85rem);
      font-weight: 900;
      line-height: 1.2;
      background: linear-gradient(100deg, #e8b86d 0%, #c084fc 60%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 4px;
    }
    .subtitle {
      color: #64748b;
      font-size: .9rem;
      margin-bottom: 28px;
    }

    /* Divider */
    .divider {
      height: 1px;
      background: rgba(255,255,255,.07);
      margin: 20px 0;
    }

    /* Prose */
    .prose { display: flex; flex-direction: column; gap: 13px; font-size: .93rem; }
    .prose p { color: #cbd5e1; }
    .prose strong { color: #e8b86d; font-weight: 700; }

    /* Note box */
    .note {
      background: rgba(124,58,237,.12);
      border-left: 3px solid #7c3aed;
      padding: 10px 14px;
      border-radius: 0 8px 8px 0;
      color: #c4b5fd;
      font-size: .88rem;
    }
    .note strong { color: #c4b5fd; }

    /* List */
    .blist { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 7px; }
    .blist li {
      padding-left: 18px;
      position: relative;
      color: #94a3b8;
      font-size: .9rem;
    }
    .blist li::before { content: '–'; position: absolute; left: 0; color: #e8b86d; }

    /* Info boxes */
    .info-box {
      background: #111118;
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 10px;
      padding: 14px 16px;
      font-size: .88rem;
      color: #94a3b8;
    }
    .info-box strong { color: #e8b86d; }
    .info-box a { color: #5865f2; text-decoration: underline; }
    .info-box a:hover { color: #7289da; }

    .goal-box {
      background: rgba(34,197,94,.06);
      border: 1px solid rgba(34,197,94,.18);
      border-radius: 10px;
      padding: 14px 16px;
      font-size: .88rem;
      color: #86efac;
    }
    .goal-box strong { color: #22c55e; }

    /* Time highlight */
    .time-box {
      text-align: center;
      background: rgba(232,184,109,.08);
      border: 1px solid rgba(232,184,109,.22);
      border-radius: 10px;
      padding: 14px;
      font-size: 1rem;
      color: #e2e8f0;
    }
    .time-box strong { color: #e8b86d; font-size: 1.1em; }

    /* Sign-off */
    .signoff { color: #475569; font-size: .85rem; text-align: right; font-style: italic; }

    /* CTA buttons */
    .cta {
      display: flex;
      gap: 12px;
      margin-top: 28px;
      flex-wrap: wrap;
    }
    .btn-proceed {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: linear-gradient(135deg, #7c3aed, #5b21b6);
      color: #fff;
      font-family: 'Rajdhani', sans-serif;
      font-size: .95rem;
      font-weight: 700;
      letter-spacing: .04em;
      padding: 13px 20px;
      border-radius: 10px;
      text-decoration: none;
      transition: opacity .2s, transform .15s;
      border: none;
      cursor: pointer;
    }
    .btn-proceed:hover { opacity: .88; transform: translateY(-1px); }

    .btn-discord {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: #5865f2;
      color: #fff;
      font-family: 'Rajdhani', sans-serif;
      font-size: .95rem;
      font-weight: 700;
      letter-spacing: .04em;
      padding: 13px 20px;
      border-radius: 10px;
      text-decoration: none;
      transition: opacity .2s, transform .15s;
    }
    .btn-discord:hover { opacity: .88; transform: translateY(-1px); }

    /* ── Mobile: stack vertically ─────────────────────────────── */
    @media (max-width: 860px) {
      .page { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
      .visual { position: relative; height: 52vw; min-height: 220px; max-height: 340px; }
      .visual-overlay {
        background:
          linear-gradient(to bottom, transparent 40%, #0a0a0f 100%);
      }
      .visual-tagline { display: none; }
      .panel { border-left: none; border-top: 1px solid rgba(255,255,255,.08); padding: 32px 22px 48px; }
    }
    @media (max-width: 480px) {
      .cta { flex-direction: column; }
    }
  </style>
</head>
<body>

<div class="page">

  <!-- Visual / Banner -->
  <div class="visual">
    <img class="visual-img" src="assets/img/beta-banner.jpg" alt="ViennaStateRP Beta" />
    <div class="visual-overlay"></div>
    <div class="visual-tagline">
      <h2>Wien.<br><span>Heute.</span><br>19:00 Uhr.</h2>
      <p>ViennaStateRP öffnet die Beta‑Phase.</p>
    </div>
  </div>

  <!-- Content Panel -->
  <div class="panel">

    <div class="badge">📢 Ankündigung</div>
    <h1 class="title">ViennaStateRP – Beta‑Start!</h1>
    <p class="subtitle">Heute um 19:00 Uhr geht es los!</p>

    <div class="prose">
      <p>Liebe Community,<br>
      heute ist es endlich soweit: <strong>ViennaStateRP öffnet um 19:00 Uhr offiziell die Beta‑Phase!</strong></p>

      <p class="note">Anmerkung: Es wird für alle <strong>Verifizierten</strong> der Server freigeschaltet!</p>

      <p>Wir haben in den letzten Wochen intensiv entwickelt, getestet und optimiert – jetzt seid ihr dran, die Stadt mit Leben zu füllen.</p>

      <p>Bitte denkt daran, dass es sich um eine <strong>Beta</strong> handelt. Das bedeutet:</p>
      <ul class="blist">
        <li>Es können Bugs, Fehler oder unerwartete Probleme auftreten.</li>
        <li>Manche Systeme sind noch nicht final.</li>
        <li>Performance‑Optimierungen laufen weiterhin im Hintergrund.</li>
      </ul>

      <div class="info-box">
        <strong>🔧 Probleme oder Bugs?</strong><br>
        Meldet euch über ein
        <a href="https://discord.com/channels/1520815251376050356/1520863619485536489" target="_blank" rel="noopener">Bugreport Ticket</a>
        oder
        <a href="https://discord.com/channels/1520815251376050356/1525093395771687054" target="_blank" rel="noopener">#bug-report</a>.
        Je genauer eure Beschreibung, desto schneller können wir reagieren.
      </div>

      <div class="goal-box">
        <strong>🎯 Ziel der Beta:</strong><br>
        Gemeinsam mit euch wollen wir Fehler finden, Systeme verbessern und ViennaStateRP für den offiziellen Release stabil vorbereiten.
      </div>

      <div class="time-box">
        🕗 Start: <strong>Heute 10.07.2026, 19:00 Uhr</strong>
      </div>

      <p>Wir freuen uns auf euch – und auf die ersten RP‑Momente in Wien!<br>
      Danke für eure Unterstützung und viel Spaß beim Spielen!</p>

      <p class="signoff">Euer VSRP I Team</p>
    </div>

    <div class="cta">
      <a href="https://discord.gg/c33S5eNf?event=1525094898347540520" target="_blank" rel="noopener" class="btn-discord">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" width="18" height="18"><path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
        Discord Event
      </a>
      <a href="beta.php?proceed=1" class="btn-proceed">
        Zur Hauptseite &rarr;
      </a>
    </div>

  </div>
</div>

</body>
</html>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
  <style>
    /* ── Splash-Layout ─────────────────────────────────────────── */
    html, body { min-height: 100vh; }
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      background: var(--bg);
      /* Subtiler animierter Hintergrund */
      background-image:
        radial-gradient(ellipse 80% 60% at 50% -10%, rgba(124,58,237,.18) 0%, transparent 70%),
        radial-gradient(ellipse 60% 40% at 80% 110%, rgba(232,184,109,.10) 0%, transparent 60%);
    }

    .splash-wrap {
      width: 100%;
      max-width: 680px;
      animation: splashIn .45s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes splashIn {
      from { opacity: 0; transform: translateY(32px) scale(.97); }
      to   { opacity: 1; transform: none; }
    }

    .splash-card {
      background: var(--bg-card);
      border: 1px solid var(--border-2);
      border-radius: var(--r-lg);
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,.65), 0 0 0 1px rgba(124,58,237,.15);
    }

    /* Banner */
    .splash-banner {
      line-height: 0;
      position: relative;
    }
    .splash-banner img {
      width: 100%;
      max-height: 320px;
      object-fit: cover;
      object-position: center top;
      display: block;
    }
    .splash-banner-overlay {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 80px;
      background: linear-gradient(to bottom, transparent, var(--bg-card));
    }

    /* Header */
    .splash-header {
      padding: 0 28px 22px;
      text-align: center;
    }
    .splash-badge {
      display: inline-block;
      background: rgba(232,184,109,.12);
      border: 1px solid rgba(232,184,109,.3);
      color: var(--gold);
      font-size: .7rem;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      padding: 5px 16px;
      border-radius: var(--r-full);
      margin-bottom: 14px;
    }
    .splash-title {
      font-family: 'Orbitron', sans-serif;
      font-size: clamp(1.4rem, 4vw, 2rem);
      font-weight: 900;
      background: linear-gradient(90deg, var(--gold) 30%, var(--purple));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 6px;
      line-height: 1.2;
    }
    .splash-sub {
      color: var(--text-2);
      font-size: .95rem;
    }

    /* Body */
    .splash-body {
      padding: 0 28px 24px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      font-size: .95rem;
      color: var(--text);
      line-height: 1.75;
    }
    .splash-body strong { color: var(--gold); }

    .splash-note {
      background: rgba(124,58,237,.1);
      border-left: 3px solid var(--purple);
      padding: 10px 14px;
      border-radius: 0 var(--r-sm) var(--r-sm) 0;
    }

    .splash-list { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 7px; }
    .splash-list li { padding-left: 20px; position: relative; color: var(--text-2); }
    .splash-list li::before { content: '–'; position: absolute; left: 0; color: var(--gold); }

    .splash-info {
      background: var(--bg-card-2);
      border: 1px solid var(--border);
      border-radius: var(--r-md);
      padding: 14px 16px;
      font-size: .9rem;
      color: var(--text-2);
    }
    .splash-info a { color: var(--discord); text-decoration: underline; }
    .splash-info a:hover { color: #7289da; }

    .splash-goal {
      background: rgba(34,197,94,.06);
      border: 1px solid rgba(34,197,94,.2);
      border-radius: var(--r-md);
      padding: 14px 16px;
      font-size: .9rem;
      color: var(--text-2);
    }
    .splash-goal strong { color: var(--green); -webkit-text-fill-color: var(--green); }

    .splash-time {
      text-align: center;
      font-size: 1.05rem;
      background: rgba(232,184,109,.08);
      border: 1px solid rgba(232,184,109,.25);
      border-radius: var(--r-md);
      padding: 14px;
    }
    .splash-time strong { color: var(--gold); -webkit-text-fill-color: var(--gold); }

    .splash-sign { color: var(--text-3); font-size: .88rem; text-align: right; }

    /* Footer */
    .splash-footer {
      display: flex;
      gap: 12px;
      padding: 16px 28px 26px;
      border-top: 1px solid var(--border);
      flex-wrap: wrap;
    }
    .splash-footer .btn-discord { flex: 1; justify-content: center; }
    .splash-proceed { flex: 1; justify-content: center; }

    @media (max-width: 480px) {
      .splash-header, .splash-body { padding-left: 18px; padding-right: 18px; }
      .splash-footer { padding: 14px 18px 22px; flex-direction: column; }
    }
  </style>
</head>
<body>

<div class="splash-wrap">
  <div class="splash-card">

    <!-- Banner -->
    <div class="splash-banner">
      <img src="assets/img/beta-banner.jpg" alt="ViennaStateRP Beta-Start" />
      <div class="splash-banner-overlay"></div>
    </div>

    <!-- Header -->
    <div class="splash-header">
      <div class="splash-badge">📢 Ankündigung</div>
      <h1 class="splash-title">ViennaStateRP – Beta‑Start!</h1>
      <p class="splash-sub">Heute um 19:00 Uhr geht es los!</p>
    </div>

    <!-- Body -->
    <div class="splash-body">
      <p>Liebe Community,<br>
      heute ist es endlich soweit: <strong>ViennaStateRP öffnet um 19:00 Uhr offiziell die Beta‑Phase!</strong></p>

      <p class="splash-note">Anmerkung: Es wird für alle <strong>Verifizierten</strong> der Server freigeschaltet!</p>

      <p>Wir haben in den letzten Wochen intensiv entwickelt, getestet und optimiert – jetzt seid ihr dran, die Stadt mit Leben zu füllen.</p>

      <p>Bitte denkt daran, dass es sich um eine <strong>Beta</strong> handelt. Das bedeutet:</p>
      <ul class="splash-list">
        <li>Es können Bugs, Fehler oder unerwartete Probleme auftreten.</li>
        <li>Manche Systeme sind noch nicht final.</li>
        <li>Performance‑Optimierungen laufen weiterhin im Hintergrund.</li>
      </ul>

      <div class="splash-info">
        <strong>🔧 Probleme oder Bugs?</strong><br>
        Meldet euch bei unserem Support-Team über ein
        <a href="https://discord.com/channels/1520815251376050356/1520863619485536489" target="_blank" rel="noopener">Bugreport Ticket</a>
        oder den Channel
        <a href="https://discord.com/channels/1520815251376050356/1525093395771687054" target="_blank" rel="noopener">#bug-report</a>.
        Je genauer eure Beschreibung, desto schneller können wir reagieren.
      </div>

      <div class="splash-goal">
        <strong>🎯 Ziel der Beta:</strong><br>
        Gemeinsam mit euch wollen wir Fehler finden, Systeme verbessern und ViennaStateRP für den offiziellen Release stabil und sauber vorbereiten.
      </div>

      <div class="splash-time">
        🕗 Start: <strong>Heute 10.07.2026, 19:00 Uhr</strong>
      </div>

      <p>Wir freuen uns auf euch – und auf die ersten RP‑Momente in Wien!<br>
      Danke für eure Unterstützung und viel Spaß beim Spielen!</p>

      <p class="splash-sign"><em>Euer VSRP I Team</em></p>
    </div>

    <!-- Footer -->
    <div class="splash-footer">
      <a href="https://discord.gg/c33S5eNf?event=1525094898347540520"
         target="_blank" rel="noopener"
         class="btn btn-discord">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" style="width:18px;height:18px">
          <path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/>
        </svg>
        Discord Event öffnen
      </a>
      <a href="beta.php?proceed=1" class="btn btn-primary splash-proceed">
        Zur Hauptseite &rarr;
      </a>
    </div>

  </div>
</div>

</body>
</html>
