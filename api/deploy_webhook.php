<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Deployment per GitHub-Webhook
//
//  Bei jedem Push auf den Deploy-Branch ruft GitHub diese URL auf, der Server macht
//  daraufhin `git pull --ff-only` im Projektordner.
//
//  Einrichtung:
//   1. Auf dem Server: Repo per `git clone` im Webroot anlegen (SSH-Deploy-Key oder HTTPS-Token,
//      nur Leserechte), PHP-Benutzer muss im Ordner schreiben dürfen.
//   2. .env: DEPLOY_WEBHOOK_SECRET=<langer Zufallswert>   (optional DEPLOY_BRANCH=main)
//   3. GitHub → Repo → Settings → Webhooks → Add webhook:
//        Payload URL:  https://viennastaterp.at/api/deploy_webhook.php
//        Content type: application/json
//        Secret:       derselbe Wert wie DEPLOY_WEBHOOK_SECRET
//        Events:       Just the push event
//
//  Sicherheit: Ohne Secret gesperrt, Anfragen müssen eine gültige HMAC-SHA256-Signatur von
//  GitHub tragen; es wird nur ein fester Befehl ausgeführt (keine Eingaben aus dem Request).
// ============================================================
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php'; // nur für env()

function deploy_out(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$secret = env('DEPLOY_WEBHOOK_SECRET', '');
if ($secret === '' || strlen($secret) < 20) {
    deploy_out(403, ['ok' => false, 'error' => 'Forbidden']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    deploy_out(405, ['ok' => false, 'error' => 'Method not allowed']);
}

$body = (string) file_get_contents('php://input', false, null, 0, 1048576);
$sig  = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
if (!hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), $sig)) {
    error_log('[Deploy] Ungültige Signatur von ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    deploy_out(403, ['ok' => false, 'error' => 'Forbidden']);
}

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event === 'ping') {
    deploy_out(200, ['ok' => true, 'pong' => true]);
}
$payload = json_decode($body, true);
require_once __DIR__ . '/../includes/deploy.php';
$branch  = deploy_branch();
if ($event !== 'push' || !is_array($payload) || ($payload['ref'] ?? '') !== 'refs/heads/' . $branch) {
    deploy_out(200, ['ok' => true, 'skipped' => 'kein Push auf ' . $branch]);
}

if (!deploy_exec_available()) {
    error_log('[Deploy] exec() ist auf diesem Server deaktiviert – Webhook-Deployment nicht möglich.');
    deploy_out(500, ['ok' => false, 'error' => 'exec deaktiviert']);
}

// Nur ein Deployment gleichzeitig
$lock = fopen(sys_get_temp_dir() . '/vsrp_deploy.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    deploy_out(409, ['ok' => false, 'error' => 'Deployment läuft bereits']);
}

[$code, $output] = deploy_pull();
error_log('[Deploy] git pull (' . $branch . ') Exit ' . $code . ': ' . implode(' | ', array_slice($output, -5)));

flock($lock, LOCK_UN);
deploy_out($code === 0 ? 200 : 500, ['ok' => $code === 0, 'commit' => substr((string) ($payload['after'] ?? ''), 0, 7)]);
