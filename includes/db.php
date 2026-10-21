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
/**
 * Sucht die .env bevorzugt AUSSERHALB des Webroots (nicht per HTTP abrufbar).
 * Reihenfolge: VSRP_ENV_FILE (Umgebungsvariable / PHP-FPM-Pool) → Elternordner des Webroots
 * → Elternordner des Projektordners → (veraltet) im Webroot selbst.
 * Eine .env im Webroot wird nur noch als Notlösung geladen und im Log angemahnt.
 */
function vsrp_env_file(): ?string
{
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''
        ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : null;

    $candidates = array_filter([
        getenv('VSRP_ENV_FILE') ?: null,
        $docRoot ? dirname($docRoot) . '/.env' : null,
        dirname(__DIR__, 2) . '/.env',
    ]);
    foreach ($candidates as $c) {
        if (is_readable($c)) return $c;
    }
    $legacy = dirname(__DIR__) . '/.env';
    if (is_readable($legacy)) {
        error_log('[VSRP] WARNUNG: .env liegt im Webroot. Bitte in den übergeordneten Ordner verschieben.');
        return $legacy;
    }
    return null;
}

(function (): void {
    $envFile = vsrp_env_file();
    if ($envFile === null) return;

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

// ── Client-IP ─────────────────────────────────────────────────────────────────
/** Prüft ob $ip in einem der Einträge (IP oder CIDR) liegt */
function _vsrp_ip_in_list(string $ip, array $list): bool
{
    $ipBin = @inet_pton($ip);
    if ($ipBin === false) return false;
    foreach ($list as $entry) {
        $entry = trim($entry);
        if ($entry === '') continue;
        [$net, $bits] = array_pad(explode('/', $entry, 2), 2, null);
        $netBin = @inet_pton($net);
        if ($netBin === false || strlen($netBin) !== strlen($ipBin)) continue;
        $bits = $bits === null ? strlen($netBin) * 8 : max(0, min(strlen($netBin) * 8, (int) $bits));
        $bytes = intdiv($bits, 8);
        $rest  = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) continue;
        if ($rest > 0) {
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($ipBin[$bytes]) & $mask) !== (ord($netBin[$bytes]) & $mask)) continue;
        }
        return true;
    }
    return false;
}

/**
 * Ermittelt die echte Client-IP.
 * X-Forwarded-For / CF-Connecting-IP werden NUR ausgewertet, wenn die Anfrage von einem
 * vertrauenswürdigen Proxy kommt (Loopback oder TRUSTED_PROXIES in .env, kommagetrennt, IP/CIDR).
 * Sonst könnte jeder Angreifer per Header Rate-Limits umgehen und Logs fälschen.
 */
