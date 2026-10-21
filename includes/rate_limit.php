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
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 300, ?string $subject = null): bool
{
    global $pdo;

    if (!isset($pdo)) return true; // kein DB → offen lassen

    // Echte Client-IP (Proxy-Header nur von vertrauenswürdigen Proxies, siehe client_ip()).
    // $subject erlaubt zusätzlich ein Limit pro Ziel (z. B. Benutzername) statt pro IP.
    $ip = $subject !== null ? 'u:' . substr(hash('sha256', strtolower($subject)), 0, 40) : client_ip();

    try {
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
    } catch (\Throwable $e) {
        // Tabelle fehlt oder DB-Fehler → Login nicht blockieren
        error_log('[RateLimit] checkRateLimit fehlgeschlagen: ' . $e->getMessage());
        return true;
    }
}

/** Sendet 429-Antwort und beendet das Skript */
function rateLimitExceeded(): void
{
    $ip = client_ip();
    if (function_exists('log_event')) {
        log_event('login_bruteforce', "Rate-Limit überschritten von {$ip}", 0, ['ip' => $ip]);
    }
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Zu viele Anfragen. Bitte warte einige Minuten.']);
    exit;
}
