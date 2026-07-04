<?php
// ============================================================
//  ViennaStateRP – Datenverschlüsselung  v1.0
//
//  Verschlüsselt sensitive Datenbankfelder mit AES-256-GCM.
//  AES-256-GCM ist dem CBC-Modus vorzuziehen da es
//  Authentizität (Integrity) mit verschlüsselt.
//
//  Key-Quelle: VSRP_SECRET_KEY in .env (bereits vorhanden)
//              oder vsrp.key Datei außerhalb Webroot
//
//  Verschlüsselte Werte beginnen mit "E2:"
//  Format: E2:<base64(nonce[12] + tag[16] + ciphertext)>
//
//  Verschlüsselt werden:
//    - users.email
//    - users.discord_id (optional – für extra Sicherheit)
//    - discord_tokens.access_token
//    - discord_tokens.refresh_token
//    - login_log.ip_hash  → eigentlich SHA-256, kein Plaintext
// ============================================================

if (defined('_VSRP_CRYPT_LOADED')) return;
define('_VSRP_CRYPT_LOADED', true);

final class VsrpCrypt
{
    private const PREFIX  = 'E2:';
    private const CIPHER  = 'aes-256-gcm';
    private const NONCE_LEN = 12;
    private const TAG_LEN   = 16;

    // ── Schlüssel holen (gleiche Quelle wie .env-Entschlüsselung) ────────────

    private static function key(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        // 1. Key-Datei außerhalb Webroot
        $keyFile = getenv('VSRP_KEY_FILE') ?: (dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/vsrp.key');
        if ($keyFile !== '' && is_readable($keyFile)) {
            $raw = trim((string) file_get_contents($keyFile));
            if ($raw !== '') {
                $cached = hash('sha256', $raw, true); // 32 Bytes
                return $cached;
            }
        }

        // 2. Umgebungsvariable ($_ENV zuerst, dann getenv – putenv() kann auf Hosts deaktiviert sein)
        $envKey = $_ENV['VSRP_SECRET_KEY'] ?? getenv('VSRP_SECRET_KEY');
        if ($envKey !== false && $envKey !== null && $envKey !== '') {
            $cached = hash('sha256', (string) $envKey, true);
            return $cached;
        }

        throw new \RuntimeException('[VsrpCrypt] Kein Verschlüsselungs-Key gefunden (VSRP_SECRET_KEY oder vsrp.key)');
    }

    // ── Verschlüsseln ─────────────────────────────────────────────────────────

    /**
     * Verschlüsselt einen String mit AES-256-GCM.
     * Gibt einen Base64-String mit Prefix zurück.
     *
     * @throws \RuntimeException wenn OpenSSL oder Key fehlt
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') return '';

        $nonce = random_bytes(self::NONCE_LEN);
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('[VsrpCrypt] Verschlüsselung fehlgeschlagen: ' . openssl_error_string());
        }

        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    // ── Entschlüsseln ─────────────────────────────────────────────────────────

    /**
     * Entschlüsselt einen per encrypt() verschlüsselten Wert.
     * Gibt bei Fehler NULL zurück (kein Exception-Leak nach außen).
     */
    public static function decrypt(string $value): ?string
    {
        if ($value === '' || !str_starts_with($value, self::PREFIX)) {
            return $value; // Nicht verschlüsselt – Klartextwert zurückgeben
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < self::NONCE_LEN + self::TAG_LEN + 1) {
            error_log('[VsrpCrypt] Ungültiger verschlüsselter Wert');
            return null;
        }

        $nonce      = substr($raw, 0, self::NONCE_LEN);
        $tag        = substr($raw, self::NONCE_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::NONCE_LEN + self::TAG_LEN);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plain === false) {
            error_log('[VsrpCrypt] Entschlüsselung fehlgeschlagen (falscher Key oder korrupte Daten)');
            return null;
        }

        return $plain;
    }

    // ── Hilfsfunktionen ───────────────────────────────────────────────────────

    /** Ist der Wert verschlüsselt? */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Sicherer Vergleich: Entschlüsselt und vergleicht constant-time.
     * Für E-Mail-Suche in der Datenbank.
     */
    public static function matches(string $encrypted, string $plaintext): bool
    {
        $decrypted = self::decrypt($encrypted);
        if ($decrypted === null) return false;
        return hash_equals($decrypted, $plaintext);
    }

    /**
     * Verschlüsselt Email für DB-Suche.
     * Problem: Verschlüsselte Werte sind nicht direkt suchbar (jeder Nonce ist anders).
     * Lösung: Suchbares HMAC-Blind-Index zusätzlich speichern.
     */
    public static function blindIndex(string $value): string
    {
        $key = self::key();
        return hash_hmac('sha256', strtolower(trim($value)), $key);
    }
}

// ── Kurzformen ────────────────────────────────────────────────────────────────

function db_encrypt(?string $value): ?string
{
    if ($value === null || $value === '') return $value;
    try {
        return VsrpCrypt::encrypt($value);
    } catch (\Throwable $e) {
        error_log('[db_encrypt] ' . $e->getMessage());
        return $value; // Fallback: Klartext
    }
}

function db_decrypt(?string $value): ?string
{
    if ($value === null || $value === '') return $value;
    return VsrpCrypt::decrypt($value);
}

function db_blind_index(string $value): string
{
    return VsrpCrypt::blindIndex($value);
}
