<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Verschlüsselte DB-Konfiguration
//
//  Speichert MariaDB-Zugangsdaten AES-256-GCM verschlüsselt.
//  Datei: db_config.enc (nicht öffentlich zugänglich via .htaccess)
//
//  Priorität: db_config.enc > .env > Hardcoded-Defaults
// ============================================================

if (defined('_VSRP_DB_CONFIG_LOADED')) return;
define('_VSRP_DB_CONFIG_LOADED', true);

define('DB_CONFIG_FILE', __DIR__ . '/db_config.enc');

/**
 * Liefert den AES-Schlüssel aus der Umgebung.
 * Gleiche Quelle wie encryption.php / db.php
 */
function _db_cfg_key(): ?string
{
    static $k = null;
    if ($k !== null) return $k;

    $keyFile = getenv('VSRP_KEY_FILE') ?: (dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/vsrp.key');
    if ($keyFile !== '' && is_readable($keyFile)) {
        $raw = trim((string) file_get_contents($keyFile));
        if ($raw !== '') { $k = hash('sha256', $raw, true); return $k; }
    }

    $envKey = getenv('VSRP_SECRET_KEY') ?: ($_ENV['VSRP_SECRET_KEY'] ?? '');
    if ($envKey !== '') { $k = hash('sha256', $envKey, true); return $k; }

    return null;
}

/**
 * Liest die verschlüsselte DB-Config.
 * Gibt assoziatives Array zurück oder null wenn nicht vorhanden.
 */
function db_config_load(): ?array
{
    if (!file_exists(DB_CONFIG_FILE)) return null;

    $raw = file_get_contents(DB_CONFIG_FILE);
    if ($raw === false || strlen($raw) < 29) return null; // 12 nonce + 16 tag + 1 min

    $key = _db_cfg_key();
    if ($key === null) {
        error_log('[DBConfig] Kein Verschlüsselungs-Key gefunden');
        return null;
    }

    $nonce  = substr($raw, 0, 12);
    $tag    = substr($raw, 12, 16);
    $cipher = substr($raw, 28);

    $json = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($json === false) {
        error_log('[DBConfig] Entschlüsselung fehlgeschlagen');
        return null;
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Speichert DB-Credentials AES-256-GCM verschlüsselt.
 */
function db_config_save(array $data): bool
{
    $key = _db_cfg_key();
    if ($key === null) {
        error_log('[DBConfig] Kein Verschlüsselungs-Key – Speichern nicht möglich');
        return false;
    }

    // Nur erlaubte Keys
    $allowed = ['host', 'port', 'name', 'user', 'pass', 'charset', 'updated_at', 'updated_by'];
    $clean   = array_intersect_key($data, array_flip($allowed));
    $clean['updated_at'] = date('Y-m-d H:i:s');

    $json  = json_encode($clean, JSON_UNESCAPED_UNICODE);
    $nonce = random_bytes(12);
    $tag   = '';

    $cipher = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) return false;

    $written = file_put_contents(DB_CONFIG_FILE, $nonce . $tag . $cipher, LOCK_EX);
    return $written !== false;
}

/**
 * Gibt einen Maskierten Wert zurück (für Anzeige im Frontend).
 */
function db_config_mask(string $value, int $show = 3): string
{
    if (strlen($value) <= $show * 2) return str_repeat('*', strlen($value));
    return substr($value, 0, $show) . str_repeat('*', max(4, strlen($value) - $show * 2)) . substr($value, -$show);
}
