<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Rate Limiting
// ============================================================

if (defined('_VSRP_RATELIMIT_LOADED')) return;
define('_VSRP_RATELIMIT_LOADED', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

/**
 * Prüft ob das IP-Limit überschritten wurde.
 * Gibt TRUE zurück wenn Anfrage erlaubt, FALSE wenn blockiert.
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    global $pdo;

    $ip = trim(explode(',', (string)(
        $_SERVER['HTTP_CF_CONNECTING_IP']
     ?? $_SERVER['HTTP_X_FORWARDED_FOR']
     ?? $_SERVER['REMOTE_ADDR']
     ?? '0.0.0.0'
    ))[0]);

    $stmt = $pdo->prepare("
        SELECT id, attempts FROM rate_limits
        WHERE ip = ? AND action = ? AND last_attempt >= NOW() - INTERVAL ? SECOND
    ");
    $stmt->execute([$ip, $action, $windowSeconds]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->prepare("
            INSERT INTO rate_limits (ip, action, attempts, last_attempt)
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = 1, last_attempt = NOW()
        ")->execute([$ip, $action]);
        return true;
    }

    if ((int) $row['attempts'] >= $maxAttempts) {
        return false;
    }

    $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?")
        ->execute([$row['id']]);

    return true;
}

/** Sendet 429-Antwort und beendet das Skript */
function rateLimitExceeded(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (function_exists('log_event')) {
        log_event('login_bruteforce', "Rate-Limit überschritten von {$ip}", 0, ['ip' => $ip]);
    }
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Zu viele Anfragen. Bitte warte einige Minuten.']);
    exit;
}
