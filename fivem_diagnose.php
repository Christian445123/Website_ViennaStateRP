<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/fivem_api.php';

// Nur Admins oder mit Secret-Key
$secret = env('DIAG_SECRET', '');
$ok = has_permission('admin')
   || ($secret !== '' && hash_equals($secret, ($_GET['key'] ?? '')));

if (!$ok) {
    http_response_code(403);
    die('403 Forbidden - Admin-Login oder ?key=DIAG_SECRET erforderlich');
}

// Alle Tests direkt mit curl – keine Klassen-Abstraktion
$base = defined('FIVEM_API_URL')
    ? rtrim(FIVEM_API_URL, '/')
    : ('http://' . (defined('FIVEM_IP') ? FIVEM_IP : 'fivem.viennastaterpx.at')
       . ':' . (defined('FIVEM_PORT') ? FIVEM_PORT : '30120'));

function diag_curl(string $url, string $method = 'GET', ?string $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT      => 'VSRP-Diagnose/1.0',
        CURLOPT_FOLLOWLOCATION => true,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = $body ?? '';
    }
    if ($headers) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);

    $result  = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time    = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    $ip      = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    $errNo   = curl_errno($ch);
    $errMsg  = curl_error($ch);
    curl_close($ch);

    $json = null;
    if ($result !== false && $result !== '') {
        $json = json_decode($result, true);
    }

    return [
        'url'      => $url,
        'method'   => $method,
        'http'     => $code,
        'ms'       => $time,
        'ip'       => $ip,
        'curl_err' => $errNo,
        'curl_msg' => $errMsg,
        'ok'       => ($result !== false && $code === 200 && $json !== null),
        'raw'      => $result === false ? null : substr((string)$result, 0, 2000),
        'json'     => $json,
    ];
}

// DNS-Aufloesung testen
$host   = parse_url($base, PHP_URL_HOST) ?? '';
$hostIp = gethostbyname($host);
$dnsOk  = ($hostIp !== $host); // gethostbyname gibt Host zurueck wenn Fehler

// Alle Tests sammeln
$tests = [];

// 1. dynamic.json
$t = diag_curl($base . '/dynamic.json');
$t['label'] = '/dynamic.json – Spieler, Map, Gametype';
$t['fields'] = $t['json'] ? array_keys($t['json']) : [];
$tests[] = $t;

// 2. info.json
$t = diag_curl($base . '/info.json');
$t['label'] = '/info.json – Server-Name, Ressourcen, Vars, Icon';
$t['fields'] = $t['json'] ? array_keys($t['json']) : [];
// Icon aus Raw-Anzeige rausnehmen (zu gross)
if (isset($t['json']['icon'])) {
    $t['json']['icon'] = '[base64, ' . round(strlen($t['json']['icon']) / 1024, 1) . ' KB]';
}
$tests[] = $t;

// 3. players.json
$t = diag_curl($base . '/players.json');
$t['label'] = '/players.json – Online-Spieler';
$count = is_array($t['json']) ? count($t['json']) : 0;
$t['extra'] = $count . ' Spieler';
// Spielernamen nicht anzeigen
if (is_array($t['json'])) {
    $t['json'] = array_map(function($p) {
        unset($p['name']);
        return $p;
    }, $t['json']);
}
$tests[] = $t;

// 4. Bridge – Ping (falls Secret gesetzt)
$bridgeSecret = defined('FIVEM_BRIDGE_SECRET') ? FIVEM_BRIDGE_SECRET : '';
$bridgeUrl    = $base . '/vsrp_bridge/vsrp';
if ($bridgeSecret !== '') {
    $payload   = json_encode(['action' => 'ping', 'timestamp' => time(), 'secret' => $bridgeSecret]);
    $signature = hash_hmac('sha256', $payload, $bridgeSecret);
    $t = diag_curl($bridgeUrl, 'POST', $payload, [
        'Content-Type: application/json',
        'X-VSRP-Signature: ' . $signature,
        'X-VSRP-Timestamp: ' . time(),
    ]);
    $t['label'] = 'Bridge /vsrp – Ping';
    $tests[] = $t;

    // 5. Bridge – job_stats
    $payload   = json_encode(['action' => 'job_stats', 'timestamp' => time(), 'secret' => $bridgeSecret]);
    $signature = hash_hmac('sha256', $payload, $bridgeSecret);
    $t = diag_curl($bridgeUrl, 'POST', $payload, [
        'Content-Type: application/json',
        'X-VSRP-Signature: ' . $signature,
        'X-VSRP-Timestamp: ' . time(),
    ]);
    $t['label'] = 'Bridge /vsrp – job_stats';
    $tests[] = $t;
} else {
    $tests[] = [
        'label'    => 'Bridge /vsrp – nicht konfiguriert',
        'ok'       => null,
        'http'     => null,
        'ms'       => null,
        'curl_err' => 0,
        'curl_msg' => 'FIVEM_BRIDGE_SECRET nicht gesetzt in .env',
        'json'     => null,
        'raw'      => null,
    ];
}

