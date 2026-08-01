<?php
// ============================================================
//  ViennaStateRP – Changelog CRUD API
//  GET    /api/changelogs.php                  – Veröffentlichte Einträge laden
//  GET    /api/changelogs.php?all=1            – Alle Einträge laden (Admin)
//  POST   /api/changelogs.php                  – Neuen Eintrag anlegen (Admin)
//  POST   /api/changelogs.php?action=process_due – Fällige Discord-Ankündigungen jetzt senden (Admin)
//  PUT    /api/changelogs.php?id=X             – Eintrag bearbeiten (Admin)
//  DELETE /api/changelogs.php?id=X             – Eintrag löschen (Admin)
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/changelog.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Keine DB-Verbindung']);
    exit;
}

changelog_migrate($pdo);

/**
 * Wandelt einen datetime-local-Wert ("YYYY-MM-DDTHH:MM") oder leeren String
 * in ein MySQL-DATETIME bzw. NULL um. NULL = sofort sichtbar.
 */
function changelog_parse_visible_at(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$action = $_GET['action'] ?? '';

// ── GET: Einträge laden ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $wantAll = isset($_GET['all']);
    if ($wantAll && (!is_logged_in() || !has_permission('admin'))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
    echo json_encode(['ok' => true, 'changelogs' => changelog_all($pdo, !$wantAll)]);
    exit;
}

// Ab hier: nur Admins dürfen schreiben
if (!is_logged_in() || !has_permission('admin')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// ── POST ?action=process_due: fällige Discord-Ankündigungen manuell auslösen ─
if ($method === 'POST' && $action === 'process_due') {
    $sent = changelog_process_due_notifications($pdo);
    echo json_encode(['ok' => true, 'sent' => $sent]);
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

    $required = ['title', 'entry_date', 'content'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Feld '{$field}' fehlt"]);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO changelogs (version, title, tag, entry_date, content, published, visible_at)
        VALUES (:version, :title, :tag, :entry_date, :content, :published, :visible_at)
    ");
    $stmt->execute([
        ':version'    => mb_substr(trim($body['version'] ?? ''), 0, 30),
        ':title'      => mb_substr(trim($body['title']), 0, 150),
        ':tag'        => array_key_exists($body['tag'] ?? '', CHANGELOG_TAGS) ? $body['tag'] : 'feature',
        ':entry_date' => $body['entry_date'],
        ':content'    => trim($body['content']),
        ':published'  => isset($body['published']) ? (int) (bool) $body['published'] : 1,
        ':visible_at' => changelog_parse_visible_at($body['visible_at'] ?? null),
    ]);
    $newId = (int) $pdo->lastInsertId();

    $sent = changelog_process_due_notifications($pdo);
    echo json_encode(['ok' => true, 'id' => $newId, 'notified' => $sent]);
    exit;
}

// ── PUT: Eintrag bearbeiten ──────────────────────────────────────────────────
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
        UPDATE changelogs
        SET version    = :version,
            title       = :title,
            tag         = :tag,
            entry_date  = :entry_date,
            content     = :content,
            published   = :published,
            visible_at  = :visible_at
        WHERE id = :id
    ");
    $stmt->execute([
        ':version'    => mb_substr(trim($body['version'] ?? ''), 0, 30),
        ':title'      => mb_substr(trim($body['title'] ?? ''), 0, 150),
        ':tag'        => array_key_exists($body['tag'] ?? '', CHANGELOG_TAGS) ? $body['tag'] : 'feature',
        ':entry_date' => $body['entry_date'] ?? date('Y-m-d'),
        ':content'    => trim($body['content'] ?? ''),
        ':published'  => isset($body['published']) ? (int) (bool) $body['published'] : 1,
        ':visible_at' => changelog_parse_visible_at($body['visible_at'] ?? null),
        ':id'         => $id,
    ]);

    $sent = changelog_process_due_notifications($pdo);
    echo json_encode(['ok' => true, 'notified' => $sent]);
    exit;
}

// ── DELETE: Eintrag löschen ──────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Keine ID angegeben']);
        exit;
    }
    $pdo->prepare("DELETE FROM changelogs WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
