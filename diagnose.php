<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo '<pre style="font-family:monospace;font-size:13px;background:#111;color:#0f0;padding:20px">';
echo "=== ViennaStateRP Diagnose v2 ===\n\n";

echo "PHP Version: " . PHP_VERSION . "\n\n";

// Jeden Include einzeln laden und Fehler abfangen
$includes = [
    'includes/db.php',
    'includes/session_config.php',
    'includes/discord_config.php',
    'includes/permissions.php',
    'includes/logger.php',
    'includes/rate_limit.php',
];

foreach ($includes as $file) {
    $path = __DIR__ . '/' . $file;
    echo "Lade $file ... ";
    ob_start();
    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($file) {
        echo "\n  ✗ ERROR $errno in $file Zeile $errline: $errstr\n";
        return true;
    });
    try {
        require_once $path;
        restore_error_handler();
        $buf = ob_get_clean();
        echo "✓" . ($buf ? "\n  Output: $buf" : "") . "\n";
    } catch (Throwable $e) {
        restore_error_handler();
        ob_get_clean();
        echo "\n  ✗ FATAL: " . $e->getMessage() . " (Zeile " . $e->getLine() . ")\n";
    }
}

// Konstanten prüfen
echo "\nKonstanten:\n";
$consts = ['SITE_URL','FIVEM_IP','FIVEM_LINK','DISCORD_CLIENT_ID','DISCORD_REDIRECT_URI','DISCORD_INVITE'];
foreach ($consts as $c) {
    echo "  $c: " . (defined($c) ? "✓ = " . constant($c) : "✗ NICHT DEFINIERT") . "\n";
}

// Funktionen prüfen
echo "\nFunktionen:\n";
$funcs = ['csrf_token','verify_csrf','is_logged_in','require_login','current_user','has_permission','discord_auth_url','discord_avatar','log_event','checkRateLimit'];
foreach ($funcs as $f) {
    echo "  $f(): " . (function_exists($f) ? "✓" : "✗ FEHLT") . "\n";
}

echo "\n=== Ende ===\n";
echo '</pre>';
echo '<p style="color:red"><strong>⚠ SOFORT DANACH LÖSCHEN!</strong></p>';

// Sicherheit: Nur von bekannten IPs abrufbar
// Trage deine eigene IP ein oder lösche die Prüfung für einmaligen Aufruf
// $allowedIp = '1.2.3.4';
// if (($_SERVER['REMOTE_ADDR'] ?? '') !== $allowedIp) { http_response_code(403); die('Forbidden'); }

echo '<pre style="font-family:monospace;font-size:13px;background:#111;color:#0f0;padding:20px">';
echo "=== ViennaStateRP Diagnose ===\n\n";

// PHP Version
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP >= 8.0: " . (version_compare(PHP_VERSION, '8.0.0') >= 0 ? "✓ OK" : "✗ ZU ALT – braucht 8.0+") . "\n\n";

// Extensions
$needed = ['pdo', 'pdo_mysql', 'curl', 'mbstring', 'json', 'openssl'];
echo "Extensions:\n";
foreach ($needed as $ext) {
    echo "  $ext: " . (extension_loaded($ext) ? "✓" : "✗ FEHLT") . "\n";
}

// .env
echo "\n.env Datei:\n";
$envPath = __DIR__ . '/.env';
echo "  Pfad: $envPath\n";
echo "  Existiert: " . (file_exists($envPath) ? "✓" : "✗ FEHLT!") . "\n";
echo "  Lesbar: "    . (is_readable($envPath) ? "✓" : "✗ NICHT LESBAR") . "\n";

// includes/
echo "\nincludes/ Dateien:\n";
$files = ['db.php','session_config.php','discord_config.php','logger.php','rate_limit.php','permissions.php'];
foreach ($files as $f) {
    $p = __DIR__ . "/includes/$f";
    echo "  includes/$f: " . (file_exists($p) ? "✓" : "✗ FEHLT") . "\n";
}

// DB-Verbindung testen
echo "\nDatenbank:\n";
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    $env = [];
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line,'=') === false) continue;
        [$k,$v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $name = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASS'] ?? '';
    echo "  Host: $host\n";
    echo "  Datenbank: $name\n";
    echo "  User: $user\n";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
        echo "  Verbindung: ✓ OK\n";
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "  Tabellen: " . (count($tables) ? implode(', ', $tables) : "KEINE – schema.sql noch nicht importiert!") . "\n";
    } catch (PDOException $e) {
        echo "  Verbindung: ✗ FEHLER: " . $e->getMessage() . "\n";
    }
} else {
    echo "  .env nicht lesbar – kann DB nicht testen\n";
}

echo "\n=== Ende ===\n";
echo '</pre>';
echo '<p style="color:red;font-family:sans-serif"><strong>⚠ Diese Datei nach dem Lesen sofort löschen!</strong></p>';
