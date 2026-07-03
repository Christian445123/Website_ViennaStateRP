<?php
// ============================================================
//  ViennaStateRP – Datenbankverbindung
//  Liest Konfiguration aus .env (mit AES-256-CBC Entschlüsselung)
//  Muster übernommen von GamingCommunity24/7
// ============================================================
declare(strict_types=1);

if (defined('_VSRP_DB_LOADED')) return;
define('_VSRP_DB_LOADED', true);

// ── .env laden ───────────────────────────────────────────────────────────────
(function (): void {
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_readable($envFile)) return;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // ENC: entschlüsseln
        if (str_starts_with($val, 'ENC:')) {
            $val = _vsrp_env_decrypt($val);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
})();

// ── Verschlüsselungs-Helfer ──────────────────────────────────────────────────

function _vsrp_get_secret_key(): ?string
{
    // Priorität 1: Key-Datei außerhalb des Webroot
    $keyFile = getenv('VSRP_KEY_FILE') ?: (dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/vsrp.key');
    if ($keyFile !== '' && is_readable($keyFile)) {
        return hash('sha256', trim((string) file_get_contents($keyFile)), true);
    }
    // Priorität 2: Umgebungsvariable (PHP-FPM pool.d oder .env)
    $envKey = getenv('VSRP_SECRET_KEY');
    if ($envKey !== false && $envKey !== '') {
        return hash('sha256', $envKey, true);
    }
    return null;
}

function _vsrp_env_decrypt(string $value): string
{
    $raw = base64_decode(substr($value, 4), true);
    if ($raw === false || strlen($raw) <= 16) return $value;

    $key = _vsrp_get_secret_key();
    if ($key === null) {
        error_log('[VSRP] Kein Entschlüsselungs-Key gefunden.');
        return $value;
    }

    $iv         = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $plain      = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    return $plain === false ? $value : $plain;
}

function vsrp_env_encrypt(string $value): string
{
    $key = _vsrp_get_secret_key();
    if ($key === null) die('[VSRP] Kein Verschlüsselungs-Key gefunden.');

    $iv         = random_bytes(16);
    $ciphertext = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    return 'ENC:' . base64_encode($iv . $ciphertext);
}

// ── ENV-Wert holen ────────────────────────────────────────────────────────────
function env(string $key, string $default = ''): string
{
    $val = $_ENV[$key] ?? getenv($key);
    return ($val !== false && $val !== null && $val !== '') ? (string) $val : $default;
}

// ── PDO-Verbindung ────────────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s;connect_timeout=10',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'viennastaterp'),
        env('DB_CHARSET', 'utf8mb4')
    );

    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_ALL_TABLES'",
    ]);
} catch (PDOException $e) {
    error_log('[VSRP DB] Verbindung fehlgeschlagen: ' . $e->getMessage());
    http_response_code(503);
    // Keine DB-Details nach außen
    die('Datenbankfehler. Bitte versuche es später erneut.');
}

// ── Konstanten aus .env ───────────────────────────────────────────────────────
define('SITE_NAME',    env('SITE_NAME',   'ViennaStateRP'));
define('SITE_URL',     env('SITE_URL',    'https://viennastaterp.at'));
define('FIVEM_IP',     env('FIVEM_IP',    'fivem.viennastaterpx.at'));
define('FIVEM_PORT',   env('FIVEM_PORT',  '30120'));
define('FIVEM_LINK',   'fivem://connect/' . FIVEM_IP . ':' . FIVEM_PORT);
define('DISCORD_INVITE', env('DISCORD_INVITE', 'https://discord.gg/za7wKkg3s8'));

define('DISCORD_CLIENT_ID',     env('DISCORD_CLIENT_ID'));
define('DISCORD_CLIENT_SECRET', env('DISCORD_CLIENT_SECRET'));
define('DISCORD_REDIRECT_URI',  env('DISCORD_REDIRECT_URI', SITE_URL . '/callback.php'));
define('DISCORD_GUILD_ID',      env('DISCORD_GUILD_ID'));
