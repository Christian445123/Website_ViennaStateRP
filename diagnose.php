<?php
// ============================================================
//  ViennaStateRP – Diagnose-Tool
//  Aufruf: https://deine-domain.at/diagnose.php?token=DEIN_TOKEN
//  Token setzen: DIAG_TOKEN=... in .env eintragen
// ============================================================
ini_set('display_errors', '1');
error_reporting(E_ALL);

// ── Zugangsschutz ────────────────────────────────────────────
$envPath = __DIR__ . '/.env';
$diagToken = '';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'DIAG_TOKEN=')) {
            $diagToken = trim(substr($line, 11));
            break;
        }
    }
}
// Fallback: IP-Schutz wenn kein Token konfiguriert
if ($diagToken === '') {
    $allowedIps = ['127.0.0.1', '::1'];
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteIp, $allowedIps, true)) {
        http_response_code(403);
        die('Kein DIAG_TOKEN in .env – Zugriff nur von localhost erlaubt. Token in .env setzen: DIAG_TOKEN=geheimes_passwort');
    }
} else {
    $provided = $_GET['token'] ?? '';
    if (!hash_equals($diagToken, $provided)) {
        http_response_code(403);
        die('Ungültiger Token. Aufruf: ?token=DEIN_DIAG_TOKEN');
    }
}