$allOk = $dnsOk && $tests[0]['ok'] && $tests[1]['ok'] && $tests[2]['ok'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FiveM Diagnose – ViennaStateRP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    body { padding: 40px 0 80px; }
    .diag-wrap { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
    .diag-title { font-family: 'Orbitron', sans-serif; font-size: 2rem; font-weight: 900; margin-bottom: 6px; }
    .diag-sub { color: var(--text-2); font-size: .9rem; margin-bottom: 32px; }

    /* Summary */
    .diag-summary {
      display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 32px;
    }
    .sum-card {
      flex: 1; min-width: 160px;
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: 12px; padding: 16px 20px;
    }
    .sum-val { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; font-weight: 900; }
    .sum-lbl { font-size: .72rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; }
    .ok   { color: #22c55e; }
    .warn { color: #f59e0b; }
    .fail { color: #ef4444; }
    .skip { color: #64748b; }

    /* Test cards */
    .test-card {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: 14px; margin-bottom: 16px; overflow: hidden;
    }
    .test-header {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 20px; cursor: pointer;
      user-select: none;
    }
    .test-header:hover { background: rgba(255,255,255,.03); }
    .test-status { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .test-status.ok   { background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,.6); }
    .test-status.fail { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,.6); }
    .test-status.skip { background: #64748b; }
    .test-label { font-weight: 600; font-size: .95rem; flex: 1; }
    .test-meta  { font-size: .8rem; color: var(--text-3); display: flex; gap: 16px; flex-wrap: wrap; }
    .test-chevron { color: var(--text-3); transition: transform .2s; }
    .test-card.open .test-chevron { transform: rotate(180deg); }

    .test-body { display: none; padding: 0 20px 20px; border-top: 1px solid var(--border); }
    .test-card.open .test-body { display: block; }

    /* Error box */
    .err-box {
      background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.25);
      border-radius: 8px; padding: 12px 16px; margin: 12px 0;
      font-family: 'JetBrains Mono', monospace; font-size: .82rem; color: #fca5a5;
    }

    /* JSON tree */
    .json-wrap {
      background: rgba(255,255,255,.03); border: 1px solid var(--border);
      border-radius: 8px; padding: 14px 16px; margin-top: 12px;
      font-family: 'JetBrains Mono', monospace; font-size: .78rem;
      line-height: 1.6; overflow-x: auto; max-height: 500px; overflow-y: auto;
      white-space: pre-wrap; word-break: break-all;
    }
    .json-key   { color: #94a3b8; }
    .json-str   { color: #86efac; }
    .json-num   { color: #fbbf24; }
    .json-bool  { color: #c084fc; }
    .json-null  { color: #f87171; }

    /* Fields list */
    .fields-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .field-tag {
      background: rgba(255,255,255,.05); border: 1px solid var(--border);
      border-radius: 4px; padding: 2px 8px;
      font-family: 'JetBrains Mono', monospace; font-size: .72rem; color: var(--text-2);
    }

    .refresh-btn { float: right; font-size: .8rem; }
    .dns-row { display: flex; gap: 16px; align-items: center; margin-bottom: 24px; flex-wrap: wrap; }
    .dns-card {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: 10px; padding: 10px 18px;
      font-family: 'JetBrains Mono', monospace; font-size: .82rem;
    }
    .dns-card span { color: var(--text-3); margin-right: 8px; }
  </style>
</head>
<body>
<div class="diag-wrap">

  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:8px">
    <div>
      <div class="diag-title">🔬 FiveM <span class="accent">Diagnose</span></div>
      <div class="diag-sub">Vollständige Verbindungsdiagnose · <?= date('d.m.Y H:i:s') ?></div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="?" class="btn btn-outline" style="font-size:.82rem">↻ Neu laden</a>
      <a href="dashboard.php" class="btn btn-ghost" style="font-size:.82rem">← Dashboard</a>
    </div>
  </div>

  <!-- DNS + Verbindungsinfos -->
  <div class="dns-row">
    <div class="dns-card"><span>API-URL</span><?= htmlspecialchars($base) ?></div>
    <div class="dns-card"><span>Host</span><?= htmlspecialchars($host) ?></div>
    <div class="dns-card <?= $dnsOk ? 'ok' : 'fail' ?>">
      <span>DNS</span><?= $dnsOk ? '✓ ' . htmlspecialchars($hostIp) : '✗ Nicht aufgelöst' ?>
    </div>
    <div class="dns-card"><span>PHP</span><?= phpversion() ?></div>
    <div class="dns-card"><span>curl</span><?= curl_version()['version'] ?></div>
  </div>

  <!-- Summary -->
  <div class="diag-summary">
    <div class="sum-card">
      <div class="sum-val <?= $allOk ? 'ok' : 'fail' ?>"><?= $allOk ? '✓ OK' : '✗ FEHLER' ?></div>
      <div class="sum-lbl">Gesamtstatus</div>
    </div>
    <?php if ($tests[0]['json']): ?>
    <div class="sum-card">
      <div class="sum-val ok"><?= (int)($tests[0]['json']['clients'] ?? 0) ?></div>
      <div class="sum-lbl">Aktive Spieler</div>
    </div>
    <div class="sum-card">
      <div class="sum-val"><?= (int)($tests[0]['json']['sv_maxclients'] ?? 0) ?></div>
      <div class="sum-lbl">Max Slots</div>
    </div>
    <div class="sum-card">
      <div class="sum-val" style="font-size:1rem"><?= htmlspecialchars($tests[0]['json']['mapname'] ?? '–') ?></div>
      <div class="sum-lbl">Map</div>
    </div>
    <?php endif; ?>
    <?php if ($tests[1]['json']): ?>
    <div class="sum-card">
      <div class="sum-val"><?= count($tests[1]['json']['resources'] ?? []) ?></div>
      <div class="sum-lbl">Ressourcen</div>
    </div>
    <?php endif; ?>
    <?php foreach ($tests as $t): ?>
    <div class="sum-card">
      <div class="sum-val <?= $t['ok'] === true ? 'ok' : ($t['ok'] === false ? 'fail' : 'skip') ?>">
        <?= $t['ok'] === true ? '✓' : ($t['ok'] === false ? '✗' : '–') ?>
        <?= $t['http'] ?? '–' ?>
      </div>
      <div class="sum-lbl"><?= htmlspecialchars(explode(' – ', $t['label'])[0]) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Test-Karten -->
  <?php foreach ($tests as $i => $t):
    $statusClass = $t['ok'] === true ? 'ok' : ($t['ok'] === false ? 'fail' : 'skip');
    $openDefault = ($t['ok'] !== true); // Fehler direkt aufgeklappt
  ?>
  <div class="test-card <?= $openDefault ? 'open' : '' ?>" id="card<?= $i ?>">
    <div class="test-header" onclick="toggle(<?= $i ?>)">
      <span class="test-status <?= $statusClass ?>"></span>
      <span class="test-label"><?= htmlspecialchars($t['label']) ?></span>
      <span class="test-meta">
        <?php if ($t['http'] !== null): ?>
          <span>HTTP <?= $t['http'] ?></span>
        <?php endif; ?>
        <?php if ($t['ms'] !== null): ?>
          <span><?= $t['ms'] ?>ms</span>
        <?php endif; ?>
        <?php if (!empty($t['ip'])): ?>
          <span>IP: <?= htmlspecialchars($t['ip']) ?></span>
        <?php endif; ?>
        <?php if (!empty($t['extra'])): ?>
          <span><?= htmlspecialchars($t['extra']) ?></span>
        <?php endif; ?>
      </span>
      <span class="test-chevron">▼</span>
    </div>

    <div class="test-body">

      <?php if ($t['curl_err'] || $t['curl_msg']): ?>
      <div class="err-box">
        <?php if ($t['curl_err']): ?>
        cURL Fehler #<?= $t['curl_err'] ?>: <?= htmlspecialchars($t['curl_msg']) ?>
        <?php else: ?>
        Hinweis: <?= htmlspecialchars($t['curl_msg']) ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($t['http'] && $t['http'] !== 200): ?>
      <div class="err-box">HTTP Status <?= $t['http'] ?> – Server antwortete nicht mit 200 OK</div>
      <?php endif; ?>

      <?php if ($t['json'] === null && $t['raw'] !== null): ?>
      <div class="err-box">Antwort ist kein gültiges JSON:<br><?= htmlspecialchars(substr($t['raw'], 0, 300)) ?></div>
      <?php endif; ?>

      <?php if (!empty($t['fields'])): ?>
      <div style="margin-top:8px;font-size:.78rem;color:var(--text-3)">Felder im Response:</div>
      <div class="fields-list">
        <?php foreach ($t['fields'] as $f): ?>
        <span class="field-tag"><?= htmlspecialchars($f) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($t['json'] !== null): ?>
      <div class="json-wrap"><?= jsonHtml($t['json']) ?></div>
      <?php elseif ($t['raw'] !== null): ?>
      <div class="json-wrap"><?= htmlspecialchars($t['raw']) ?></div>
      <?php endif; ?>

    </div>
  </div>
  <?php endforeach; ?>

  <!-- Alle Vars -->
  <?php if (isset($tests[1]['json']['vars']) && is_array($tests[1]['json']['vars'])): ?>
  <div class="test-card" id="cardVars">
    <div class="test-header" onclick="toggle('Vars')">
      <span class="test-status ok"></span>
      <span class="test-label">Server Vars (alle Convars aus /info.json)</span>
      <span class="test-meta"><span><?= count($tests[1]['json']['vars']) ?> Felder</span></span>
      <span class="test-chevron">▼</span>
    </div>
    <div class="test-body">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead><tr>
          <th style="text-align:left;color:var(--text-3);padding:4px 8px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px">Convar</th>
          <th style="text-align:left;color:var(--text-3);padding:4px 8px 10px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px">Wert</th>
        </tr></thead>
        <tbody>
          <?php foreach ($tests[1]['json']['vars'] as $k => $v): ?>
          <tr style="border-top:1px solid var(--border)">
            <td style="padding:7px 8px;color:var(--text-2);font-family:'JetBrains Mono',monospace"><?= htmlspecialchars((string)$k) ?></td>
            <td style="padding:7px 8px;color:#86efac;font-family:'JetBrains Mono',monospace;word-break:break-all"><?= htmlspecialchars((string)$v) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <p style="font-size:.75rem;color:var(--text-3);margin-top:24px;text-align:center">
    Diese Seite ist nur für Admins zugänglich. Nach Abschluss der Diagnose kann sie gelöscht werden.
  </p>

</div>

<script>
function toggle(id) {
  const el = document.getElementById('card' + id);
  el.classList.toggle('open');
}
</script>

<?php
function jsonHtml($data, int $depth = 0): string
{
    $indent  = str_repeat('  ', $depth);
    $indent1 = str_repeat('  ', $depth + 1);

    if (is_array($data)) {
        // PHP 7.x kompatibel: Liste = alle Keys sind 0,1,2,...
        $keys   = array_keys($data);
        $isList = ($keys === range(0, count($keys) - 1));
        if (empty($data)) return $isList ? '[]' : '{}';
        $out = $isList ? "[\n" : "{\n";
        $last = array_key_last($data);
        foreach ($data as $k => $v) {
            $out .= $indent1;
            if (!$isList) {
                $out .= '<span class="json-key">"' . htmlspecialchars((string)$k) . '"</span>: ';
            }
            $out .= jsonHtml($v, $depth + 1);
            $out .= ($k !== $last ? ',' : '') . "\n";
        }
        $out .= $indent . ($isList ? ']' : '}');
        return $out;
    }
    if (is_string($data)) return '<span class="json-str">"' . htmlspecialchars($data) . '"</span>';
    if (is_int($data) || is_float($data)) return '<span class="json-num">' . $data . '</span>';
    if (is_bool($data)) return '<span class="json-bool">' . ($data ? 'true' : 'false') . '</span>';
    if ($data === null) return '<span class="json-null">null</span>';
    return htmlspecialchars((string)$data);
}
?>
</body>
</html>
