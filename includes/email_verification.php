<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – E-Mail-Bestätigung für Passwort-Accounts
//  Token: 32 Zufallsbytes, in der DB nur als SHA-256 gespeichert,
//  24 Stunden gültig, einmalig verwendbar.
//  Abschaltbar mit REQUIRE_EMAIL_VERIFICATION=false in der .env.
// ============================================================

if (defined('_VSRP_EMAILVERIFY_LOADED')) return;
define('_VSRP_EMAILVERIFY_LOADED', true);

require_once __DIR__ . '/db.php';

const EMAIL_VERIFY_TTL = 86400; // 24 h

function email_verification_required(): bool
{
    return strtolower(env('REQUIRE_EMAIL_VERIFICATION', 'true')) !== 'false';
}

/** Einfacher Mailversand über PHP mail(); Adresse wurde zuvor mit FILTER_VALIDATE_EMAIL geprüft. */
function email_send(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
        return false; // Header-Injection verhindern
    }
    $siteHost = (string) parse_url(defined('SITE_URL') ? SITE_URL : env('SITE_URL', ''), PHP_URL_HOST);
    $from     = env('MAIL_FROM', $siteHost !== '' ? 'noreply@' . $siteHost : 'noreply@localhost');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return false;

    $headers = [
        'From'         => env('SITE_NAME', 'ViennaStateRP') . ' <' . $from . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ];
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $encodedSubject, $body, $headers);
    if (!$ok) error_log('[Mail] Versand an Nutzer fehlgeschlagen (mail() lieferte false) – MTA/MAIL_FROM prüfen.');
    return $ok;
}

/** Erzeugt einen neuen Token (alte werden ungültig) und verschickt den Bestätigungslink. */
function email_verification_send(PDO $pdo, int $userId, string $email): bool
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('INSERT INTO email_verifications (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
        ->execute([hash('sha256', $token), $userId, date('Y-m-d H:i:s', time() + EMAIL_VERIFY_TTL)]);

    $link = rtrim(defined('SITE_URL') ? SITE_URL : env('SITE_URL', ''), '/') . '/verify_email.php?token=' . $token;
    $body = "Willkommen bei " . env('SITE_NAME', 'ViennaStateRP') . "!\n\n"
          . "Bitte bestätige deine E-Mail-Adresse mit diesem Link (24 Stunden gültig):\n\n"
          . $link . "\n\n"
          . "Falls du dich nicht registriert hast, ignoriere diese Nachricht einfach.\n";
    return email_send($email, 'Bitte bestätige deine E-Mail-Adresse', $body);
}

/** Löst einen Token ein. Gibt true zurück, wenn die E-Mail jetzt bestätigt ist. */
function email_verification_confirm(PDO $pdo, string $token): bool
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) return false;
    $hash = hash('sha256', $token);

    $st = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row) return false;

    $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL')
        ->execute([$row['user_id']]);
    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$row['user_id']]);
    return true;
}
