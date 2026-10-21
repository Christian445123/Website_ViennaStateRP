<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/email_verification.php';

header('Referrer-Policy: no-referrer'); // Token steht in der URL → nicht an Dritte weitergeben
header('Cache-Control: no-store');

$msg = '';
$ok  = false;

if (!isset($pdo)) {
    $msg = 'Datenbankfehler. Bitte versuche es später erneut.';
} elseif (isset($_GET['token'])) {
    // Einlösen des Links aus der Mail
    if (!checkRateLimit('verify_email', 20, 600)) {
        $msg = 'Zu viele Versuche. Bitte warte einige Minuten.';
    } else {
        try {
            $ok  = email_verification_confirm($pdo, (string) $_GET['token']);
            $msg = $ok ? 'Deine E-Mail-Adresse ist bestätigt. Du kannst dich jetzt anmelden.'
                       : 'Der Link ist ungültig oder abgelaufen. Fordere unten einen neuen an.';
        } catch (\Throwable $e) {
            error_log('[VerifyEmail] ' . $e->getMessage());
            $msg = 'Bestätigung fehlgeschlagen. Bitte versuche es später erneut.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Neuen Link anfordern – Antwort ist immer gleich (verrät nicht, ob die Adresse existiert)
    $msg = 'Falls ein unbestätigtes Konto mit dieser Adresse existiert, haben wir einen neuen Link gesendet.';
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $msg = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif (!checkRateLimit('verify_resend', 3, 900)) {
        $msg = 'Zu viele Anfragen. Bitte warte einige Minuten.';
    } else {
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && checkRateLimit('verify_resend_mail', 3, 3600, $email)) {
            try {
                $st = $pdo->prepare('SELECT id FROM users WHERE email = ? AND password_hash IS NOT NULL AND email_verified_at IS NULL LIMIT 1');
                $st->execute([$email]);
                if ($row = $st->fetch()) {
                    email_verification_send($pdo, (int) $row['id'], $email);
                }
            } catch (\Throwable $e) {
                error_log('[VerifyEmail] ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>E-Mail bestätigen – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
</head>
<body class="auth-body">
<div class="auth-bg"><div class="auth-bg-overlay"></div></div>
<div class="auth-wrap">
  <a href="index.php" class="auth-logo"><span class="accent">Vienna</span>StateRP</a>
  <div class="auth-card">
    <h1 class="auth-title">E-Mail bestätigen</h1>

    <?php if ($msg !== ''): ?>
      <div class="alert <?= $ok ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:20px"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
    <?php else: ?>
      <p class="auth-sub">Wir haben dir einen Bestätigungslink per E-Mail geschickt. Öffne ihn, um dein Konto freizuschalten.</p>
    <?php endif; ?>

    <?php if ($ok): ?>
      <a href="login.php" class="btn btn-primary btn-block">Zum Login</a>
    <?php else: ?>
      <div class="auth-divider"><span>Link nicht erhalten?</span></div>
      <form class="auth-form" method="POST" action="verify_email.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" />
        <div class="form-group">
          <label for="email">E-Mail Adresse</label>
          <input type="email" id="email" name="email" required maxlength="255" autocomplete="email" />
        </div>
        <button type="submit" class="btn btn-primary btn-block">Neuen Link senden</button>
      </form>
      <p class="auth-switch" style="margin-top:16px"><a href="login.php">Zurück zum Login</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
