<?php
// ============================================================
//  ViennaStateRP – Strafenkatalog CRUD API
//  GET    /api/strafenkatalog.php         – Alle Einträge laden (Team: Supporter+)
//  POST   /api/strafenkatalog.php         – Neuen Eintrag anlegen (Admin)
//  PUT    /api/strafenkatalog.php?id=X    – Eintrag bearbeiten (Admin)
//  DELETE /api/strafenkatalog.php?id=X    – Eintrag löschen (Admin)
//
//  Jeder Verstoß kann bis zu zwei Sanktionsstufen (z. B. Ersttäter /
//  Wiederholungstäter) sowie eine freie Bemerkung tragen.
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Lesen: strafenkatalog.view · Schreiben: strafenkatalog.edit
if (!is_logged_in() || !has_capability('strafenkatalog.view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Keine DB-Verbindung']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$isAdmin = has_capability('strafenkatalog.edit');
if ($method !== 'GET' && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Nur Admins dürfen den Strafenkatalog bearbeiten']);
    exit;
}

$validLevels = ['warn', 'kick', 'ban', 'perma'];

// ── Tabelle automatisch anlegen / auf neues Schema erweitern ─────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `strafenkatalog` (
          `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `paragraph`  VARCHAR(20)  NOT NULL,
          `verstoss`   VARCHAR(255) NOT NULL,
          `sanktion_1` ENUM('warn','kick','ban','perma') NOT NULL DEFAULT 'warn',
          `dauer_1`    VARCHAR(100) NOT NULL DEFAULT '',
          `sanktion_2` ENUM('warn','kick','ban','perma') NULL,
          `dauer_2`    VARCHAR(100) NOT NULL DEFAULT '',
          `bemerkung`  VARCHAR(255) NOT NULL DEFAULT '',
          `sort_order` SMALLINT     NOT NULL DEFAULT 0,
          `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Upgrade für Installationen, die noch die alte Ein-Sanktion-Spalte (sanktion/dauer) hatten
    $pdo->exec("
        ALTER TABLE `strafenkatalog`
          ADD COLUMN IF NOT EXISTS `sanktion_1` ENUM('warn','kick','ban','perma') NOT NULL DEFAULT 'warn' AFTER `verstoss`,
          ADD COLUMN IF NOT EXISTS `dauer_1`    VARCHAR(100) NOT NULL DEFAULT '' AFTER `sanktion_1`,
          ADD COLUMN IF NOT EXISTS `sanktion_2` ENUM('warn','kick','ban','perma') NULL AFTER `dauer_1`,
          ADD COLUMN IF NOT EXISTS `dauer_2`    VARCHAR(100) NOT NULL DEFAULT '' AFTER `sanktion_2`,
          ADD COLUMN IF NOT EXISTS `bemerkung`  VARCHAR(255) NOT NULL DEFAULT '' AFTER `dauer_2`
    ");
    $cols = $pdo->query("SHOW COLUMNS FROM `strafenkatalog` LIKE 'sanktion'")->fetchAll();
    if ($cols) {
        // Alte Spalten aus der Vorversion (ein Sanktionsfeld) in sanktion_1/dauer_1 übernehmen
        $pdo->exec("UPDATE `strafenkatalog` SET `sanktion_1` = `sanktion` WHERE `sanktion` IS NOT NULL");
        $pdo->exec("UPDATE `strafenkatalog` SET `dauer_1` = `dauer` WHERE `dauer` IS NOT NULL AND `dauer` != ''");
        $pdo->exec("ALTER TABLE `strafenkatalog` DROP COLUMN `sanktion`, DROP COLUMN `dauer`");
    }

    $isEmpty = (int)$pdo->query("SELECT COUNT(*) FROM strafenkatalog")->fetchColumn() === 0;
    if ($isEmpty) {
        $ins = $pdo->prepare(
            "INSERT INTO strafenkatalog (paragraph, verstoss, sanktion_1, dauer_1, sanktion_2, dauer_2, bemerkung, sort_order)
             VALUES (?, ?, ?, ?, NULL, '', '', ?)"
        );
        foreach ([
            ['4.1',  'RDM – Random Deathmatch', 'ban', ''],
            ['4.2',  'VDM – Vehicle Deathmatch', 'ban', ''],
            ['5',    'Metagaming (OOC-Wissen im RP verwenden)', 'ban', ''],
            ['7',    'Combat Logging (Ausloggen während laufender RP-Situation)', 'ban', ''],
            ['8.1',  'RDM / VDM / Entführung / Schießerei im Ankunftsbereich (Safezone)', 'ban', ''],
            ['9.3',  'Fake-Geiseln oder abgesprochene Geiseln zum Vorteil', 'ban', ''],
            ['11',   'Cop-Baiting (Polizei ohne RP-Grund provozieren)', 'warn', ''],
            ['12.1', 'Mehrfache Fraktionszugehörigkeit (staatlich)', 'ban', ''],
            ['12.2', 'Weitergabe interner Fraktionsinfos (staatlich)', 'kick', ''],
            ['13.1', 'Mehrfache Fraktionszugehörigkeit (illegal)', 'ban', ''],
            ['13.2', 'Weitergabe interner Fraktionsinfos (illegal)', 'kick', ''],
            ['16',   'Ausnutzen von Bugs, Glitches oder Scriptfehlern', 'ban', ''],
            ['17',   'Hacking, Cheating oder unerlaubte Client-Manipulationen', 'perma', 'Permanent'],
            ['18',   'Falsche oder manipulierte Beweise einreichen', 'ban', ''],
            ['19',   'Metagaming durch Streaminformationen', 'ban', ''],
            ['20',   'Beleidigungen und Belästigungen', 'ban', ''],
            ['20',   'Rassismus, Diskriminierung und Extremismus', 'ban', ''],
            ['20',   'Mobbing und Hate Speech', 'ban', ''],
            ['20',   'OOC-Drohungen', 'ban', ''],
            ['20',   'Doxing (Veröffentlichung privater Daten)', 'perma', 'Permanent'],
            ['20',   'Reale Konsequenzen androhen', 'perma', 'Permanent'],
            ['21',   'Sexuelle Gewalt oder erzwungenes NSFW-RP', 'ban', ''],
            ['21',   'Sexualisiertes RP mit Minderjährigen', 'ban', ''],
        ] as $i => [$par, $desc, $level, $dauer]) {
            $ins->execute([$par, $desc, $level, $dauer, $i]);
        }
    }
} catch (\Throwable $e) {
    error_log('[Strafenkatalog] Migration fehlgeschlagen: ' . $e->getMessage());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/** Validiert + normalisiert die Sanktionsfelder aus dem Request-Body */
function strafen_parse_body(array $body, array $validLevels): ?array
{
    if (empty($body['paragraph']) || empty($body['verstoss']) || !in_array($body['sanktion_1'] ?? '', $validLevels, true)) {
        return null;
    }
    $sanktion2 = $body['sanktion_2'] ?? '';
    if ($sanktion2 !== '' && !in_array($sanktion2, $validLevels, true)) {
        return null;
    }
    return [
        'paragraph'  => mb_substr(trim($body['paragraph']), 0, 20),
        'verstoss'   => mb_substr(trim($body['verstoss']), 0, 255),
        'sanktion_1' => $body['sanktion_1'],
        'dauer_1'    => mb_substr(trim($body['dauer_1'] ?? ''), 0, 100),
        'sanktion_2' => $sanktion2 !== '' ? $sanktion2 : null,
        'dauer_2'    => $sanktion2 !== '' ? mb_substr(trim($body['dauer_2'] ?? ''), 0, 100) : '',
        'bemerkung'  => mb_substr(trim($body['bemerkung'] ?? ''), 0, 255),
    ];
}

// ── GET: Alle Einträge ───────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $rows = $pdo->query("SELECT * FROM strafenkatalog ORDER BY sort_order ASC, id ASC")->fetchAll();
        echo json_encode(['ok' => true, 'strafen' => $rows]);
    } catch (\Throwable $e) {
        http_response_code(500);
        error_log('[API] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Interner Fehler']);
    }
    exit;
}