function client_ip(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $trusted = array_merge(['127.0.0.1/8', '::1'], explode(',', env('TRUSTED_PROXIES', '')));

    if (_vsrp_ip_in_list($remote, $trusted)) {
        $candidates = [];
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Rechtester Eintrag, der nicht selbst ein vertrauenswürdiger Proxy ist
            foreach (array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $hop) {
                $hop = trim($hop);
                if (!_vsrp_ip_in_list($hop, $trusted)) { $candidates[] = $hop; break; }
            }
        }
        foreach ($candidates as $c) {
            $c = trim((string) $c);
            if (filter_var($c, FILTER_VALIDATE_IP)) return $cached = $c;
        }
    }
    return $cached = (filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0');
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

// ── Auto-Migration: erweitertes Rollensystem + Rollenverwaltung ─────────────
if ($pdo !== null) {
    try {
        $rolesFlag = sys_get_temp_dir() . '/vsrp_migrated_roles_v4.flag';
        if (!file_exists($rolesFlag)) {
            // ENUM der `role`-Spalte um alle Team-Ränge erweitern (bestehende Werte bleiben gültig)
            $pdo->exec("
                ALTER TABLE `users`
                MODIFY COLUMN `role` ENUM(
                    'player','test_supporter','supporter','moderator','test_admin','admin',
                    'test_developer','developer','stv_teamleitung','teamleitung',
                    'stv_projektleitung','projektleitung','owner','websitenmanager'
                ) NOT NULL DEFAULT 'player'
            ");

            // Tabelle für die über die Website konfigurierbaren Rollen-Berechtigungen
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `role_permissions` (
                  `role`    VARCHAR(32) NOT NULL,
                  `gate`    VARCHAR(32) NOT NULL,
                  `enabled` TINYINT(1)  NOT NULL DEFAULT 0,
                  PRIMARY KEY (`role`, `gate`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Kein Seeding nötig: solange für eine Rolle+Funktion nichts explizit in
            // `role_permissions` steht, berechnet has_capability() den Ist-Zustand live
            // aus der bisherigen Rang-Hierarchie (siehe includes/permissions.php).

            // Zuordnung Discord-Rolle → Website-Rolle für den automatischen Rollen-Sync beim Login
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `discord_role_map` (
                  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `discord_role_id` VARCHAR(32)  NOT NULL,
                  `website_role`    VARCHAR(32)  NOT NULL,
                  `label`           VARCHAR(100) NOT NULL DEFAULT '',
                  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uniq_discord_role` (`discord_role_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            @file_put_contents($rolesFlag, date('Y-m-d H:i:s'));
        }
    } catch (\Throwable $e) {
        error_log('[VSRP Migration] Rollensystem: ' . $e->getMessage());
    }

    // E-Mail-Bestätigung für Passwort-Accounts (Spalte + Token-Tabelle)
    try {
        $mailFlag = sys_get_temp_dir() . '/vsrp_migrated_emailverify_v1.flag';
        if (!file_exists($mailFlag)) {
            $hasMailCol = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'email_verified_at'")->fetch();
            if (!$hasMailCol) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `email_verified_at` DATETIME NULL");
                // Bestehende Passwort-Accounts gelten als bestätigt (sonst wären sie ausgesperrt)
                $pdo->exec("UPDATE `users` SET `email_verified_at` = NOW() WHERE `password_hash` IS NOT NULL");
            }
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `email_verifications` (
                  `token_hash` CHAR(64)        NOT NULL,
                  `user_id`    BIGINT UNSIGNED NOT NULL,
                  `expires_at` DATETIME        NOT NULL,
                  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`token_hash`),
                  KEY `idx_user` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            @unlink(sys_get_temp_dir() . '/vsrp_cols_users.json');
            @file_put_contents($mailFlag, date('Y-m-d H:i:s'));
        }
    } catch (\Throwable $e) {
        error_log('[VSRP Migration] E-Mail-Bestätigung: ' . $e->getMessage());
    }

    // Einmalig: bisherige Admins auf Websitenmanager (Vollzugriff) hochstufen
    try {
        $promoteFlag = sys_get_temp_dir() . '/vsrp_promoted_admins_v1.flag';
        if (!file_exists($promoteFlag)) {
            $affected = $pdo->exec("UPDATE `users` SET `role` = 'websitenmanager' WHERE `role` = 'admin'");
            error_log("[VSRP Migration] {$affected} Admin(s) zu Websitenmanager hochgestuft.");
            @file_put_contents($promoteFlag, date('Y-m-d H:i:s'));
        }
    } catch (\Throwable $e) {
        error_log('[VSRP Migration] Admin-Hochstufung: ' . $e->getMessage());
    }
}

// ── Konstanten aus .env ───────────────────────────────────────────────────────
defined('SITE_NAME')         || define('SITE_NAME',    env('SITE_NAME',   'ViennaStateRP'));
defined('SITE_URL')          || define('SITE_URL',     env('SITE_URL',    'https://viennastaterp.at'));
defined('FIVEM_IP')          || define('FIVEM_IP',     env('FIVEM_IP',    'fivem.viennastaterp.at'));
defined('FIVEM_PORT')        || define('FIVEM_PORT',   env('FIVEM_PORT',  '30120'));
defined('FIVEM_LINK')        || define('FIVEM_LINK',   'fivem://connect/' . FIVEM_IP . ':' . FIVEM_PORT);
defined('DISCORD_INVITE')    || define('DISCORD_INVITE', env('DISCORD_INVITE', 'https://discord.gg/za7wKkg3s8'));

// ── Dev-Server (nur Online/Offline-Anzeige, kein FiveM-API-Zugriff) ──────────
defined('DEV_SERVER_IP')     || define('DEV_SERVER_IP',   env('DEV_SERVER_IP',   '148.251.13.14'));
defined('DEV_SERVER_PORT')   || define('DEV_SERVER_PORT', env('DEV_SERVER_PORT', '30120'));

defined('DISCORD_CLIENT_ID')     || define('DISCORD_CLIENT_ID',     env('DISCORD_CLIENT_ID',     '1522589753105584178'));
defined('DISCORD_CLIENT_SECRET') || define('DISCORD_CLIENT_SECRET', env('DISCORD_CLIENT_SECRET', 'XnEX7ZNGquv8eosBupraBTlY8EbYsmuO'));
defined('DISCORD_REDIRECT_URI')  || define('DISCORD_REDIRECT_URI',  env('DISCORD_REDIRECT_URI',  rtrim(SITE_URL, '/') . '/callback.php'));
defined('DISCORD_GUILD_ID')      || define('DISCORD_GUILD_ID',      env('DISCORD_GUILD_ID',      '1520815251376050356'));

// Alias für Kompatibilität
defined('FIVEM_CONNECT_LINK') || define('FIVEM_CONNECT_LINK', FIVEM_LINK);

// Verschluesselungs-Modul laden
require_once __DIR__ . '/encryption.php';

// ── Selbstauslösender Cron: Discord-Rollen-Sync alle 5 Stunden ──────────────
// Kein Server-Cronjob nötig: ganz normaler Website-Traffic prüft bei jedem
// Seitenaufruf (sehr günstig – nur ein Datei-Zeitstempel), ob der letzte Lauf
// über 5 Stunden her ist, und stößt den Sync dann im Hintergrund an.
// api/discord_role_sync_cron.php erledigt die eigentliche Arbeit inkl. Discord-Bericht.
if ($pdo !== null) {
    try {
        $cronSecret = env('CRON_SECRET', '');
        if ($cronSecret !== '') {
            $lastRunFile = sys_get_temp_dir() . '/vsrp_discord_role_sync_last_run.flag';
            $intervalSec = 5 * 3600;
            if (!file_exists($lastRunFile) || (time() - filemtime($lastRunFile)) >= $intervalSec) {
                // Zuerst "beanspruchen" (Zeitstempel jetzt setzen), damit nicht mehrere
                // gleichzeitige Requests denselben Lauf parallel auslösen.
                @file_put_contents($lastRunFile, (string) time());

                $ch = curl_init(rtrim(SITE_URL, '/') . '/api/discord_role_sync_cron.php?secret=' . urlencode($cronSecret));
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER    => true,
                    CURLOPT_TIMEOUT_MS        => 500,  // Fire-and-forget – wir warten nicht auf das Ergebnis
                    CURLOPT_CONNECTTIMEOUT_MS => 500,
                    CURLOPT_NOSIGNAL          => true, // nötig für Sub-Sekunden-Timeouts
                    CURLOPT_SSL_VERIFYPEER    => true,
                ]);
                @curl_exec($ch);
                curl_close($ch);
            }
        }
    } catch (\Throwable $e) {
        error_log('[VSRP Cron-Trigger] ' . $e->getMessage());
    }
}