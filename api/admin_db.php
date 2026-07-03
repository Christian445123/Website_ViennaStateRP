<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Admin: DB-Konfiguration speichern/laden
//  POST /api/admin_db.php  – Credentials speichern
//  GET  /api/admin_db.php  – Aktuelle Config laden (maskiert)
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/db_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !has_permission('admin')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// ── GET: Aktuelle Config anzeigen (Passwort maskiert) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cfg = db_config_load();
    if ($cfg) {
        // Passwort maskieren
        if (isset($cfg['pass'])) {
            $cfg['pass'] = db_config_mask($cfg['pass']);
        }
        $cfg['source'] = 'db_config.enc';
    } else {
        // Aus .env lesen (maskiert)
        $cfg = [
            'host'    => env('DB_HOST', '127.0.0.1'),
            'port'    => env('DB_PORT', '3306'),
            'name'    => env('DB_NAME', ''),
            'user'    => env('DB_USER', ''),
            'pass'    => db_config_mask(env('DB_PASS', '')),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'source'  => '.env',
        ];
    }
    echo json_encode(['ok' => true, 'config' => $cfg]);
    exit;
}

// ── POST: Neue Credentials speichern und testen ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ungültiger JSON-Body']);
        exit;
    }

    if (!verify_csrf($body['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Ungültige CSRF-Token']);
        exit;
    }

    $host    = trim($body['host']    ?? '');
    $port    = (int) ($body['port']  ?? 3306);
    $name    = trim($body['name']    ?? '');
    $user    = trim($body['user']    ?? '');
    $pass    = $body['pass']         ?? '';
    $charset = trim($body['charset'] ?? 'utf8mb4');

    // Wenn Passwort leer oder maskiert → altes beibehalten
    if ($pass === '' || str_contains($pass, '***')) {
        $existing = db_config_load();
        $pass = $existing['pass'] ?? env('DB_PASS', '');
    }

    // Validierung
    if ($host === '' || $name === '' || $user === '') {
        echo json_encode(['ok' => false, 'error' => 'Host, Datenbankname und Benutzer sind erforderlich.']);
        exit;
    }

    // ── Verbindung testen ─────────────────────────────────────────────────────
    $testResult = null;
    try {
        $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset={$charset};connect_timeout=5";
        $test = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE  => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT  => 5,
        ]);
        $version = $test->query("SELECT VERSION()")->fetchColumn();
        $testResult = ['connected' => true, 'version' => $version];
        $test = null;
    } catch (\PDOException $e) {
        echo json_encode([
            'ok'    => false,
            'error' => 'Verbindungstest fehlgeschlagen: ' . $e->getMessage(),
        ]);
        exit;
    }

    // ── Speichern ─────────────────────────────────────────────────────────────
    $saved = db_config_save([
        'host'       => $host,
        'port'       => (string) $port,
        'name'       => $name,
        'user'       => $user,
        'pass'       => $pass,
        'charset'    => $charset,
        'updated_by' => $user ?? 'admin',
    ]);

    if (!$saved) {
        echo json_encode(['ok' => false, 'error' => 'Speichern fehlgeschlagen – Key vorhanden?']);
        exit;
    }

    echo json_encode([
        'ok'      => true,
        'message' => 'Datenbankverbindung gespeichert und getestet.',
        'db'      => $testResult,
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
