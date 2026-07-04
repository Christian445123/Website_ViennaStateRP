<?php
// ============================================================
//  ViennaStateRP – Fraktionen CRUD API
//  GET    /api/fraktionen.php         – Alle Fraktionen laden
//  POST   /api/fraktionen.php         – Neue Fraktion anlegen
//  PUT    /api/fraktionen.php?id=X    – Fraktion bearbeiten
//  DELETE /api/fraktionen.php?id=X    – Fraktion löschen
//  POST   /api/fraktionen.php?action=reorder – Reihenfolge speichern
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !has_permission('admin')) {
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
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

// ── GET: Alle Fraktionen ─────────────────────────────────────────────────────
if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM fraktionen ORDER BY sort_order ASC, id ASC")->fetchAll();
    echo json_encode(['ok' => true, 'fraktionen' => $rows]);
    exit;
}

// ── POST: Neu anlegen oder Reihenfolge speichern ────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    // Reihenfolge speichern
    if ($action === 'reorder') {
        $order = $body['order'] ?? [];
        if (!is_array($order)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'order fehlt']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE fraktionen SET sort_order = ? WHERE id = ?");
        foreach ($order as $idx => $fid) {
            $stmt->execute([(int)$idx, (int)$fid]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // Neue Fraktion anlegen
    $required = ['emoji', 'name', 'typ', 'farbe', 'beschreibung', 'slots_label'];
    foreach ($required as $field) {
        if (empty($body[$field]) && $body[$field] !== '0') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Feld '{$field}' fehlt"]);
            exit;
        }
    }

    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM fraktionen")->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO fraktionen (emoji, name, typ, farbe, beschreibung, slots_label, sort_order, aktiv)
        VALUES (:emoji, :name, :typ, :farbe, :beschreibung, :slots_label, :sort_order, :aktiv)
    ");
    $stmt->execute([
        ':emoji'       => mb_substr(trim($body['emoji']), 0, 10),
        ':name'        => mb_substr(trim($body['name']), 0, 100),
        ':typ'         => mb_substr(trim($body['typ']), 0, 60),
        ':farbe'       => preg_match('/^#[0-9a-fA-F]{3,8}$/', $body['farbe']) ? $body['farbe'] : '#64748b',
        ':beschreibung'=> trim($body['beschreibung']),
        ':slots_label' => mb_substr(trim($body['slots_label']), 0, 50),
        ':sort_order'  => $maxOrder + 1,
        ':aktiv'       => isset($body['aktiv']) ? (int)(bool)$body['aktiv'] : 1,
    ]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

// ── PUT: Fraktion bearbeiten ─────────────────────────────────────────────────
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

    $stmt = $pdo->prepare("
        UPDATE fraktionen
        SET emoji       = :emoji,
            name        = :name,
            typ         = :typ,
            farbe       = :farbe,
            beschreibung= :beschreibung,
            slots_label = :slots_label,
            aktiv       = :aktiv
        WHERE id = :id
    ");
    $stmt->execute([
        ':emoji'       => mb_substr(trim($body['emoji'] ?? ''), 0, 10),
        ':name'        => mb_substr(trim($body['name'] ?? ''), 0, 100),
        ':typ'         => mb_substr(trim($body['typ'] ?? ''), 0, 60),
        ':farbe'       => preg_match('/^#[0-9a-fA-F]{3,8}$/', $body['farbe'] ?? '') ? ($body['farbe']) : '#64748b',
        ':beschreibung'=> trim($body['beschreibung'] ?? ''),
        ':slots_label' => mb_substr(trim($body['slots_label'] ?? ''), 0, 50),
        ':aktiv'       => isset($body['aktiv']) ? (int)(bool)$body['aktiv'] : 1,
        ':id'          => $id,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE: Fraktion löschen ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Keine ID angegeben']);
        exit;
    }
    $pdo->prepare("DELETE FROM fraktionen WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
