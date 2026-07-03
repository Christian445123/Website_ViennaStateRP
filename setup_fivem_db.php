<?php
// ============================================================
//  EINMALIG AUSFÜHREN – danach wird diese Datei gelöscht!
//  Speichert FiveM-DB-Credentials verschlüsselt in fivem_db.enc
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/fivem_db.php';

// Nur für Admins ODER mit Secret-Key
$ok = (is_logged_in() && has_permission('admin'))
   || ($_GET['key'] ?? '') === env('VSRP_SECRET_KEY', '');

if (!$ok) {
    // Fallback: Key aus .env direkt prüfen
    $envKey = getenv('VSRP_SECRET_KEY') ?: ($_ENV['VSRP_SECRET_KEY'] ?? '');
    $ok = ($envKey !== '' && hash_equals($envKey, $_GET['key'] ?? ''));
}

if (!$ok) {
    http_response_code(403);
    die('403 – Kein Zugriff. Admin einloggen oder ?key=VSRP_SECRET_KEY anhängen.');
}

$credentials = [
    'host'    => '162.55.131.14',
    'port'    => '3306',
    'name'    => _vsrp_env_decrypt('ENC:elTKLKCc+g9Ztulq9nnBnoKPyMs3uRx2z4SUddd7/4c='),
    'user'    => _vsrp_env_decrypt('ENC:LSCm1RJfNMCm/cPVCGRKByA4r0AeTTk/8UXR1FQ4Xco='),
    'pass'    => _vsrp_env_decrypt('ENC:k/sSoWIS0vlj605GHF43TQ/Q29pFOtX+ASf2etGFyLU='),
    'charset' => 'utf8mb4',
];

// Verbindung testen
$connectOk  = false;
$dbVersion  = '';
$tables     = [];
$connectErr = '';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4;connect_timeout=8',
        $credentials['host'], $credentials['port'], $credentials['name']);
    $test = new PDO($dsn, $credentials['user'], $credentials['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbVersion  = $test->query("SELECT VERSION()")->fetchColumn();
    $tables     = $test->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $connectOk  = true;
    $test = null;
} catch (\PDOException $e) {
    $connectErr = $e->getMessage();
}

// Verschlüsselt speichern
$saved = false;
if ($connectOk) {
    $saved = fivem_db_save($credentials);
}

// Datei selbst löschen nach Ausführung
$selfDelete = false;
if ($saved && isset($_GET['delete'])) {
    $selfDelete = unlink(__FILE__);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>FiveM DB Setup</title>
  <style>
    body{font-family:monospace;background:#0a0a0f;color:#e2e8f0;padding:40px;font-size:14px;line-height:1.6}
    h1{color:#e8b86d;font-family:sans-serif;font-size:1.4rem}
    .ok{color:#22c55e} .err{color:#ef4444} .info{color:#64748b}
    .box{background:#111118;border:1px solid #1e1e28;border-radius:8px;padding:16px 20px;margin:16px 0}
    .tag{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.78rem;font-weight:700}
    .tag-ok{background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.3)}
    .tag-err{background:rgba(239,68,68,.12);color:#fca5a5;border:1px solid rgba(239,68,68,.3)}
    pre{background:#0a0a0f;padding:8px;border-radius:4px;overflow-x:auto;font-size:.82rem;color:#94a3b8}
  </style>
</head>
<body>
<h1>🔐 FiveM Datenbank Setup</h1>

<div class="box">
  <strong>Verbindungstest:</strong><br>
  <?php if ($connectOk): ?>
  <span class="tag tag-ok">✓ VERBUNDEN</span>
  MariaDB/MySQL <strong><?= htmlspecialchars($dbVersion) ?></strong><br><br>
  <strong><?= count($tables) ?> Tabellen gefunden:</strong><br>
  <pre><?= htmlspecialchars(implode("\n", $tables)) ?></pre>
  <?php else: ?>
  <span class="tag tag-err">✗ FEHLER</span> <?= htmlspecialchars($connectErr) ?>
  <?php endif; ?>
</div>

<div class="box">
  <strong>Verschlüsseltes Speichern:</strong><br>
  <?php if ($saved): ?>
  <span class="tag tag-ok">✓ GESPEICHERT</span>
  Credentials wurden AES-256-GCM verschlüsselt in <code>includes/fivem_db.enc</code> gespeichert.
  <?php elseif ($connectOk): ?>
  <span class="tag tag-err">✗ FEHLER</span> Speichern fehlgeschlagen – VSRP_SECRET_KEY vorhanden?
  <?php else: ?>
  <span class="info">– Übersprungen (Verbindungstest fehlgeschlagen)</span>
  <?php endif; ?>
</div>

<?php if ($saved): ?>
<div class="box">
  <strong class="ok">✓ Nächste Schritte:</strong><br>
  1. <a href="fivem_sync.php" style="color:#e8b86d">fivem_sync.php</a> öffnen und Sync starten<br>
  2. <strong>Diese Datei löschen!</strong><br><br>
  <a href="?delete=1<?= isset($_GET['key']) ? '&key='.urlencode($_GET['key']) : '' ?>"
     onclick="return confirm('setup_fivem_db.php jetzt löschen?')"
     style="background:#ef4444;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700">
    🗑 Diese Datei jetzt löschen
  </a>
</div>
<?php endif; ?>

<?php if ($selfDelete): ?>
<p style="color:#22c55e;font-weight:700">✓ setup_fivem_db.php wurde gelöscht.</p>
<?php elseif (isset($_GET['delete']) && !$selfDelete): ?>
<p style="color:#ef4444">✗ Löschen fehlgeschlagen – bitte manuell löschen!</p>
<?php endif; ?>

</body>
</html>
