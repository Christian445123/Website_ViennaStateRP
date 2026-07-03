<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – FiveM Datenbank-Konfiguration
//  Speichert Remote-DB-Credentials verschlüsselt
//  Datei: fivem_db.enc
// ============================================================

if (defined('_VSRP_FIVEMDB_LOADED')) return;
define('_VSRP_FIVEMDB_LOADED', true);

define('FIVEM_DB_CONFIG_FILE', __DIR__ . '/fivem_db.enc');
define('FIVEM_DB_LOCAL_NAME',  'server_fivem'); // Lokale Zieldatenbank

function _fivem_db_key(): ?string
{
    static $k = null;
    if ($k !== null) return $k;
    $kf = getenv('VSRP_KEY_FILE') ?: (dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/vsrp.key');
    if (is_readable($kf)) { $k = hash('sha256', trim((string) file_get_contents($kf)), true); return $k; }
    $ek = getenv('VSRP_SECRET_KEY') ?: ($_ENV['VSRP_SECRET_KEY'] ?? '');
    if ($ek !== '') { $k = hash('sha256', $ek, true); return $k; }
    return null;
}

function fivem_db_load(): ?array
{
    if (!file_exists(FIVEM_DB_CONFIG_FILE)) return null;
    $raw = file_get_contents(FIVEM_DB_CONFIG_FILE);
    if ($raw === false || strlen($raw) < 29) return null;
    $key = _fivem_db_key();
    if (!$key) return null;
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($plain === false) return null;
    return json_decode($plain, true) ?: null;
}

function fivem_db_save(array $data): bool
{
    $key = _fivem_db_key();
    if (!$key) return false;
    $allowed = ['host','port','name','user','pass','charset','updated_at'];
    $clean   = array_intersect_key($data, array_flip($allowed));
    $clean['updated_at'] = date('Y-m-d H:i:s');
    $nonce = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt(json_encode($clean), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) return false;
    return file_put_contents(FIVEM_DB_CONFIG_FILE, $nonce . $tag . $cipher, LOCK_EX) !== false;
}

function fivem_db_connect(): ?PDO
{
    $cfg = fivem_db_load();
    if (!$cfg) return null;
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s;connect_timeout=10',
            $cfg['host'], $cfg['port'] ?? '3306', $cfg['name'], $cfg['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Explizit read-only – verhindert versehentliche Schreiboperationen
        // auf der FiveM-DB (User hat ohnehin nur SELECT-Rechte)
        $pdo->exec("SET SESSION TRANSACTION READ ONLY");
        $pdo->exec("SET SESSION sql_log_bin = 0"); // Kein Binary-Log
        return $pdo;
    } catch (\PDOException $e) {
        error_log('[FiveM-DB] Verbindung fehlgeschlagen: ' . $e->getMessage());
        return null;
    }
}
