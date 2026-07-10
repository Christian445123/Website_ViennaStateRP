<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/discord_config.php';

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

$hasBanner = file_exists(__DIR__ . '/assets/img/beta-banner.jpg');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Beta-Start &ndash; ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Rajdhani', system-ui, sans-serif;
      background: #0a0a0f;
      color: #e2e8f0;
      line-height: 1.65;
      min-height: 100vh;
      overflow-x: hidden;
    }
    .bg {
      position: fixed; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse 90% 70% at 15% 10%, rgba(124,58,237,.22) 0%, transparent 60%),
        radial-gradient(ellipse 70% 50% at 85% 90%, rgba(232,184,109,.14) 0%, transparent 55%),
        #0a0a0f;
    }
    .bg-grid {
      position: fixed; inset: 0; z-index: 0;
      background-image:
        linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
      background-size: 48px 48px;
    }
    .page {
      position: relative; z-index: 1; min-height: 100vh;
      display: flex; align-items: flex-start; justify-content: center;
      padding: 48px 16px 64px;
    }
    .card {
      width: 100%; max-width: 720px;
      background: rgba(17,17,24,.95);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 20px; overflow: hidden;
      box-shadow: 0 0 0 1px rgba(124,58,237,.2), 0 32px 80px rgba(0,0,0,.7);
      animation: cardIn .5s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(28px) scale(.97); }
      to   { opacity: 1; transform: none; }
    }
    .card-banner { position: relative; line-height: 0; }
    .card-banner img { width: 100%; max-height: 300px; object-fit: cover; object-position: center 20%; display: block; }
    .card-banner-grad { position: absolute; bottom: 0; left: 0; right: 0; height: 100px; background: linear-gradient(to bottom, transparent, rgba(17,17,24,.97)); }
    .card-hero {
      padding: 48px 40px 8px; text-align: center;
      background: radial-gradient(ellipse 100% 120% at 50% -20%, rgba(124,58,237,.18) 0%, transparent 65%);
      border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .hero-logo { font-family: 'Orbitron', sans-serif; font-size: 2.4rem; font-weight: 900; line-height: 1; margin-bottom: 12px; }
    .hero-logo span { color: #e8b86d; }
    .hero-tag {
      display: inline-block; font-family: 'Orbitron', sans-serif; font-size: .65rem; font-weight: 700;
      letter-spacing: .22em; text-transform: uppercase; color: #e8b86d;
      background: rgba(232,184,109,.12); border: 1px solid rgba(232,184,109,.3);
      padding: 5px 16px 4px; border-radius: 999px; margin-bottom: 16px;
    }
    .card-header { padding: 28px 40px 0; text-align: center; }
    .has-banner .card-header { padding-top: 0; }
    .header-badge { display: inline-block; font-size: .68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px; }
    .card-title {
      font-family: 'Orbitron', sans-serif; font-size: clamp(1.3rem, 3.5vw, 1.9rem);
      font-weight: 900; line-height: 1.2;
      background: linear-gradient(100deg, #e8b86d 10%, #c084fc 70%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
      margin-bottom: 4px;
    }
    .card-subtitle { color: #64748b; font-size: .92rem; margin-bottom: 28px; }
    .card-body { padding: 8px 40px 32px; display: flex; flex-direction: column; gap: 14px; }
    .card-body p { color: #cbd5e1; font-size: .95rem; }
    .card-body strong { color: #e8b86d; font-weight: 700; }
    hr.div { border: none; border-top: 1px solid rgba(255,255,255,.07); margin: 4px 0; }
    .note { background: rgba(124,58,237,.12); border-left: 3px solid #7c3aed; padding: 11px 16px; border-radius: 0 8px 8px 0; color: #c4b5fd; font-size: .9rem; }
    .note strong { color: #c4b5fd; }
    .blist { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .blist li { padding-left: 20px; position: relative; color: #94a3b8; font-size: .9rem; }
    .blist li::before { content: '--'; position: absolute; left: 0; color: #e8b86d; }
    .info-box { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 14px 16px; font-size: .88rem; color: #94a3b8; }
    .info-box strong { color: #e2e8f0; }
    .info-box a { color: #5865f2; text-decoration: underline; }
    .info-box a:hover { color: #7289da; }
    .goal-box { background: rgba(34,197,94,.06); border: 1px solid rgba(34,197,94,.18); border-radius: 10px; padding: 14px 16px; font-size: .88rem; color: #86efac; }
    .goal-box strong { color: #22c55e; }
    .time-box { display: flex; align-items: center; justify-content: center; gap: 10px; background: rgba(232,184,109,.08); border: 1px solid rgba(232,184,109,.25); border-radius: 10px; padding: 16px; text-align: center; font-size: 1.05rem; color: #e2e8f0; }
    .time-box strong { color: #e8b86d; font-family: 'Orbitron', sans-serif; font-size: 1rem; font-weight: 700; }
    .signoff { color: #475569; font-size: .85rem; text-align: right; font-style: italic; }
    .card-footer { display: flex; gap: 12px; padding: 20px 40px 36px; border-top: 1px solid rgba(255,255,255,.07); }
    .btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: 'Rajdhani', sans-serif; font-size: .97rem; font-weight: 700; letter-spacing: .04em; padding: 13px 20px; border-radius: 10px; text-decoration: none; border: none; cursor: pointer; transition: opacity .18s, transform .15s; white-space: nowrap; }
    .btn:hover { opacity: .85; transform: translateY(-2px); }
    .btn-main { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); color: #fff; box-shadow: 0 4px 20px rgba(124,58,237,.35); }
    .btn-disc { background: #5865f2; color: #fff; box-shadow: 0 4px 20px rgba(88,101,242,.3); }
    @media (max-width: 600px) {
      .page { padding: 24px 12px 48px; }
      .card-hero { padding: 36px 22px 8px; }
      .card-header { padding: 20px 22px 0; }
      .card-body { padding: 8px 22px 28px; }
      .card-footer { padding: 16px 22px 28px; flex-direction: column; }
      .hero-logo { font-size: 1.8rem; }
    }
  </style>
</head>
<body>
<div class="bg"></div>
<div class="bg-grid"></div>
<div class="page">
  <div class="card <?php echo $hasBanner ? 'has-banner' : ''; ?>">

    <?php if ($hasBanner): ?>
    <div class="card-banner">
      <img src="assets/img/beta-banner.jpg" alt="ViennaStateRP Beta-Start" />
      <div class="card-banner-grad"></div>
    </div>
    <?php else: ?>
    <div class="card-hero">
      <div class="hero-tag">&#128226; Beta-Phase gestartet</div>
      <div class="hero-logo"><span>Vienna</span>StateRP</div>
    </div>
    <?php endif; ?>

    <div class="card-header">
      <div class="header-badge">&#128226; Offizielle Ank&uuml;ndigung</div>
      <h1 class="card-title">ViennaStateRP &ndash; Beta-Start!</h1>
      <p class="card-subtitle">Heute um 19:00 Uhr geht es los!</p>
    </div>

    <div class="card-body">

      <p>Liebe Community,<br>
      heute ist es endlich soweit: <strong>ViennaStateRP &ouml;ffnet um 19:00 Uhr offiziell die Beta-Phase!</strong></p>

      <p class="note">Anmerkung: Es wird f&uuml;r alle <strong>Verifizierten</strong> der Server freigeschaltet!</p>

      <p>Wir haben in den letzten Wochen intensiv entwickelt, getestet und optimiert &ndash;
      jetzt seid ihr dran, die Stadt mit Leben zu f&uuml;llen.</p>

      <p>Bitte denkt daran, dass es sich um eine <strong>Beta</strong> handelt. Das bedeutet:</p>
      <ul class="blist">
        <li>Es k&ouml;nnen Bugs, Fehler oder unerwartete Probleme auftreten.</li>
        <li>Manche Systeme sind noch nicht final.</li>
        <li>Performance-Optimierungen laufen weiterhin im Hintergrund.</li>
      </ul>

      <hr class="div" />

      <div class="info-box">
        <strong>&#128296; Probleme oder Bugs?</strong><br>
        Meldet euch &uuml;ber ein
        <a href="https://discord.com/channels/1520815251376050356/1520863619485536489" target="_blank" rel="noopener">Bugreport Ticket</a>
        oder den Channel
        <a href="https://discord.com/channels/1520815251376050356/1525093395771687054" target="_blank" rel="noopener">#bug-report</a>.
        Je genauer eure Beschreibung, desto schneller k&ouml;nnen wir reagieren.
      </div>

      <div class="goal-box">
        &#127919; <strong>Ziel der Beta:</strong><br>
        Gemeinsam mit euch wollen wir Fehler finden, Systeme verbessern und
        ViennaStateRP f&uuml;r den offiziellen Release stabil und sauber vorbereiten.
      </div>

      <div class="time-box">
        &#128343;&ensp;Start:&ensp;<strong>Heute 10.07.2026, 19:00 Uhr</strong>
      </div>

      <p>Wir freuen uns auf euch &ndash; und auf die ersten RP-Momente in Wien!<br>
      Danke f&uuml;r eure Unterst&uuml;tzung und viel Spa&szlig; beim Spielen!</p>

      <p class="signoff">Euer VSRP I Team</p>

    </div>

    <div class="card-footer">
      <a href="https://discord.gg/c33S5eNf?event=1525094898347540520" target="_blank" rel="noopener" class="btn btn-disc">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" width="18" height="14">
          <path fill="#fff" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/>
        </svg>
        Discord Event
      </a>
      <a href="beta.php?proceed=1" class="btn btn-main">
        Zur Hauptseite &rarr;
      </a>
    </div>

  </div>
</div>
</body>
</html>