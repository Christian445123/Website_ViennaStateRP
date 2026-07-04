<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – DB_LIVE Sync API
//  POST /api/live_db_sync.php  { action: 'sync'|'status'|'tables'|'browse' }
//  Synct: FiveM Remote-DB  →  Lokale db_live Datenbank
//  Nur für Admins
// ============================================================
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/fivem_db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !has_permission('admin')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// ── Lokale DB_LIVE Datenbankname ──────────────────────────────────────────────
$liveDbName = env('DB_LIVE_NAME', 'db_live');
$liveDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $liveDbName); // Sicherheit

// ── Log-Datei ─────────────────────────────────────────────────────────────────
$logFile = sys_get_temp_dir() . '/vsrp_live_sync.json';

function live_log_read(string $f): array
{
    if (!file_exists($f)) return ['runs' => [], 'last' => null];
    return json_decode((string) file_get_contents($f), true) ?? ['runs' => [], 'last' => null];
}

function live_log_write(array $entry, string $f): void
{
    $log = live_log_read($f);
    array_unshift($log['runs'], $entry);
    $log['runs'] = array_slice($log['runs'], 0, 30);
    $log['last'] = $entry;
    file_put_contents($f, json_encode($log, JSON_UNESCAPED_UNICODE));
}

// ── Request lesen ─────────────────────────────────────────────────────────────
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? 'status';

// CSRF-Schutz außer bei reinen GET-ähnlichen Leseanfragen
if (!in_array($action, ['status', 'tables', 'browse'], true)) {
    if (!verify_csrf($body['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Ungültige CSRF-Token']);
        exit;
    }
}

// ── STATUS ────────────────────────────────────────────────────────────────────
if ($action === 'status') {
    $fivemCfg  = fivem_db_load();
    $log       = live_log_read($logFile);
    $liveReady = false;
    $liveTables = 0;
    $liveSize   = '–';

    if (isset($pdo)) {
        try {
            $exists = $pdo->query(
                "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = " .
                $pdo->quote($liveDbName)
            )->fetchColumn();
            if ($exists) {
                $liveReady  = true;
                $liveTables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($liveDbName))->fetchColumn();
                $sizeRow    = $pdo->query(
                    "SELECT ROUND(SUM(data_length + index_length)/1024/1024, 2) AS mb
                     FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($liveDbName)
                )->fetchColumn();
                $liveSize = $sizeRow ? $sizeRow . ' MB' : '< 1 MB';
            }
        } catch (\Throwable $e) {}
    }

    echo json_encode([
        'ok'              => true,
        'live_db'         => $liveDbName,
        'live_ready'      => $liveReady,
        'live_tables'     => $liveTables,
        'live_size'       => $liveSize,
        'fivem_configured'=> $fivemCfg !== null,
        'fivem_host'      => $fivemCfg ? ($fivemCfg['host'] . ':' . ($fivemCfg['port'] ?? '3306')) : null,
        'fivem_db'        => $fivemCfg['name'] ?? null,
        'log'             => $log,
    ]);
    exit;
}

