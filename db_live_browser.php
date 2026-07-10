<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_permission('admin')) {
    header('Location: dashboard.php?error=' . urlencode('Kein Zugriff – Admin-Rolle erforderlich'));
    exit;
}

header('Location: admin.php?notice=' . urlencode('Die direkte Spielserver-DB-Anbindung wurde deaktiviert.'));
exit;

$liveDb = live_db_connect();
if (!$liveDb) {
    die('<div style="font-family:sans-serif;padding:60px;color:#fca5a5;background:#0a0a0f;min-height:100vh">
        <h2>⚠ DB_LIVE nicht erreichbar</h2>
        <p style="color:#94a3b8">Verbindung mit <code>live_db_connect()</code> fehlgeschlagen.<br>
        Bitte <code>DB_LIVE_*</code> Credentials in der <code>.env</code> prüfen.</p>
        <a href="admin_live_db.php" style="color:#e8b86d">← Zurück zu DB_LIVE Sync</a>
    </div>');
}

$liveDbName = env('DB_LIVE_NAME', 'DBLIVE');
$action     = $_GET['action'] ?? 'overview';
$table      = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table'] ?? '');
$page       = max(1, (int) ($_GET['p'] ?? 1));
$limit      = 50;
$offset     = ($page - 1) * $limit;

// ── Alle Tabellen laden ───────────────────────────────────────────────────────
$tables = $liveDb->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// ── SQL-Query ausführen (nur SELECT/SHOW/DESCRIBE) ────────────────────────────
$queryResult = null;
$queryError  = null;
$querySql    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $queryError = 'Ungültige Anfrage (CSRF).';
    } else {
        $querySql  = trim($_POST['sql'] ?? '');
        $firstWord = strtoupper(strtok($querySql, " \t\n\r"));
        if (!in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'DESC'], true)) {
            $queryError = 'Nur SELECT, SHOW, DESCRIBE und EXPLAIN sind erlaubt.';
        } else {
            try {
                $stmt = $liveDb->query($querySql);
                $queryResult = ['cols' => [], 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
                $queryResult['cols'] = !empty($queryResult['rows']) ? array_keys($queryResult['rows'][0]) : [];
            } catch (\PDOException $e) {
                $queryError = $e->getMessage();
            }
        }
    }
}

// ── Tabellen-Daten laden ──────────────────────────────────────────────────────
$tableData  = null;
$tableInfo  = null;
$tableCount = 0;

if ($action === 'table' && $table && in_array($table, $tables)) {
    $tableInfo  = $liveDb->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $tableCount = (int) $liveDb->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $tableData  = $liveDb->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
}

// ── Tabellen-Statistiken ──────────────────────────────────────────────────────
$tableStats = [];
if ($action === 'overview') {
    $stats = $liveDb->query("
        SELECT TABLE_NAME, TABLE_ROWS,
               ROUND((data_length + index_length) / 1024, 1) AS size_kb,
               CREATE_TIME, UPDATE_TIME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stats as $s) {
        $tableStats[$s['TABLE_NAME']] = $s;
    }
}

function ldb_html(mixed $v): string {
    if ($v === null) return '<span style="color:#64748b;font-style:italic">NULL</span>';
    return htmlspecialchars((string)$v, ENT_QUOTES);
}

