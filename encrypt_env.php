<?php
/**
 * ViennaStateRP – Passwörter für .env verschlüsseln
 *
 * Aufruf (CLI):
 *   php encrypt_env.php "MeinGeheimesPasswort"
 *
 * Das Ergebnis (ENC:...) in die .env eintragen.
 * Voraussetzung: VSRP_SECRET_KEY in .env oder /home/vsrp/vsrp.key muss gesetzt sein.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Nur per CLI ausführbar.');
}

require_once __DIR__ . '/includes/db.php'; // lädt .env + Entschlüsselungs-Helfer

if (empty($argv[1])) {
    echo "Verwendung: php encrypt_env.php \"ZuVerschlüsselnderWert\"\n";
    exit(1);
}

$plain     = $argv[1];
$encrypted = vsrp_env_encrypt($plain);

echo "\nKlartext : {$plain}\n";
echo "Verschlüsselt: {$encrypted}\n\n";
echo "→ In .env eintragen, z.B.:\n";
echo "  DB_PASS={$encrypted}\n\n";