// ── POST: Neuen Eintrag anlegen ──────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    $data = strafen_parse_body($body, $validLevels);
    if ($data === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Pflichtfelder fehlen oder Sanktion ungültig']);
        exit;
    }

    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM strafenkatalog")->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO strafenkatalog (paragraph, verstoss, sanktion_1, dauer_1, sanktion_2, dauer_2, bemerkung, sort_order)
        VALUES (:paragraph, :verstoss, :sanktion_1, :dauer_1, :sanktion_2, :dauer_2, :bemerkung, :sort_order)
    ");
    $stmt->execute([
        ':paragraph'  => $data['paragraph'],
        ':verstoss'   => $data['verstoss'],
        ':sanktion_1' => $data['sanktion_1'],
        ':dauer_1'    => $data['dauer_1'],
        ':sanktion_2' => $data['sanktion_2'],
        ':dauer_2'    => $data['dauer_2'],
        ':bemerkung'  => $data['bemerkung'],
        ':sort_order' => $maxOrder + 1,
    ]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

// ── PUT: Eintrag bearbeiten ───────────────────────────────────────────────────
if ($method === 'PUT') {
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Keine ID angegeben']);
        exit;
    }
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    $data = strafen_parse_body($body, $validLevels);
    if ($data === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Pflichtfelder fehlen oder Sanktion ungültig']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE strafenkatalog
        SET paragraph  = :paragraph,
            verstoss   = :verstoss,
            sanktion_1 = :sanktion_1,
            dauer_1    = :dauer_1,
            sanktion_2 = :sanktion_2,
            dauer_2    = :dauer_2,
            bemerkung  = :bemerkung
        WHERE id = :id
    ");
    $stmt->execute([
        ':paragraph'  => $data['paragraph'],
        ':verstoss'   => $data['verstoss'],
        ':sanktion_1' => $data['sanktion_1'],
        ':dauer_1'    => $data['dauer_1'],
        ':sanktion_2' => $data['sanktion_2'],
        ':dauer_2'    => $data['dauer_2'],
        ':bemerkung'  => $data['bemerkung'],
        ':id'         => $id,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE: Eintrag löschen ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Keine ID angegeben']);
        exit;
    }
    $pdo->prepare("DELETE FROM strafenkatalog WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