$dbVersion = $liveDb->query("SELECT VERSION()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DB_LIVE Browser – ViennaStateRP Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    body { padding-top: 0; }
    .db-wrap { display: flex; min-height: 100vh; }

    /* Sidebar */
    .db-sidebar {
      width: 230px; flex-shrink: 0;
      background: var(--bg-card); border-right: 1px solid var(--border);
      padding: 24px 0; position: fixed; top: 0; left: 0; bottom: 0;
      overflow-y: auto; z-index: 10;
    }
    .db-sidebar-logo { font-family: 'Orbitron', sans-serif; font-size: .85rem; font-weight: 900; padding: 0 18px 16px; border-bottom: 1px solid var(--border); margin-bottom: 14px; line-height: 1.5; }
    .db-sidebar-logo .sub { font-family: 'Rajdhani', sans-serif; font-size: .72rem; color: #64748b; font-weight: 400; }
    .db-sidebar-section { font-size: .63rem; text-transform: uppercase; letter-spacing: 2px; color: #4b5563; padding: 10px 18px 5px; }
    .db-sidebar a { display: flex; align-items: center; gap: 8px; padding: 7px 18px; font-size: .82rem; color: #94a3b8; text-decoration: none; border-left: 2px solid transparent; transition: all .15s; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .db-sidebar a:hover { color: #e2e8f0; background: rgba(255,255,255,.04); }
    .db-sidebar a.active { color: #86efac; border-left-color: #86efac; background: rgba(134,239,172,.06); }
    .db-badge { display: inline-block; font-size: .62rem; padding: 1px 5px; border-radius: 3px; background: rgba(134,239,172,.15); color: #86efac; border: 1px solid rgba(134,239,172,.25); margin-left: auto; flex-shrink: 0; }

    /* Main */
    .db-main { margin-left: 230px; flex: 1; padding: 32px; min-width: 0; }
    .db-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
    .db-title { font-family: 'Orbitron', sans-serif; font-size: 1.3rem; font-weight: 900; }
    .db-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
    .db-card-head { padding: 13px 20px; border-bottom: 1px solid var(--border); font-size: .76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #64748b; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .db-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .db-table th { background: rgba(255,255,255,.03); padding: 9px 14px; text-align: left; color: #64748b; font-size: .68rem; text-transform: uppercase; letter-spacing: 1px; white-space: nowrap; border-bottom: 1px solid var(--border); }
    .db-table td { padding: 7px 14px; border-bottom: 1px solid rgba(255,255,255,.04); color: #e2e8f0; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: 'JetBrains Mono', monospace; font-size: .76rem; }
    .db-table tr:last-child td { border-bottom: none; }
    .db-table tr:hover td { background: rgba(255,255,255,.025); }
    .db-table a { color: #86efac; text-decoration: none; }
    .db-table a:hover { text-decoration: underline; }
    .sql-editor { width: 100%; font-family: 'JetBrains Mono', monospace; font-size: .85rem; background: #0a0a0f; border: 1px solid var(--border); border-radius: 8px; color: #e2e8f0; padding: 14px 16px; resize: vertical; min-height: 110px; outline: none; transition: border-color .2s; }
    .sql-editor:focus { border-color: #86efac; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .7rem; font-weight: 700; }
    .badge-pk  { background: rgba(232,184,109,.15); color: #e8b86d; border: 1px solid rgba(232,184,109,.3); }
    .badge-fk  { background: rgba(124,58,237,.15);  color: #a78bfa; border: 1px solid rgba(124,58,237,.3); }
    .badge-idx { background: rgba(34,197,94,.15);   color: #86efac; border: 1px solid rgba(34,197,94,.3); }
    .badge-null{ background: rgba(100,116,139,.15); color: #94a3b8; }
    .badge-nn  { background: rgba(239,68,68,.1);    color: #fca5a5; }
    .pagination { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 16px; }
    .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; font-size: .82rem; border: 1px solid var(--border); color: #94a3b8; text-decoration: none; transition: .15s; }
    .pagination a:hover { border-color: #86efac; color: #86efac; }
    .pagination span.current { background: #86efac; color: #0a0a0f; border-color: #86efac; font-weight: 700; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; }
    .stat-box .val { font-family: 'Orbitron', sans-serif; font-size: 1.3rem; font-weight: 900; color: #86efac; }
    .stat-box .lbl { font-size: .68rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
    .sql-error   { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.25); border-radius: 8px; padding: 12px 16px; color: #fca5a5; font-family: 'JetBrains Mono', monospace; font-size: .82rem; margin-top: 12px; }
    .sql-success { background: rgba(134,239,172,.08); border: 1px solid rgba(134,239,172,.2); border-radius: 8px; padding: 10px 16px; color: #86efac; font-size: .82rem; margin-bottom: 12px; }
    .quick-btn { background: rgba(255,255,255,.04); border: 1px solid var(--border); color: #94a3b8; border-radius: 8px; padding: 6px 14px; font-size: .78rem; cursor: pointer; font-family: inherit; transition: .15s; }
    .quick-btn:hover { border-color: #86efac; color: #86efac; }
    @media(max-width:768px) { .db-sidebar { display: none; } .db-main { margin-left: 0; padding: 20px; } }
  </style>
</head>
<body>
<div class="db-wrap">

  <!-- ── Sidebar ─────────────────────────────────────────────────────── -->
  <aside class="db-sidebar">
    <div class="db-sidebar-logo">
      <span style="color:#86efac">DB_LIVE</span> Browser<br>
      <span class="sub"><?= htmlspecialchars($liveDbName) ?> · FiveM Daten</span>
    </div>

    <div class="db-sidebar-section">Navigation</div>
    <a href="db_live_browser.php" class="<?= $action === 'overview' ? 'active' : '' ?>">📊 Übersicht</a>
    <a href="db_live_browser.php?action=query" class="<?= $action === 'query' ? 'active' : '' ?>">💻 SQL Query</a>

    <div class="db-sidebar-section" style="margin-top:10px">Tabellen <span class="db-badge"><?= count($tables) ?></span></div>
    <?php foreach ($tables as $t): ?>
    <a href="db_live_browser.php?action=table&table=<?= urlencode($t) ?>"
       class="<?= ($action === 'table' && $table === $t) ? 'active' : '' ?>"
       title="<?= htmlspecialchars($t) ?>">
      🗃 <?= htmlspecialchars($t) ?>
    </a>
    <?php endforeach; ?>

    <div class="db-sidebar-section" style="margin-top:10px">Admin</div>
    <a href="admin_live_db.php">🔄 DB_LIVE Sync</a>
    <a href="fivem_sync.php">⚙ FiveM Config</a>
    <a href="db_admin.php">🗄 Website-DB</a>
    <a href="admin.php">← Admin Panel</a>
  </aside>

  <!-- ── Main Content ────────────────────────────────────────────────── -->
  <main class="db-main">

  <?php if ($action === 'overview'): ?>
  <!-- ── Übersicht ─────────────────────────────────────────────── -->
  <div class="db-header">
    <div>
      <div class="db-title">📊 <span style="color:#86efac"><?= htmlspecialchars($liveDbName) ?></span> Übersicht</div>
      <div style="font-size:.78rem;color:#64748b;margin-top:4px">FiveM-Spiegeldatenbank · Read-only Ansicht</div>
    </div>
    <a href="db_live_browser.php?action=query" class="btn btn-outline" style="font-size:.82rem;border-color:#86efac;color:#86efac">💻 SQL ausführen</a>
  </div>

  <?php
  $totalRows = 0;
  foreach ($tables as $t) {
      try {
          $cnt = (int) $liveDb->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
      } catch (\Throwable $e) { $cnt = 0; }
      $tableStats[$t]['row_count'] = $cnt;
      $totalRows += $cnt;
  }
  ?>
  <div class="stats-grid">
    <div class="stat-box"><div class="val"><?= count($tables) ?></div><div class="lbl">Tabellen</div></div>
    <div class="stat-box"><div class="val"><?= number_format($totalRows) ?></div><div class="lbl">Gesamt-Zeilen</div></div>
    <div class="stat-box"><div class="val"><?= htmlspecialchars($liveDbName) ?></div><div class="lbl">Datenbank</div></div>
    <div class="stat-box"><div class="val"><?= htmlspecialchars($dbVersion) ?></div><div class="lbl">MySQL Version</div></div>
  </div>

  <div class="db-card">
    <div class="db-card-head">Alle Tabellen in <?= htmlspecialchars($liveDbName) ?></div>
    <table class="db-table">
      <thead><tr>
        <th>Tabelle</th><th>Zeilen</th><th>Größe (KB)</th><th>Zuletzt aktualisiert</th><th>Aktion</th>
      </tr></thead>
      <tbody>
        <?php foreach ($tables as $t):
          $s   = $tableStats[$t] ?? [];
          $cnt = $s['row_count'] ?? '–';
        ?>
        <tr>
          <td><strong><?= ldb_html($t) ?></strong></td>
          <td><?= number_format((int)$cnt) ?></td>
          <td><?= $s['size_kb'] ?? '–' ?></td>
          <td style="color:#64748b"><?= isset($s['UPDATE_TIME']) && $s['UPDATE_TIME'] ? date('d.m.Y H:i', strtotime($s['UPDATE_TIME'])) : '–' ?></td>
          <td>
            <a href="db_live_browser.php?action=table&table=<?= urlencode($t) ?>">Daten</a>
            &nbsp;·&nbsp;
            <a href="db_live_browser.php?action=table&table=<?= urlencode($t) ?>&view=structure">Struktur</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($action === 'table' && $tableData !== null): ?>
  <!-- ── Tabellen-Ansicht ──────────────────────────────────────── -->
  <?php
  $viewMode   = $_GET['view'] ?? 'data';
  $totalPages = $tableCount > 0 ? (int) ceil($tableCount / $limit) : 1;
  ?>
  <div class="db-header">
    <div>
      <div class="db-title">🗃 <span style="color:#86efac"><?= ldb_html($table) ?></span></div>
      <div style="font-size:.78rem;color:#64748b;margin-top:4px"><?= number_format($tableCount) ?> Zeilen · <?= htmlspecialchars($liveDbName) ?></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="db_live_browser.php?action=table&table=<?= urlencode($table) ?>" class="btn btn-outline" style="font-size:.82rem;<?= $viewMode === 'data' ? 'border-color:#86efac;color:#86efac' : '' ?>">Daten</a>
      <a href="db_live_browser.php?action=table&table=<?= urlencode($table) ?>&view=structure" class="btn btn-outline" style="font-size:.82rem;<?= $viewMode === 'structure' ? 'border-color:#86efac;color:#86efac' : '' ?>">Struktur</a>
      <a href="db_live_browser.php" class="btn btn-outline" style="font-size:.82rem">← Übersicht</a>
    </div>
  </div>

  <?php if ($viewMode === 'structure'): ?>
  <div class="db-card">
    <div class="db-card-head">Tabellenstruktur – <?= ldb_html($table) ?></div>
    <table class="db-table">
      <thead><tr><th>Feld</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>
      <tbody>
        <?php foreach ($tableInfo as $col): ?>
        <tr>
          <td><strong><?= ldb_html($col['Field']) ?></strong></td>
          <td><?= ldb_html($col['Type']) ?></td>
          <td><?= $col['Null'] === 'YES' ? '<span class="badge badge-null">NULL</span>' : '<span class="badge badge-nn">NOT NULL</span>' ?></td>
          <td>
            <?php if ($col['Key'] === 'PRI') echo '<span class="badge badge-pk">PK</span>'; ?>
            <?php if ($col['Key'] === 'MUL') echo '<span class="badge badge-fk">IDX</span>'; ?>
            <?php if ($col['Key'] === 'UNI') echo '<span class="badge badge-idx">UNI</span>'; ?>
          </td>
          <td><?= ldb_html($col['Default'] ?? '') ?></td>
          <td style="color:#94a3b8"><?= ldb_html($col['Extra']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php else: ?>
  <?php if (!empty($tableData)): ?>
  <div class="db-card" style="overflow-x:auto">
    <div class="db-card-head">
      Seite <?= $page ?> / <?= $totalPages ?> &nbsp;·&nbsp; <?= count($tableData) ?> von <?= number_format($tableCount) ?> Zeilen
      <a href="db_live_browser.php?action=query" onclick="setTimeout(()=>document.querySelector('textarea[name=sql]').value='SELECT * FROM `<?= addslashes($table) ?>` LIMIT 50',50)" style="font-size:.72rem;color:#86efac;font-weight:400">SQL öffnen ↗</a>
    </div>
    <table class="db-table">
      <thead><tr>
        <?php foreach (array_keys($tableData[0]) as $col): ?>
        <th><?= ldb_html($col) ?></th>
        <?php endforeach; ?>
      </tr></thead>
      <tbody>
        <?php foreach ($tableData as $row): ?>
        <tr>
          <?php foreach ($row as $v): ?>
          <td title="<?= ldb_html($v) ?>"><?= ldb_html($v) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?action=table&table=<?= urlencode($table) ?>&p=<?= $page-1 ?>">&lsaquo;</a><?php endif; ?>
    <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
      <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="?action=table&table=<?= urlencode($table) ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?action=table&table=<?= urlencode($table) ?>&p=<?= $page+1 ?>">&rsaquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div style="padding:40px;text-align:center;color:#64748b">Keine Daten in dieser Tabelle.</div>
  <?php endif; ?>
  <?php endif; ?>

  <?php elseif ($action === 'query'): ?>
  <!-- ── SQL Query ─────────────────────────────────────────────── -->
  <div class="db-header">
    <div>
      <div class="db-title">💻 SQL <span style="color:#86efac">Query</span></div>
      <div style="font-size:.78rem;color:#64748b;margin-top:4px">Datenbank: <code style="color:#86efac"><?= htmlspecialchars($liveDbName) ?></code></div>
    </div>
    <a href="db_live_browser.php" class="btn btn-outline" style="font-size:.82rem">← Übersicht</a>
  </div>

  <div class="db-card">
    <div class="db-card-head">
      SQL ausführen
      <span style="font-size:.72rem;color:#f59e0b">⚠ Nur SELECT, SHOW, DESCRIBE, EXPLAIN erlaubt</span>
    </div>
    <div style="padding:20px">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
        <textarea name="sql" class="sql-editor" placeholder="SELECT * FROM players LIMIT 50;"><?= htmlspecialchars($querySql) ?></textarea>
        <div style="display:flex;gap:10px;margin-top:12px;align-items:flex-start;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary" style="border-color:#86efac;background:rgba(134,239,172,.1);color:#86efac">▶ Ausführen</button>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php foreach ($tables as $t): ?>
            <button type="button" onclick="insertTable('<?= addslashes($t) ?>')"
              style="background:rgba(134,239,172,.05);border:1px solid rgba(134,239,172,.2);color:#86efac;border-radius:6px;padding:4px 10px;font-size:.73rem;cursor:pointer;font-family:'JetBrains Mono',monospace"
            ><?= ldb_html($t) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </form>

      <?php if ($queryError): ?>
      <div class="sql-error">✗ <?= htmlspecialchars($queryError) ?></div>
      <?php endif; ?>

      <?php if ($queryResult !== null): ?>
      <div class="sql-success">✓ <?= number_format(count($queryResult['rows'])) ?> Zeile<?= count($queryResult['rows']) !== 1 ? 'n' : '' ?> zurückgegeben</div>
      <?php if (!empty($queryResult['rows'])): ?>
      <div style="overflow-x:auto">
        <table class="db-table">
          <thead><tr><?php foreach ($queryResult['cols'] as $c): ?><th><?= ldb_html($c) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($queryResult['rows'] as $row): ?>
            <tr><?php foreach ($row as $v): ?><td title="<?= ldb_html($v) ?>"><?= ldb_html($v) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?><p style="color:#64748b;font-size:.85rem">Keine Ergebnisse.</p><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- FiveM Schnell-Queries -->
  <div class="db-card">
    <div class="db-card-head">⚡ FiveM Schnell-Queries</div>
    <div style="padding:16px;display:flex;flex-wrap:wrap;gap:8px">
      <?php
      $quickQueries = [
          'Alle Spieler'            => 'SELECT * FROM players ORDER BY id DESC LIMIT 50',
          'Online Spieler'          => "SELECT * FROM players WHERE online = 1 ORDER BY id DESC LIMIT 50",
          'Spieler nach Geld'       => 'SELECT identifier, name, money, bank, dirty_money FROM players ORDER BY (money + bank) DESC LIMIT 50',
          'Jobs / Berufe'           => 'SELECT identifier, job, job_grade, job_grade_name FROM players ORDER BY job LIMIT 50',
          'Alle Fahrzeuge'          => 'SELECT plate, model, owner FROM owned_vehicles ORDER BY id DESC LIMIT 50',
          'Fahrzeuge nach Besitzer' => 'SELECT owner, COUNT(*) AS fahrzeuge FROM owned_vehicles GROUP BY owner ORDER BY fahrzeuge DESC LIMIT 30',
          'Inventar (Top)'          => 'SELECT identifier, inventory FROM players WHERE inventory IS NOT NULL LIMIT 20',
          'Alle Tabellen + Größe'   => 'SELECT TABLE_NAME, TABLE_ROWS, ROUND((data_length+index_length)/1024,1) AS kb FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY kb DESC',
          'Gesellschaften'          => 'SELECT * FROM player_companies ORDER BY id DESC LIMIT 30',
          'Apartements/Häuser'      => 'SELECT * FROM player_houses ORDER BY id DESC LIMIT 30',
          'Bankkonten'              => 'SELECT * FROM bank_accounts ORDER BY id DESC LIMIT 30',
          'Letzte Transaktionen'    => 'SELECT * FROM transactions ORDER BY id DESC LIMIT 30',
      ];
      foreach ($quickQueries as $label => $sql): ?>
      <button class="quick-btn" onclick="setQuery(<?= json_encode($sql) ?>)"><?= htmlspecialchars($label) ?></button>
      <?php endforeach; ?>
    </div>
    <div style="padding:0 16px 14px;font-size:.72rem;color:#4b5563">
      ⚠ Tabellennamen sind abhängig von der FiveM-Framework-Installation (ESX, QB-Core, etc.).
      Nutze die Tabellenübersicht links um die verfügbaren Tabellen zu sehen.
    </div>
  </div>

  <?php else: ?>
  <div style="padding:40px;color:#64748b">Ungültige Aktion.</div>
  <?php endif; ?>

  </main>
</div>

<script>
function setQuery(sql) {
  document.querySelector('textarea[name="sql"]').value = sql;
  document.querySelector('textarea[name="sql"]').focus();
  document.querySelector('textarea[name="sql"]').scrollIntoView({behavior:'smooth',block:'center'});
}
function insertTable(name) {
  document.querySelector('textarea[name="sql"]').value = 'SELECT * FROM `' + name + '` LIMIT 50';
  document.querySelector('textarea[name="sql"]').focus();
}
</script>
</body>
</html>