echo '<style>body{font-family:monospace;background:#111;color:#0f0;padding:20px}
.ok{color:#0f0}.err{color:#f44}.warn{color:#fa0}
h2{color:#0af;border-bottom:1px solid #0af;padding-bottom:4px}</style>';

echo '<h2>ViennaStateRP – Diagnose</h2>';

// ── PHP ───────────────────────────────────────────────────────
echo '<h2>PHP</h2>';
$phpOk = version_compare(PHP_VERSION, '8.0.0') >= 0;
echo 'PHP Version: ' . PHP_VERSION . ' ' . ($phpOk ? '<span class="ok">✓</span>' : '<span class="err">✗ BRAUCHT 8.0+</span>') . '<br>';

$exts = ['pdo', 'pdo_mysql', 'curl', 'mbstring', 'json', 'openssl'];
foreach ($exts as $ext) {
    $ok = extension_loaded($ext);
    echo "Extension $ext: " . ($ok ? '<span class="ok">✓</span>' : '<span class="err">✗ FEHLT</span>') . '<br>';
}

// ── .env ──────────────────────────────────────────────────────
echo '<h2>.env Datei</h2>';
echo '.env Pfad: ' . $envPath . '<br>';
echo '.env existiert: ' . (file_exists($envPath) ? '<span class="ok">✓</span>' : '<span class="err">✗ FEHLT – DB-Zugangsdaten nicht konfiguriert!</span>') . '<br>';

$env = [];
if (is_readable($envPath)) {
    echo '.env lesbar: <span class="ok">✓</span><br>';
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
    $keys = ['DB_HOST','DB_PORT','DB_NAME','DB_USER','SITE_URL','DISCORD_CLIENT_ID','DISCORD_REDIRECT_URI'];
    foreach ($keys as $k) {
        $val = $env[$k] ?? null;
        $safe = in_array($k, ['DB_USER','DB_HOST','DB_PORT','DB_NAME','SITE_URL','DISCORD_CLIENT_ID','DISCORD_REDIRECT_URI'], true)
              ? htmlspecialchars((string)$val) : '(versteckt)';
        echo "$k: " . ($val !== null ? '<span class="ok">✓ = ' . $safe . '</span>' : '<span class="err">✗ FEHLT</span>') . '<br>';
    }
    // DB_PASS nur Vorhandensein prüfen
    echo 'DB_PASS: ' . (isset($env['DB_PASS']) && $env['DB_PASS'] !== '' ? '<span class="ok">✓ (gesetzt)</span>' : '<span class="err">✗ FEHLT oder leer</span>') . '<br>';
} else {
    echo '.env lesbar: <span class="err">✗ NICHT LESBAR</span><br>';
}

// ── db_config.enc ─────────────────────────────────────────────
$encFile = __DIR__ . '/includes/db_config.enc';
echo '<h2>Verschlüsselte DB-Config (db_config.enc)</h2>';
echo 'Existiert: ' . (file_exists($encFile) ? '<span class="ok">✓</span>' : '<span class="warn">– nicht vorhanden (wird .env verwendet)</span>') . '<br>';

// ── Datenbankverbindung ───────────────────────────────────────
echo '<h2>Datenbankverbindung</h2>';

// Priorität: db_config.enc > .env > Defaults
$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_NAME'] ?? 'viennastaterp';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

// Falls db_config.enc vorhanden, versuchen zu entschlüsseln
if (file_exists($encFile)) {
    try {
        require_once __DIR__ . '/includes/db_config.php';
        $cfg = db_config_load();
        if ($cfg) {
            $dbHost = $cfg['host'] ?? $dbHost;
            $dbPort = $cfg['port'] ?? $dbPort;
            $dbName = $cfg['name'] ?? $dbName;
            $dbUser = $cfg['user'] ?? $dbUser;
            $dbPass = $cfg['pass'] ?? $dbPass;
            echo 'db_config.enc: <span class="ok">✓ entschlüsselt</span><br>';
        } else {
            echo 'db_config.enc: <span class="warn">⚠ Entschlüsselung fehlgeschlagen – kein Key?</span><br>';
        }
    } catch (\Throwable $e) {
        echo 'db_config.enc: <span class="err">✗ Fehler: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
    }
}

echo 'Verbindungsversuch: host=' . htmlspecialchars($dbHost) . ' db=' . htmlspecialchars($dbName) . ' user=' . htmlspecialchars($dbUser) . '<br>';

$testPdo = null;
try {
    $testPdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4;connect_timeout=5",
        $dbUser, $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    echo 'Verbindung: <span class="ok">✓ ERFOLGREICH</span><br>';
} catch (\PDOException $e) {
    echo 'Verbindung: <span class="err">✗ FEHLER: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
    echo '<br><span class="warn">→ DB-Zugangsdaten in .env oder Admin-Panel prüfen!</span><br>';
}

// ── Tabellen ──────────────────────────────────────────────────
echo '<h2>Datenbank-Tabellen</h2>';
$required = ['users','discord_tokens','login_log','rate_limits','activity_logs'];
if ($testPdo) {
    try {
        $existing = $testPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($required as $t) {
            $ok = in_array($t, $existing, true);
            echo "$t: " . ($ok ? '<span class="ok">✓</span>' : '<span class="err">✗ FEHLT – schema.sql importieren!</span>') . '<br>';
        }
        $missing = array_diff($required, $existing);
        if ($missing) {
            echo '<br><span class="err">✗ Fehlende Tabellen! schema.sql in der Datenbank ausführen.</span><br>';
        }
    } catch (\Throwable $e) {
        echo '<span class="err">Tabellen-Check fehlgeschlagen: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
    }
} else {
    echo '<span class="warn">Kein DB-Zugriff – Tabellen können nicht geprüft werden.</span><br>';
}

// ── Includes laden ────────────────────────────────────────────
echo '<h2>Includes laden</h2>';
$files = ['includes/db.php','includes/session_config.php','includes/discord_config.php',
          'includes/permissions.php','includes/logger.php','includes/rate_limit.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    ob_start();
    set_error_handler(fn($no,$str) => true);
    $ok = true; $msg = '';
    try {
        require_once $path;
    } catch (\Throwable $e) {
        $ok = false; $msg = $e->getMessage();
    }
    restore_error_handler();
    ob_end_clean();
    echo "$f: " . ($ok ? '<span class="ok">✓</span>' : '<span class="err">✗ ' . htmlspecialchars($msg) . '</span>') . '<br>';
}

// ── Konstanten ────────────────────────────────────────────────
echo '<h2>Konstanten</h2>';
foreach (['SITE_URL','DISCORD_CLIENT_ID','DISCORD_REDIRECT_URI','DISCORD_GUILD_ID'] as $c) {
    echo "$c: " . (defined($c) ? '<span class="ok">✓ = ' . htmlspecialchars(constant($c)) . '</span>' : '<span class="err">✗ NICHT DEFINIERT</span>') . '<br>';
}

// ── Session ───────────────────────────────────────────────────
echo '<h2>Session</h2>';
echo 'session_status: ' . session_status() . ' (' . (session_status() === PHP_SESSION_ACTIVE ? '<span class="ok">aktiv</span>' : '<span class="warn">inaktiv</span>') . ')<br>';
echo 'Session ID: ' . (session_id() ?: '<span class="warn">keine</span>') . '<br>';
echo 'discord_user in Session: ' . (isset($_SESSION['discord_user']) ? '<span class="ok">✓ eingeloggt als ' . htmlspecialchars($_SESSION['discord_user']['username'] ?? '?') . '</span>' : '<span class="warn">nicht eingeloggt</span>') . '<br>';

echo '<br><p style="color:#f44;font-size:14px">⚠ Diese Seite enthält sensible Systeminformationen. Nach dem Lesen DIAG_TOKEN aus .env entfernen oder diese Datei löschen!</p>';
