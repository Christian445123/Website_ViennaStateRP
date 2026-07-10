<?php
declare(strict_types=1);
// Dieser Endpunkt wurde deaktiviert.
// Die direkte Spielserver-DB-Anbindung ist nicht mehr verfügbar.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Endpunkt wurde deaktiviert (410 Gone).']);


// Sync-Log Datei
define('SYNC_LOG_FILE', sys_get_temp_dir() . '/vsrp_fivem_sync.json');

function sync_log_read(): array
{
    if (!file_exists(SYNC_LOG_FILE)) return ['runs' => [], 'last' => null];
    return json_decode(file_get_contents(SYNC_LOG_FILE), true) ?? ['runs' => [], 'last' => null];
}

function sync_log_write(array $entry): void
{
    $log = sync_log_read();
    array_unshift($log['runs'], $entry);
    $log['runs']  = array_slice($log['runs'], 0, 20); // max 20 Einträge
    $log['last']  = $entry;
    file_put_contents(SYNC_LOG_FILE, json_encode($log, JSON_UNESCAPED_UNICODE));
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verify_csrf($body['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Ungültige CSRF-Token']);
    exit;
}

$action = $body['action'] ?? 'status';

// ── Config speichern ──────────────────────────────────────────────────────────
if ($action === 'save_config') {
    $pass = $body['pass'] ?? '';
    if ($pass === '' || str_contains($pass, '***')) {
        $ex = fivem_db_load();
        $pass = $ex['pass'] ?? '';
    }
    $saved = fivem_db_save([
        'host'    => trim($body['host']    ?? ''),
        'port'    => trim($body['port']    ?? '3306'),
        'name'    => trim($body['name']    ?? ''),
        'user'    => trim($body['user']    ?? ''),
        'pass'    => $pass,
        'charset' => trim($body['charset'] ?? 'utf8mb4'),
    ]);
    echo json_encode(['ok' => $saved, 'message' => $saved ? 'Gespeichert' : 'Speichern fehlgeschlagen']);
    exit;
}

// ── Verbindung testen ─────────────────────────────────────────────────────────
if ($action === 'test') {
    $remote = fivem_db_connect();
    if (!$remote) {
        echo json_encode(['ok' => false, 'error' => 'Verbindung fehlgeschlagen – Credentials prüfen']);
        exit;
    }
    // Nur lesende Queries auf der Remote-DB
    $version = $remote->query("SELECT VERSION()")->fetchColumn();
    $tables  = $remote->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $remote  = null; // Verbindung sofort schließen
    echo json_encode(['ok' => true, 'version' => $version, 'table_count' => count($tables), 'tables' => $tables]);
    exit;
}

// ── Sync-Status ───────────────────────────────────────────────────────────────
if ($action === 'status') {
    $cfg = fivem_db_load();
    echo json_encode([
        'ok'         => true,
        'configured' => $cfg !== null,
        'host'       => $cfg ? ($cfg['host'] . ':' . ($cfg['port'] ?? '3306')) : null,
        'db_name'    => $cfg['name'] ?? null,        'target_db'  => env('DB_LIVE_NAME', 'DBLIVE'),
        'target_user'=> env('DB_LIVE_USER', ''),        'log'        => sync_log_read(),
    ]);
    exit;
}

// ── Vollständiger Sync ────────────────────────────────────────────────────────
if ($action === 'sync') {
    $remote = fivem_db_connect();
    if (!$remote) {
        echo json_encode(['ok' => false, 'error' => 'Remote-Verbindung fehlgeschlagen']);
        exit;
    }

    // Schreibzugriff über DB_LIVE User (fivemLIVE) – hat Rechte auf DBLIVE
    $liveDb = live_db_connect();
    if (!$liveDb) {
        echo json_encode(['ok' => false, 'error' => 'DB_LIVE-Verbindung fehlgeschlagen – DB_LIVE_* in .env prüfen']);
        exit;
    }

    $startTime = microtime(true);
    $results   = ['tables' => [], 'errors' => [], 'rows_total' => 0];
    $tables    = $body['tables'] ?? null; // null = alle

    try {
        // Zieldatenbank: DBLIVE (bereits existiert, fivemLIVE hat Rechte)
        // Alle Tabellen der Remote-DB
        $remoteTables = $remote->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($tables) && !empty($tables)) {
            $remoteTables = array_intersect($remoteTables, $tables);
        }

        foreach ($remoteTables as $table) {
            $tSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            if ($tSafe !== $table) continue;

            try {
                // ── NUR LESEN von Remote (SELECT/SHOW) ──────────────────
                $createRow  = $remote->query("SHOW CREATE TABLE `$tSafe`")->fetch();
                $createStmt = $createRow['Create Table'] ?? null;
                if (!$createStmt) {
                    $results['errors'][] = "$tSafe: Struktur nicht lesbar";
                    continue;
                }

                // Tabelle in DBLIVE anlegen (DROP + CREATE für frischen Sync)
                $liveDb->exec("SET FOREIGN_KEY_CHECKS=0");
                $liveDb->exec("DROP TABLE IF EXISTS `$tSafe`");
                $liveDb->exec($createStmt);
                $liveDb->exec("SET FOREIGN_KEY_CHECKS=1");

                // Daten in Batches kopieren
                $rowCount   = 0;
                $batchSize  = 500;
                $offset     = 0;

                while (true) {
                    $rows = $remote->query("SELECT * FROM `$tSafe` LIMIT $batchSize OFFSET $offset")->fetchAll();
                    if (empty($rows)) break;

                    $cols   = array_keys($rows[0]);
                    $colsSq = implode(', ', array_map(fn($c) => "`$c`", $cols));
                    $phCnt  = count($cols);
                    $rowPh  = '(' . implode(',', array_fill(0, $phCnt, '?')) . ')';
                    $allPh  = implode(',', array_fill(0, count($rows), $rowPh));

                    $vals = [];
                    foreach ($rows as $row) {
                        foreach ($row as $v) $vals[] = $v;
                    }

                    $liveDb->prepare("INSERT INTO `$tSafe` ($colsSq) VALUES $allPh")->execute($vals);
                    $rowCount += count($rows);
                    $offset   += $batchSize;

                    if (count($rows) < $batchSize) break;
                }

                $results['tables'][]    = ['name' => $tSafe, 'rows' => $rowCount, 'ok' => true];
                $results['rows_total'] += $rowCount;

            } catch (\Throwable $e) {
                $results['tables'][]  = ['name' => $tSafe, 'ok' => false, 'error' => $e->getMessage()];
                $results['errors'][]  = "$tSafe: " . $e->getMessage();
                try { $liveDb->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (\Throwable $e2) {}
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $entry = [
            'at'         => date('Y-m-d H:i:s'),
            'ok'         => empty($results['errors']),
            'tables'     => count($results['tables']),
            'rows'       => $results['rows_total'],
            'duration_s' => $duration,
            'errors'     => $results['errors'],
            'target_db'  => env('DB_LIVE_NAME', 'DBLIVE'),
        ];
        sync_log_write($entry);

        echo json_encode([
            'ok'      => true,
            'message' => sprintf('%d Tabellen, %s Zeilen in %.1fs', count($results['tables']), number_format($results['rows_total']), $duration),
            'details' => $results,
            'log'     => $entry,
        ], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion: ' . $action]);
