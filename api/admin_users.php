<?php
// ============================================================
//  ViennaStateRP – Benutzerverwaltung API
//  GET    /api/admin_users.php                    – Liste (q, role, status, page)
//  GET    /api/admin_users.php?stats=1             – Dashboard-Zählungen
//  POST   /api/admin_users.php?id=X&action=role    – Rolle ändern       {role}
//  POST   /api/admin_users.php?id=X&action=ban     – Sperren            {reason}
//  POST   /api/admin_users.php?id=X&action=unban   – Entsperren
//  POST   /api/admin_users.php?id=X&action=verify  – Verifizieren       {verified}
//  POST   /api/admin_users.php?id=X&action=reset_password – Passwort    {password}
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in() || !has_capability('users.view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Keine DB-Verbindung']);
    exit;
}

$me     = current_user();
$myId   = (int)($me['db_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

// ── GET: Zählungen fürs Dashboard ────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['stats'])) {
    try {
        $stats = [
            'total'    => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'verified' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn(),
            'admins'   => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
            'banned'   => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn(),
        ];
        echo json_encode(['ok' => true, 'stats' => $stats]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── GET: Userliste mit Suche/Filter/Pagination ───────────────────────────────
if ($method === 'GET') {
    try {
        $q      = trim((string)($_GET['q'] ?? ''));
        $role   = (string)($_GET['role'] ?? '');
        $status = (string)($_GET['status'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;

        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(username LIKE :q OR global_name LIKE :q OR email LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($role !== '' && array_key_exists($role, VSRP_ROLES)) {
            $where[] = "role = :role";
            $params[':role'] = $role;
        }
        if ($status === 'verified')   $where[] = "is_verified = 1";
        if ($status === 'unverified') $where[] = "is_verified = 0";
        if ($status === 'banned')     $where[] = "is_banned = 1";

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("
            SELECT id, discord_id, username, global_name, avatar, email,
                   is_verified, role, is_banned, ban_reason, banned_at,
                   last_login, created_at
            FROM users
            {$whereSql}
            ORDER BY created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo json_encode(['ok' => true, 'users' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── POST: Aktionen ────────────────────────────────────────────────────────────
if ($method === 'POST') {
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Keine ID angegeben']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];

    $target = $pdo->prepare("SELECT id, username, global_name, role FROM users WHERE id = ? LIMIT 1");
    $target->execute([$id]);
    $target = $target->fetch();
    if (!$target) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }

    if ($action === 'role') {
        if (!has_capability('users.change_role')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung, Rollen zu ändern']);
            exit;
        }
        $role = (string)($body['role'] ?? '');
        if (!array_key_exists($role, VSRP_ROLES)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Rolle']);
            exit;
        }
        $adminLevel = VSRP_ROLES['admin'] ?? 0;
        $wasAdminUp = (VSRP_ROLES[$target['role']] ?? 0) >= $adminLevel;
        $staysAdminUp = (VSRP_ROLES[$role] ?? 0) >= $adminLevel;
        if ($id === $myId && $wasAdminUp && !$staysAdminUp) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Du kannst dich nicht selbst unter Administrator-Rechte degradieren.']);
            exit;
        }
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);
        log_event('role_assigned', "Rolle von {$target['username']} auf {$role} geändert", $myId, ['target_id' => $id, 'role' => $role]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'ban') {
        if (!has_capability('users.ban')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung zum Sperren']);
            exit;
        }
        if ($id === $myId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Du kannst dich nicht selbst sperren.']);
            exit;
        }
        $reason = trim((string)($body['reason'] ?? ''));
        $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ?, banned_by = ?, banned_at = NOW() WHERE id = ?")
            ->execute([$reason !== '' ? $reason : null, $myId, $id]);
        log_event('admin_ban', "{$target['username']} gesperrt" . ($reason !== '' ? ": {$reason}" : ''), $myId, ['target_id' => $id, 'reason' => $reason]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'unban') {
        if (!has_capability('users.ban')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung zum Entsperren']);
            exit;
        }
        $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL, banned_by = NULL, banned_at = NULL WHERE id = ?")
            ->execute([$id]);
        log_event('admin_unban', "{$target['username']} entsperrt", $myId, ['target_id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'verify') {
        if (!has_capability('users.verify')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung, die Verifizierung zu ändern']);
            exit;
        }
        $verified = !empty($body['verified']) ? 1 : 0;
        $pdo->prepare("UPDATE users SET is_verified = ? WHERE id = ?")->execute([$verified, $id]);
        log_event('admin_action', ($verified ? "{$target['username']} verifiziert" : "Verifizierung von {$target['username']} entfernt"), $myId, ['target_id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reset_password') {
        if (!has_capability('users.reset_password')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung, Passwörter zurückzusetzen']);
            exit;
        }
        $password = (string)($body['password'] ?? '');
        if (mb_strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Passwort muss mindestens 8 Zeichen lang sein']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
        log_event('admin_action', "Passwort von {$target['username']} zurückgesetzt", $myId, ['target_id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
