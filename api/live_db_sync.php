<?php
declare(strict_types=1);
// Dieser Endpunkt wurde deaktiviert.
// Die direkte Spielserver-DB-Anbindung ist nicht mehr verfügbar.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Endpunkt wurde deaktiviert (410 Gone).']);


$liveDbName = preg_replace('/[^a-zA-Z0-9_]/', '', env('DB_LIVE_NAME', 'DBLIVE'));

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
    $fivemCfg   = fivem_db_load();
    $log        = live_log_read($logFile);
    $liveReady  = false;
    $liveTables = 0;
    $liveSize   = '–';

    $liveDb = live_db_connect();
    if ($liveDb) {
        try {
            $liveTables = (int) $liveDb->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchColumn();
            $sizeRow    = $liveDb->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchColumn();
            $liveSize   = $sizeRow ? $sizeRow . ' MB' : '< 1 MB';
            $liveReady  = true;
        } catch (\Throwable $e) {}
        $liveDb = null;
    }

    echo json_encode([
        'ok'               => true,
        'live_db'          => $liveDbName,
        'live_ready'       => $liveReady,
        'live_tables'      => $liveTables,
        'live_size'        => $liveSize,
        'live_user'        => env('DB_LIVE_USER', ''),
        'fivem_configured' => $fivemCfg !== null,
        'fivem_host'       => $fivemCfg ? ($fivemCfg['host'] . ':' . ($fivemCfg['port'] ?? '3306')) : null,
        'fivem_db'         => $fivemCfg['name'] ?? null,
        'log'              => $log,
    ]);
    exit;
}

// ── TABELLENLISTE aus DB_LIVE ─────────────────────────────────────────────────
if ($action === 'tables') {
    $liveDb = live_db_connect();
    if (!$liveDb) {
        echo json_encode(['ok' => false, 'error' => 'DB_LIVE nicht erreichbar – DB_LIVE_* in .env prüfen']);
        exit;
    }
    try {
        $rows = $liveDb->query(
            "SELECT TABLE_NAME AS name, TABLE_ROWS AS est_rows,
               ROUND((data_length+index_length)/1024,1) AS size_kb,
               CREATE_TIME AS created, UPDATE_TIME AS updated
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME"
        )->fetchAll();
        echo json_encode(['ok' => true, 'tables' => $rows]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    $liveDb = null;
    exit;
}

// ── TABELLE DURCHSUCHEN ───────────────────────────────────────────────────────
if ($action === 'browse') {
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($body['table'] ?? ''));
    $page   = max(1, (int)($body['page']  ?? 1));
    $limit  = min(100, max(10, (int)($body['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    if (!$table) { echo json_encode(['ok' => false, 'error' => 'Tabellenname fehlt']); exit; }
    $liveDb = live_db_connect();
    if (!$liveDb) { echo json_encode(['ok' => false, 'error' => 'DB_LIVE nicht erreichbar']); exit; }
    try {
        $total = (int) $liveDb->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $rows  = $liveDb->query("SELECT * FROM `{$table}` LIMIT {$limit} OFFSET {$offset}")->fetchAll();
        $cols  = $rows ? array_keys($rows[0]) : [];
        echo json_encode(['ok' => true, 'total' => $total, 'cols' => $cols, 'rows' => $rows, 'page' => $page, 'limit' => $limit]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    $liveDb = null;
    exit;
}

// ── VOLLSTÄNDIGER SYNC: FiveM Remote → DB_LIVE ───────────────────────────────
if ($action === 'sync') {
    $remote = fivem_db_connect();
    if (!$remote) {
        echo json_encode(['ok' => false, 'error' => 'FiveM Remote-DB: Verbindung fehlgeschlagen – Credentials unter FiveM DB Sync konfigurieren']);
        exit;
    }
    $liveDb = live_db_connect();
    if (!$liveDb) {
        echo json_encode(['ok' => false, 'error' => 'DB_LIVE (' . $liveDbName . '): Verbindung fehlgeschlagen – DB_LIVE_* in .env prüfen']);
        exit;
    }

    $start      = microtime(true);
    $results    = ['tables' => [], 'errors' => [], 'rows_total' => 0];
    $onlyTables = (is_array($body['tables'] ?? null) && !empty($body['tables'])) ? $body['tables'] : null;

    try {
        $remoteTables = $remote->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if ($onlyTables !== null) {
            $remoteTables = array_values(array_intersect($remoteTables, $onlyTables));
        }

        foreach ($remoteTables as $table) {
            $tSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            if ($tSafe !== $table) continue;

            try {
                $createRow  = $remote->query("SHOW CREATE TABLE `{$tSafe}`")->fetch();
                $createStmt = $createRow['Create Table'] ?? null;
                if (!$createStmt) { $results['errors'][] = "{$tSafe}: Struktur nicht lesbar"; continue; }

                $liveDb->exec("SET FOREIGN_KEY_CHECKS = 0");
                $liveDb->exec("DROP TABLE IF EXISTS `{$tSafe}`");
                $liveDb->exec($createStmt);
                $liveDb->exec("SET FOREIGN_KEY_CHECKS = 1");

                $rowCount = 0; $batchSize = 500; $offset = 0;
                while (true) {
                    $rows = $remote->query("SELECT * FROM `{$tSafe}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll();
                    if (empty($rows)) break;
                    $cols   = array_keys($rows[0]);
                    $colsSq = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
                    $rowPh  = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
                    $allPh  = implode(',', array_fill(0, count($rows), $rowPh));
                    $vals   = [];
                    foreach ($rows as $row) foreach ($row as $v) $vals[] = $v;
                    $liveDb->prepare("INSERT INTO `{$tSafe}` ({$colsSq}) VALUES {$allPh}")->execute($vals);
                    $rowCount += count($rows); $offset += $batchSize;
                    if (count($rows) < $batchSize) break;
                }
                $results['tables'][]    = ['name' => $tSafe, 'rows' => $rowCount, 'ok' => true];
                $results['rows_total'] += $rowCount;

            } catch (\Throwable $e) {
                $results['tables'][]  = ['name' => $tSafe, 'ok' => false, 'error' => $e->getMessage()];
                $results['errors'][]  = "{$tSafe}: " . $e->getMessage();
                try { $liveDb->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (\Throwable $e2) {}
            }
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

        echo json_encode([
            'ok'      => true,
            'message' => sprintf('%d Tabellen · %s Zeilen → %s (%.1fs)', count($results['tables']), number_format($results['rows_total']), $liveDbName, $duration),
            'details' => $results,
            'log'     => $entry,
        ], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        $entry = ['at' => date('Y-m-d H:i:s'), 'ok' => false, 'error' => $e->getMessage(), 'duration_s' => round(microtime(true) - $start, 2)];
        live_log_write($entry, $logFile);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    $remote = null; $liveDb = null;
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion']);
