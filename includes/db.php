<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Datenbankverbindung
//  Liest Konfiguration aus .env (mit AES-256-CBC Entschlüsselung)
//  Muster übernommen von GamingCommunity24/7
// ============================================================

if (defined('_VSRP_DB_LOADED')) return;
define('_VSRP_DB_LOADED', true);

// ── .env laden ───────────────────────────────────────────────────────────────
(function (): void {
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_readable($envFile)) return;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // ENC: entschlüsseln
        if (substr($val, 0, 4) === 'ENC:') {
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
    // Priorität 2: $_ENV zuerst (gesetzt vom .env-Loader), dann getenv() (PHP-FPM pool.d)
    // Wichtig: putenv() ist auf manchen Hosts deaktiviert – $_ENV ist immer verfügbar
    $envKey = $_ENV['VSRP_SECRET_KEY'] ?? getenv('VSRP_SECRET_KEY');
    if ($envKey !== false && $envKey !== null && $envKey !== '') {
        return hash('sha256', (string) $envKey, true);
    }
    return null;
}

function _vsrp_env_decrypt(string $value): string
{
    if (!function_exists('openssl_decrypt')) {
        error_log('[VSRP] OpenSSL nicht verfügbar – ENC:-Wert kann nicht entschlüsselt werden. PHP-Extension "openssl" aktivieren.');
        return $value;
    }

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
    if ($key === null) {
        throw new \RuntimeException('[VSRP] Kein Verschlüsselungs-Key gefunden (VSRP_SECRET_KEY oder vsrp.key).');
    }

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
// Priorität: db_config.enc (Admin-Panel) > .env > config.php > Defaults
try {
    // Verschlüsselte Config laden falls vorhanden
    $dbCfgFile = __DIR__ . '/db_config.enc';
    $dbCfg     = null;
    if (file_exists($dbCfgFile)) {
        // db_config.php noch nicht geladen? Dann inline entschlüsseln
        if (function_exists('db_config_load')) {
            $dbCfg = db_config_load();
        } else {
            // Minimale Inline-Entschlüsselung
            $raw = file_get_contents($dbCfgFile);
            if ($raw !== false && strlen($raw) > 28) {
                $cfgKey = null;
                $kf = getenv('VSRP_KEY_FILE') ?: (dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/vsrp.key');
                if (is_readable($kf)) {
                    $cfgKey = hash('sha256', trim((string) file_get_contents($kf)), true);
                } elseif (($ek = getenv('VSRP_SECRET_KEY') ?: ($_ENV['VSRP_SECRET_KEY'] ?? '')) !== '') {
                    $cfgKey = hash('sha256', $ek, true);
                }
                if ($cfgKey) {
                    $nonce = substr($raw, 0, 12);
                    $tag   = substr($raw, 12, 16);
                    $json  = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $cfgKey, OPENSSL_RAW_DATA, $nonce, $tag);
                    if ($json !== false) $dbCfg = json_decode($json, true);
                }
            }
        }
    }

    // Fallback 3: config.php Konstanten laden wenn weder db_config.enc noch .env verfügbar
    if ($dbCfg === null && env('DB_USER', '') === '' && !defined('DB_HOST')) {
        $cfgFile = __DIR__ . '/../config.php';
        if (file_exists($cfgFile)) { @include_once $cfgFile; }
    }

    $dbHost    = $dbCfg['host']    ?? env('DB_HOST',    defined('DB_HOST') ? DB_HOST : '127.0.0.1');
    $dbPort    = $dbCfg['port']    ?? env('DB_PORT',    defined('DB_PORT') ? DB_PORT : '3306');
    $dbName    = $dbCfg['name']    ?? env('DB_NAME',    defined('DB_NAME') ? DB_NAME : 'viennastaterp');
    $dbUser    = $dbCfg['user']    ?? env('DB_USER',    defined('DB_USER') ? DB_USER : '');
    $dbPass    = $dbCfg['pass']    ?? env('DB_PASS',    defined('DB_PASS') ? DB_PASS : '');
    $dbCharset = $dbCfg['charset'] ?? env('DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s;connect_timeout=10',
        $dbHost, $dbPort, $dbName, $dbCharset
    );

    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
    // Charset + Strict Mode via Query (PHP 8.5 deprecated MYSQL_ATTR_INIT_COMMAND)
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_ALL_TABLES'");
} catch (PDOException $e) {
    error_log('[VSRP DB] Verbindung fehlgeschlagen: ' . $e->getMessage());
    // $pdo bleibt null – Seiten müssen isset($pdo) prüfen
    $pdo = null;
}

// ── Auto-Migration: fehlende Spalten automatisch hinzufügen ──────────────────
if ($pdo !== null) {
    try {
        // Spalten-Cache (wird von callback.php / process_register.php ebenfalls genutzt)
        $migrateFlag = sys_get_temp_dir() . '/vsrp_migrated_users_v2.flag';
        if (!file_exists($migrateFlag)) {
            $existingCols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);

            // Spalten, die von der Benutzerverwaltung benötigt werden, aber auf
            // älteren Deployments (vor Schema v2.0) noch fehlen können.
            $columnsToAdd = [
                'is_verified' => "ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 1",
                'role'        => "ADD COLUMN `role` ENUM('player','supporter','moderator','admin') NOT NULL DEFAULT 'player'",
                'is_banned'   => "ADD COLUMN `is_banned` TINYINT(1) NOT NULL DEFAULT 0",
                'ban_reason'  => "ADD COLUMN `ban_reason` TEXT NULL",
                'banned_by'   => "ADD COLUMN `banned_by` BIGINT UNSIGNED NULL",
                'banned_at'   => "ADD COLUMN `banned_at` DATETIME NULL",
            ];

            foreach ($columnsToAdd as $col => $ddl) {
                if (!in_array($col, $existingCols, true)) {
                    $pdo->exec("ALTER TABLE `users` {$ddl}");
                    error_log("[VSRP Migration] Spalte `{$col}` erfolgreich zur Tabelle `users` hinzugefügt.");
                }
            }

            // Flag setzen damit die Prüfung nur einmal läuft
            @file_put_contents($migrateFlag, date('Y-m-d H:i:s'));
            // Spalten-Cache invalidieren (falls process_register.php ihn gecacht hat)
            @unlink(sys_get_temp_dir() . '/vsrp_cols_users.json');
        }
    } catch (\Throwable $e) {
        error_log('[VSRP Migration] ' . $e->getMessage());
    }
}

// ── Konstanten aus .env ───────────────────────────────────────────────────────
defined('SITE_NAME')         || define('SITE_NAME',    env('SITE_NAME',   'ViennaStateRP'));
defined('SITE_URL')          || define('SITE_URL',     env('SITE_URL',    'https://viennastaterp.at'));
defined('FIVEM_IP')          || define('FIVEM_IP',     env('FIVEM_IP',    'fivem.viennastaterp.at'));
defined('FIVEM_PORT')        || define('FIVEM_PORT',   env('FIVEM_PORT',  '30120'));
defined('FIVEM_LINK')        || define('FIVEM_LINK',   'fivem://connect/' . FIVEM_IP . ':' . FIVEM_PORT);
defined('DISCORD_INVITE')    || define('DISCORD_INVITE', env('DISCORD_INVITE', 'https://discord.gg/za7wKkg3s8'));

defined('DISCORD_CLIENT_ID')     || define('DISCORD_CLIENT_ID',     env('DISCORD_CLIENT_ID',     '1522589753105584178'));
defined('DISCORD_CLIENT_SECRET') || define('DISCORD_CLIENT_SECRET', env('DISCORD_CLIENT_SECRET', 'XnEX7ZNGquv8eosBupraBTlY8EbYsmuO'));
defined('DISCORD_REDIRECT_URI')  || define('DISCORD_REDIRECT_URI',  env('DISCORD_REDIRECT_URI',  rtrim(SITE_URL, '/') . '/callback.php'));
defined('DISCORD_GUILD_ID')      || define('DISCORD_GUILD_ID',      env('DISCORD_GUILD_ID',      '1520815251376050356'));

// Alias für Kompatibilität
defined('FIVEM_CONNECT_LINK') || define('FIVEM_CONNECT_LINK', FIVEM_LINK);

// Verschluesselungs-Modul laden
require_once __DIR__ . '/encryption.php';