// ── TABELLEN LISTE (aus db_live) ─────────────────────────────────────────────
if ($action === 'tables') {
    if (!isset($pdo)) {
        echo json_encode(['ok' => false, 'error' => 'Lokale DB nicht verfügbar']);
        exit;
    }
    try {
        $rows = $pdo->query(
            "SELECT
               t.TABLE_NAME        AS name,
               t.TABLE_ROWS        AS est_rows,
               ROUND((t.data_length + t.index_length)/1024, 1) AS size_kb,
               t.CREATE_TIME       AS created,
               t.UPDATE_TIME       AS updated
             FROM information_schema.TABLES t
             WHERE t.TABLE_SCHEMA = " . $pdo->quote($liveDbName) . "
             ORDER BY t.TABLE_NAME"
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'tables' => $rows]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── TABELLE DURCHSUCHEN ───────────────────────────────────────────────────────
if ($action === 'browse') {
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($body['table'] ?? ''));
    $page   = max(1, (int)($body['page'] ?? 1));
    $limit  = min(100, max(10, (int)($body['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    if (!$table || !isset($pdo)) {
        echo json_encode(['ok' => false, 'error' => 'Tabelle oder DB fehlt']);
        exit;
    }
    try {
        $total  = (int) $pdo->query("SELECT COUNT(*) FROM `{$liveDbName}`.`{$table}`")->fetchColumn();
        $rows   = $pdo->query("SELECT * FROM `{$liveDbName}`.`{$table}` LIMIT {$limit} OFFSET {$offset}")->fetchAll();
        $cols   = $rows ? array_keys($rows[0]) : [];
        echo json_encode(['ok' => true, 'total' => $total, 'cols' => $cols, 'rows' => $rows, 'page' => $page, 'limit' => $limit]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── VOLLSTÄNDIGER SYNC ────────────────────────────────────────────────────────
if ($action === 'sync') {
    // Verbindungen prüfen
    $remote = fivem_db_connect();
    if (!$remote) {
        echo json_encode(['ok' => false, 'error' => 'Remote FiveM-DB: Verbindung fehlgeschlagen – Credentials in FiveM DB Sync konfigurieren']);
        exit;
    }
    if (!isset($pdo)) {
        echo json_encode(['ok' => false, 'error' => 'Lokale DB nicht verfügbar']);
        exit;
    }

    $start     = microtime(true);
    $results   = ['tables' => [], 'errors' => [], 'rows_total' => 0];
    $onlyTables = is_array($body['tables'] ?? null) && !empty($body['tables']) ? $body['tables'] : null;

    try {
        // DB_LIVE anlegen falls nicht vorhanden
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$liveDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Alle Tabellen der Remote-DB lesen
        $remoteTables = $remote->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if ($onlyTables !== null) {
            $remoteTables = array_values(array_intersect($remoteTables, $onlyTables));
        }

        $total = count($remoteTables);
        $done  = 0;

        foreach ($remoteTables as $table) {
            $tSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            if ($tSafe !== $table) continue;

            try {
                // Struktur von Remote lesen (nur SELECT/SHOW)
                $createRow  = $remote->query("SHOW CREATE TABLE `{$tSafe}`")->fetch();
                $createStmt = $createRow['Create Table'] ?? null;
                if (!$createStmt) {
                    $results['errors'][] = "{$tSafe}: Struktur nicht lesbar";
                    continue;
                }

                // Ziel-Tabelle neu erstellen (vollständiger Ersat-Sync)
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->exec("DROP TABLE IF EXISTS `{$liveDbName}`.`{$tSafe}`");
                // CREATE-Statement für Ziel-DB anpassen
                $pdo->exec($createStmt);
                // Tabelle in DB_LIVE verschieben (wurde in aktueller DB angelegt)
                try {
                    $pdo->exec("ALTER TABLE `{$tSafe}` RENAME TO `{$liveDbName}`.`{$tSafe}`");
                } catch (\Throwable $ren) {
                    // Fallback: direkt in db_live anlegen
                    $pdo->exec("DROP TABLE IF EXISTS `{$liveDbName}`.`{$tSafe}`");
                    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$liveDbName}`.`{$tSafe}` LIKE `{$tSafe}`");
                    try { $pdo->exec("DROP TABLE IF EXISTS `{$tSafe}`"); } catch (\Throwable $e2) {}
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                // Daten in 500er-Batches kopieren
                $rowCount  = 0;
                $batchSize = 500;
                $offset    = 0;

                while (true) {
                    $rows = $remote->query("SELECT * FROM `{$tSafe}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll();
                    if (empty($rows)) break;

                    $cols   = array_keys($rows[0]);
                    $colsSq = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
                    $rowPh  = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
                    $allPh  = implode(',', array_fill(0, count($rows), $rowPh));
                    $vals   = [];
                    foreach ($rows as $row) {
                        foreach ($row as $v) $vals[] = $v;
                    }

                    $pdo->prepare(
                        "INSERT INTO `{$liveDbName}`.`{$tSafe}` ({$colsSq}) VALUES {$allPh}"
                    )->execute($vals);

                    $rowCount += count($rows);
                    $offset   += $batchSize;
                    if (count($rows) < $batchSize) break;
                }

                $results['tables'][]    = ['name' => $tSafe, 'rows' => $rowCount, 'ok' => true];
                $results['rows_total'] += $rowCount;

            } catch (\Throwable $e) {
                $results['tables'][]  = ['name' => $tSafe, 'ok' => false, 'error' => $e->getMessage()];
                $results['errors'][]  = "{$tSafe}: " . $e->getMessage();
                try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (\Throwable $e2) {}
            }
            $done++;
        }

        $duration = round(microtime(true) - $start, 2);
        $entry = [
            'at'         => date('Y-m-d H:i:s'),
            'ok'         => empty($results['errors']),
            'tables'     => count($results['tables']),
            'rows'       => $results['rows_total'],
            'duration_s' => $duration,
            'errors'     => $results['errors'],
            'target_db'  => $liveDbName,
        ];
        live_log_write($entry, $logFile);

        // Spalten-Cache invalidieren (Migration-Check in db.php)
        @unlink(sys_get_temp_dir() . '/vsrp_migrated_role.flag');

        echo json_encode([
            'ok'      => true,
            'message' => sprintf('%d Tabellen, %s Zeilen in %.1fs → %s', count($results['tables']), number_format($results['rows_total']), $duration, $liveDbName),
            'details' => $results,
            'log'     => $entry,
        ], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        $duration = round(microtime(true) - $start, 2);
        $entry = ['at' => date('Y-m-d H:i:s'), 'ok' => false, 'error' => $e->getMessage(), 'duration_s' => $duration];
        live_log_write($entry, $logFile);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion']);
