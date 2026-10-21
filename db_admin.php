<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/logger.php';

require_login();
if (!has_capability('db.view')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff – Admin-Rolle erforderlich'));
    exit;
}
$canRunQuery = has_capability('db.query');

if (!isset($pdo)) {
    die('<h2 style="color:#ef4444;font-family:sans-serif;padding:40px">Keine Datenbankverbindung verfügbar.</h2>');
}

$user = current_user();

// ── Aktuelle Aktion bestimmen ─────────────────────────────────────────────────
$action = $_GET['action'] ?? 'overview';
$table  = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table'] ?? '');
$page   = max(1, (int) ($_GET['p'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

// ── Alle Tabellen laden ───────────────────────────────────────────────────────
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// ── SQL-Query ausführen (nur SELECT erlaubt) ──────────────────────────────────
$queryResult = null;
$queryError  = null;
$querySql    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    if (!$canRunQuery) {
        $queryError = 'Keine Berechtigung, eigene SQL-Abfragen auszuführen.';
    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $queryError = 'Ungültige Anfrage (CSRF).';
    } else {
        $querySql = trim($_POST['sql'] ?? '');
        // Nur SELECT/SHOW/DESCRIBE erlauben
        $firstWord = strtoupper(strtok($querySql, " \t\n\r"));
        // Ein abschließendes ';' ist ok, mehrere Statements nicht
        $sqlCheck = rtrim($querySql, "; \t\n\r");
        // String-Literale entfernen, damit Schlüsselwörter darin keinen Fehlalarm auslösen
        $sqlStripped = (string) preg_replace('/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.)*"/s', "''", $sqlCheck);
        if (!in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'DESC'], true)) {
            $queryError = 'Nur SELECT, SHOW, DESCRIBE und EXPLAIN sind erlaubt.';
        } elseif (strlen($querySql) > 5000) {
            $queryError = 'Abfrage zu lang.';
        } elseif (str_contains($sqlStripped, ';') || preg_match('~/\*|--|#~', $sqlStripped)) {
            $queryError = 'Mehrere Statements und Kommentare sind nicht erlaubt.';
        } elseif (preg_match('/\b(INTO|OUTFILE|DUMPFILE|LOAD_FILE|LOAD\s+DATA|SLEEP|BENCHMARK|GET_LOCK|RELEASE_LOCK|FOR\s+UPDATE|LOCK\s+IN\s+SHARE|HANDLER)\b|@/i', $sqlStripped)) {
            $queryError = 'Diese SQL-Funktion bzw. Klausel ist nicht erlaubt.';
        } elseif (preg_match('/\b(discord_tokens|password_hash|access_token|refresh_token)\b/i', $sqlStripped)
                  || (preg_match('/\*/', preg_replace('/count\s*\(\s*\*\s*\)/i', '', $sqlStripped)) && preg_match('/\busers\b/i', $sqlStripped))) {
            // "SELECT * FROM users" würde password_hash mitliefern → Spalten explizit benennen
            $queryError = 'Zugriff auf Token-/Passwort-Daten gesperrt (bei Tabelle users bitte Spalten einzeln angeben, kein *).';
        } else {
            try {
                // Zusätzliche Absicherung: Sitzung read-only + Zeitlimit (Schutz vor Schreibzugriffen
                // über Funktionen/Subselects und vor Dauerlast durch teure Abfragen)
                $pdo->exec('SET SESSION TRANSACTION READ ONLY');
                try {
                    $pdo->exec('SET SESSION MAX_EXECUTION_TIME = 5000');      // MySQL
                } catch (\Throwable $e) {
                    try { $pdo->exec('SET SESSION max_statement_time = 5'); } catch (\Throwable $e2) {} // MariaDB
                }
                $stmt = $pdo->query($sqlCheck);
                $rows = [];
                while (count($rows) < 500 && ($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $rows[] = $row;
                }
                $queryResult = ['cols' => $rows ? array_keys($rows[0]) : [], 'rows' => $rows];
            } catch (\PDOException $e) {
                error_log('[db_admin] ' . $e->getMessage());
                $queryError = 'Abfrage fehlgeschlagen (Syntax-Fehler oder nicht erlaubt).';
            }
        }
        if (function_exists('log_event')) {
            log_event('admin_action', 'DB-Admin SQL-Abfrage: ' . mb_substr($querySql, 0, 300), (int) ($user['db_id'] ?? 0), [], false);
        }
    }
}

// ── Tabellen-Daten laden ──────────────────────────────────────────────────────
$tableData  = null;
$tableInfo  = null;
$tableCount = 0;

// Geheimnisse (Passwort-Hashes, OAuth-Tokens) werden im Browser nie angezeigt
const DB_ADMIN_HIDDEN_TABLES  = ['discord_tokens'];
const DB_ADMIN_HIDDEN_COLUMNS = ['password_hash', 'access_token', 'refresh_token'];

if ($action === 'table' && $table && in_array($table, $tables, true)) {
    // Spalten-Info
    $tableInfo = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    // Anzahl
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    // Daten (nur nicht-geheime Spalten)
    if (in_array($table, DB_ADMIN_HIDDEN_TABLES, true)) {
        $tableData = [];
    } else {
        $visibleCols = array_values(array_filter(
            array_column($tableInfo, 'Field'),
            fn($c) => !in_array(strtolower($c), DB_ADMIN_HIDDEN_COLUMNS, true)
        ));
        $colList = implode(', ', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', $visibleCols));
        $tableData = $colList === '' ? [] : $pdo->query("SELECT $colList FROM `$table` LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ── Tabellen-Statistiken ──────────────────────────────────────────────────────
$tableStats = [];
if ($action === 'overview') {
    $stats = $pdo->query("
        SELECT table_name, table_rows, 
               ROUND((data_length + index_length) / 1024, 1) AS size_kb,
               create_time, update_time
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stats as $s) {
        $tableStats[$s['table_name']] = $s;
    }
}

function htmle(mixed $v): string {
    if ($v === null) return '<span style="color:#64748b;font-style:italic">NULL</span>';
    return htmlspecialchars((string)$v, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DB Admin – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <style>
    body { padding-top: 0; }
    .db-wrap { display: flex; min-height: 100vh; }

    /* Sidebar */
    .db-sidebar {
      width: 220px; flex-shrink: 0;
      background: var(--bg-card);
      border-right: 1px solid var(--border);
      padding: 24px 0;
      position: fixed; top: 0; left: 0; bottom: 0;
      overflow-y: auto; z-index: 10;
    }
    .db-sidebar-logo {
      font-family: 'Orbitron', sans-serif; font-size: .9rem; font-weight: 900;
      padding: 0 18px 20px; border-bottom: 1px solid var(--border); margin-bottom: 16px;
    }
    .db-sidebar-section { font-size: .65rem; text-transform: uppercase; letter-spacing: 2px; color: #4b5563; padding: 8px 18px 6px; }
    .db-sidebar a {
      display: flex; align-items: center; gap: 8px;
      padding: 7px 18px; font-size: .83rem; color: #94a3b8;
      text-decoration: none; border-left: 2px solid transparent;
      transition: all .15s;
    }
    .db-sidebar a:hover { color: #e2e8f0; background: rgba(255,255,255,.04); }
    .db-sidebar a.active { color: #e8b86d; border-left-color: #e8b86d; background: rgba(232,184,109,.06); }

    /* Main */
    .db-main { margin-left: 220px; flex: 1; padding: 32px; min-width: 0; }
    .db-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
    .db-title { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; }

    /* Cards */
    .db-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
    .db-card-head { padding: 14px 20px; border-bottom: 1px solid var(--border); font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #64748b; display: flex; align-items: center; justify-content: space-between; }

    /* Table */
    .db-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .db-table th { background: rgba(255,255,255,.03); padding: 9px 14px; text-align: left; color: #64748b; font-size: .7rem; text-transform: uppercase; letter-spacing: 1px; white-space: nowrap; border-bottom: 1px solid var(--border); }
    .db-table td { padding: 8px 14px; border-bottom: 1px solid rgba(255,255,255,.04); color: #e2e8f0; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: 'JetBrains Mono', monospace; font-size: .78rem; }
    .db-table tr:last-child td { border-bottom: none; }
    .db-table tr:hover td { background: rgba(255,255,255,.025); }
    .db-table a { color: #e8b86d; text-decoration: none; }
    .db-table a:hover { text-decoration: underline; }

    /* SQL Editor */
    .sql-editor {
      width: 100%; font-family: 'JetBrains Mono', monospace; font-size: .85rem;
      background: #0a0a0f; border: 1px solid var(--border); border-radius: 8px;
      color: #e2e8f0; padding: 14px 16px; resize: vertical; min-height: 100px;
      outline: none; transition: border-color .2s;
    }
    .sql-editor:focus { border-color: #e8b86d; }

    /* Badges */
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .7rem; font-weight: 700; }
    .badge-pk  { background: rgba(232,184,109,.15); color: #e8b86d; border: 1px solid rgba(232,184,109,.3); }
    .badge-fk  { background: rgba(124,58,237,.15);  color: #a78bfa; border: 1px solid rgba(124,58,237,.3); }
    .badge-idx { background: rgba(34,197,94,.15);   color: #86efac; border: 1px solid rgba(34,197,94,.3); }
    .badge-null{ background: rgba(100,116,139,.15); color: #94a3b8; }
    .badge-nn  { background: rgba(239,68,68,.1);    color: #fca5a5; }

    /* Pagination */
    .pagination { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 16px; }
    .pagination a, .pagination span {
      display: inline-flex; align-items: center; justify-content: center;
      width: 34px; height: 34px; border-radius: 8px; font-size: .82rem;
      border: 1px solid var(--border); color: #94a3b8; text-decoration: none; transition: .15s;
    }
    .pagination a:hover { border-color: #e8b86d; color: #e8b86d; }
    .pagination span.current { background: #e8b86d; color: #0a0a0f; border-color: #e8b86d; font-weight: 700; }

    /* Stats grid */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; }
    .stat-box .val { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; font-weight: 900; color: #e8b86d; }
    .stat-box .lbl { font-size: .7rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

    /* Error */
    .sql-error { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.25); border-radius: 8px; padding: 12px 16px; color: #fca5a5; font-family: 'JetBrains Mono', monospace; font-size: .82rem; margin-top: 12px; }
    .sql-success { background: rgba(34,197,94,.08); border: 1px solid rgba(34,197,94,.2); border-radius: 8px; padding: 10px 16px; color: #86efac; font-size: .82rem; margin-bottom: 12px; }

    @media(max-width:768px) {
      .db-sidebar { display: none; }
      .db-main { margin-left: 0; padding: 20px; }
    }
  </style>
</head>
<body>

<div class="db-wrap">

  <!-- Sidebar -->
  <aside class="db-sidebar">
    <div class="db-sidebar-logo">
      <span style="color:#e8b86d">Vienna</span>StateRP<br>
      <span style="font-family:'Rajdhani',sans-serif;font-size:.72rem;color:#64748b;font-weight:400">Datenbank Admin</span>
    </div>

    <div class="db-sidebar-section">Navigation</div>
    <a href="db_admin.php" class="<?= $action === 'overview' ? 'active' : '' ?>">📊 Übersicht</a>
    <a href="db_admin.php?action=query" class="<?= $action === 'query' ? 'active' : '' ?>">💻 SQL Query</a>

    <div class="db-sidebar-section" style="margin-top:12px">Tabellen (<?= count($tables) ?>)</div>
    <?php foreach ($tables as $t): ?>
    <a href="db_admin.php?action=table&table=<?= urlencode($t) ?>"
       class="<?= ($action === 'table' && $table === $t) ? 'active' : '' ?>">
      🗃 <?= htmlspecialchars($t) ?>
    </a>
    <?php endforeach; ?>

    <div class="db-sidebar-section" style="margin-top:12px">Links</div>
    <a href="admin.php">⚙️ Admin Panel</a>
    <a href="players.php">📡 Server Status</a>
    <a href="dashboard.php">← Dashboard</a>
  </aside>

  <!-- Main Content -->
  <main class="db-main">

    <?php if ($action === 'overview'): ?>
    <!-- ── Übersicht ─────────────────────────────────────────────────── -->
    <div class="db-header">
      <div class="db-title">📊 Datenbank <span style="color:#e8b86d">Übersicht</span></div>
      <a href="db_admin.php?action=query" class="btn btn-outline" style="font-size:.82rem">💻 SQL ausführen</a>
    </div>

    <?php
    // Schnell-Statistiken
    $totalRows = 0;
    foreach ($tables as $t) {
        $cnt = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $tableStats[$t]['row_count'] = $cnt;
        $totalRows += $cnt;
    }
    ?>
    <div class="stats-grid">
      <div class="stat-box"><div class="val"><?= count($tables) ?></div><div class="lbl">Tabellen</div></div>
      <div class="stat-box"><div class="val"><?= number_format($totalRows) ?></div><div class="lbl">Gesamt-Zeilen</div></div>
      <div class="stat-box"><div class="val"><?= htmlspecialchars($pdo->query("SELECT DATABASE()")->fetchColumn()) ?></div><div class="lbl">Datenbank</div></div>
      <div class="stat-box"><div class="val"><?= htmlspecialchars($pdo->query("SELECT VERSION()")->fetchColumn()) ?></div><div class="lbl">MySQL Version</div></div>
    </div>

    <div class="db-card">
      <div class="db-card-head">Alle Tabellen</div>
      <table class="db-table">
        <thead><tr>
          <th>Tabelle</th>
          <th>Zeilen (ca.)</th>
          <th>Größe (KB)</th>
          <th>Erstellt</th>
          <th>Aktion</th>
        </tr></thead>
        <tbody>
          <?php foreach ($tables as $t):
            $s = $tableStats[$t] ?? [];
            $cnt = $s['row_count'] ?? '–';
          ?>
          <tr>
            <td><strong><?= htmle($t) ?></strong></td>
            <td><?= $cnt ?></td>
            <td><?= $s['size_kb'] ?? '–' ?></td>
            <td><?= isset($s['create_time']) ? date('d.m.Y', strtotime($s['create_time'])) : '–' ?></td>
            <td>
              <a href="db_admin.php?action=table&table=<?= urlencode($t) ?>">Daten</a>
              · <a href="db_admin.php?action=table&table=<?= urlencode($t) ?>&view=structure">Struktur</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($action === 'table' && $tableData !== null): ?>
    <!-- ── Tabellen-Ansicht ──────────────────────────────────────────── -->
    <?php
    $viewMode = $_GET['view'] ?? 'data';
    $totalPages = $tableCount > 0 ? (int) ceil($tableCount / $limit) : 1;
    ?>
    <div class="db-header">
      <div>
        <div class="db-title">🗃 <span style="color:#e8b86d"><?= htmle($table) ?></span></div>
        <div style="font-size:.8rem;color:#64748b;margin-top:4px"><?= number_format($tableCount) ?> Zeilen gesamt</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="db_admin.php?action=table&table=<?= urlencode($table) ?>" class="btn btn-outline" style="font-size:.82rem;<?= $viewMode === 'data' ? 'border-color:#e8b86d;color:#e8b86d' : '' ?>">Daten</a>
        <a href="db_admin.php?action=table&table=<?= urlencode($table) ?>&view=structure" class="btn btn-outline" style="font-size:.82rem;<?= $viewMode === 'structure' ? 'border-color:#e8b86d;color:#e8b86d' : '' ?>">Struktur</a>
        <a href="db_admin.php" class="btn btn-outline" style="font-size:.82rem">← Übersicht</a>
      </div>
    </div>

    <?php if ($viewMode === 'structure'): ?>
    <div class="db-card">
      <div class="db-card-head">Tabellenstruktur – <?= htmle($table) ?></div>
      <table class="db-table">
        <thead><tr><th>Feld</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>
        <tbody>
          <?php foreach ($tableInfo as $col): ?>
          <tr>
            <td><strong><?= htmle($col['Field']) ?></strong></td>
            <td><?= htmle($col['Type']) ?></td>
            <td><?= $col['Null'] === 'YES' ? '<span class="badge badge-null">NULL</span>' : '<span class="badge badge-nn">NOT NULL</span>' ?></td>
            <td>
              <?php if ($col['Key'] === 'PRI') echo '<span class="badge badge-pk">PK</span>'; ?>
              <?php if ($col['Key'] === 'MUL') echo '<span class="badge badge-fk">FK</span>'; ?>
              <?php if ($col['Key'] === 'UNI') echo '<span class="badge badge-idx">UNI</span>'; ?>
            </td>
            <td><?= htmle($col['Default'] ?? '') ?></td>
            <td style="color:#94a3b8"><?= htmle($col['Extra']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php else: ?>
    <?php if (!empty($tableData)): ?>
    <div class="db-card" style="overflow-x:auto">
      <div class="db-card-head">
        Seite <?= $page ?> / <?= $totalPages ?> · <?= count($tableData) ?> von <?= number_format($tableCount) ?> Zeilen
      </div>
      <table class="db-table">
        <thead><tr>
          <?php foreach (array_keys($tableData[0]) as $col): ?>
          <th><?= htmle($col) ?></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php foreach ($tableData as $row): ?>
          <tr>
            <?php foreach ($row as $v): ?>
            <td title="<?= htmle($v) ?>"><?= htmle($v) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
      <a href="?action=table&table=<?= urlencode($table) ?>&p=<?= $page-1 ?>">&lsaquo;</a>
      <?php endif; ?>
      <?php
      $start = max(1, $page - 3);
      $end   = min($totalPages, $page + 3);
      for ($i = $start; $i <= $end; $i++):
      ?>
        <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
        <?php else: ?>
        <a href="?action=table&table=<?= urlencode($table) ?>&p=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?action=table&table=<?= urlencode($table) ?>&p=<?= $page+1 ?>">&rsaquo;</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div style="padding:40px;text-align:center;color:#64748b">Keine Daten in dieser Tabelle.</div>
    <?php endif; ?>
    <?php endif; ?>

    <?php elseif ($action === 'query'): ?>
    <!-- ── SQL Query ─────────────────────────────────────────────────── -->
    <div class="db-header">
      <div class="db-title">💻 SQL <span style="color:#e8b86d">Query</span></div>
      <a href="db_admin.php" class="btn btn-outline" style="font-size:.82rem">← Übersicht</a>
    </div>

    <div class="db-card">
      <div class="db-card-head">
        SQL ausführen
        <span style="font-size:.72rem;color:#f59e0b">⚠ Nur SELECT, SHOW, DESCRIBE, EXPLAIN erlaubt</span>
      </div>
      <div style="padding:20px">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
          <textarea name="sql" class="sql-editor" placeholder="SELECT * FROM users LIMIT 10;" <?= $canRunQuery ? '' : 'readonly' ?>><?= htmlspecialchars($querySql) ?></textarea>
          <div style="display:flex;gap:10px;margin-top:12px;align-items:center">
            <?php if ($canRunQuery): ?>
            <button type="submit" class="btn btn-primary">▶ Ausführen</button>
            <?php else: ?>
            <span style="font-size:.82rem;color:var(--text-3)">🔒 Keine Berechtigung, eigene SQL-Abfragen auszuführen</span>
            <?php endif; ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <?php foreach ($tables as $t): ?>
              <button type="button" onclick='insertTable(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
                style="background:rgba(255,255,255,.05);border:1px solid var(--border);color:#94a3b8;border-radius:6px;padding:4px 10px;font-size:.75rem;cursor:pointer;font-family:inherit"
              ><?= htmle($t) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        </form>

        <?php if ($queryError): ?>
        <div class="sql-error">✗ <?= htmlspecialchars($queryError) ?></div>
        <?php endif; ?>

        <?php if ($queryResult !== null): ?>
        <div class="sql-success">
          ✓ <?= number_format(count($queryResult['rows'])) ?> Zeile<?= count($queryResult['rows']) !== 1 ? 'n' : '' ?> zurückgegeben
        </div>

        <?php if (!empty($queryResult['rows'])): ?>
        <div style="overflow-x:auto">
          <table class="db-table">
            <thead><tr>
              <?php foreach ($queryResult['cols'] as $c): ?>
              <th><?= htmle($c) ?></th>
              <?php endforeach; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($queryResult['rows'] as $row): ?>
              <tr>
                <?php foreach ($row as $v): ?>
                <td title="<?= htmle($v) ?>"><?= htmle($v) ?></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p style="color:#64748b;font-size:.85rem">Keine Ergebnisse.</p>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Schnell-Queries -->
    <div class="db-card">
      <div class="db-card-head">Schnell-Queries</div>
      <div style="padding:16px;display:flex;flex-wrap:wrap;gap:8px">
        <?php
        $quickQueries = [
          'Alle User' => 'SELECT id, username, global_name, role, is_whitelisted, is_banned, last_login FROM users ORDER BY id DESC LIMIT 50',
          'Neue User (heute)' => "SELECT id, username, global_name, role, created_at FROM users WHERE DATE(created_at) = CURDATE()",
          'Gebannte User' => 'SELECT id, username, ban_reason, banned_at FROM users WHERE is_banned = 1',
          'Admins' => "SELECT id, username, global_name FROM users WHERE role IN ('admin','moderator')",
          'Login-Log' => 'SELECT l.id, u.username, l.method, l.logged_in_at FROM login_log l JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 30',
          'Activity Logs' => 'SELECT id, type, message, username, created_at FROM activity_logs ORDER BY id DESC LIMIT 30',
          'Rate Limits' => 'SELECT * FROM rate_limits ORDER BY last_attempt DESC LIMIT 20',
          'Server Status Log' => 'SELECT * FROM server_status_log ORDER BY snapshot_at DESC LIMIT 30',
          'Tabellen-Größen' => 'SELECT table_name, table_rows, ROUND((data_length+index_length)/1024,1) AS kb FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY kb DESC',
        ];
        foreach ($quickQueries as $label => $sql): ?>
        <button onclick="setQuery(<?= htmlspecialchars(json_encode($sql), ENT_QUOTES) ?>)"
          style="background:rgba(255,255,255,.04);border:1px solid var(--border);color:#94a3b8;border-radius:8px;padding:6px 14px;font-size:.8rem;cursor:pointer;font-family:inherit;transition:.15s"
          onmouseover="this.style.borderColor='#e8b86d';this.style.color='#e8b86d'"
          onmouseout="this.style.borderColor='rgba(255,255,255,.08)';this.style.color='#94a3b8'"
        ><?= htmle($label) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php endif; ?>
  </main>
</div>

<script>
function setQuery(sql) {
  document.querySelector('textarea[name="sql"]').value = sql;
  document.querySelector('textarea[name="sql"]').focus();
}
function insertTable(name) {
  const ta = document.querySelector('textarea[name="sql"]');
  const q = 'SELECT * FROM `' + name + '` LIMIT 50';
  ta.value = q;
  ta.focus();
}
</script>
</body>
</html>