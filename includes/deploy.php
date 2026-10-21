<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Deployment-Helfer (git pull)
//  Genutzt vom Admin-Panel (api/deploy.php) und vom GitHub-Webhook.
//  Es werden ausschließlich feste git-Befehle ausgeführt; der Branch kommt
//  aus der .env (DEPLOY_BRANCH) und wird zusätzlich auf erlaubte Zeichen geprüft.
// ============================================================

if (defined('_VSRP_DEPLOY_LOADED')) return;
define('_VSRP_DEPLOY_LOADED', true);

function deploy_branch(): string
{
    $b = env('DEPLOY_BRANCH', 'main');
    return preg_match('#^[A-Za-z0-9._/-]{1,60}$#', $b) && !str_contains($b, '..') ? $b : 'main';
}

function deploy_exec_available(): bool
{
    if (!function_exists('exec')) return false;
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return !in_array('exec', $disabled, true);
}

/** Führt `git <args>` im Projektordner aus. Gibt [Exit-Code, Ausgabezeilen] zurück. */
function deploy_git(array $args): array
{
    $root = dirname(__DIR__);
    $cmd  = 'GIT_TERMINAL_PROMPT=0 HOME=' . escapeshellarg(sys_get_temp_dir())
          . ' git -c safe.directory=' . escapeshellarg($root)
          . ' -C ' . escapeshellarg($root)
          . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $out = [];
    exec($cmd, $out, $code);
    return [$code, $out];
}

/** Aktueller Stand + ausstehende Commits (macht ein `git fetch`). */
function deploy_status(): array
{
    @set_time_limit(90);
    $branch = deploy_branch();
    [$fc, $fo] = deploy_git(['fetch', 'origin', $branch]);
    [, $head]    = deploy_git(['log', '-1', '--format=%h %s (%cd)', '--date=format:%d.%m.%Y %H:%M']);
    [, $pending] = deploy_git(['log', '--format=%h %s', '-n', '20', 'HEAD..origin/' . $branch]);
    [, $dirty]   = deploy_git(['status', '--porcelain', '--untracked-files=no']);
    return [
        'branch'    => $branch,
        'fetch_ok'  => $fc === 0,
        'fetch_msg' => $fc === 0 ? '' : implode("\n", array_slice($fo, -3)),
        'current'   => $head[0] ?? '?',
        'pending'   => $fc === 0 ? $pending : [],
        'dirty'     => $dirty, // lokal geänderte, versionierte Dateien (blockieren den Pull)
    ];
}

/** Holt den neuesten Stand (nur Fast-Forward) und leert Caches. */
function deploy_pull(): array
{
    @set_time_limit(90);
    $branch = deploy_branch();
    [$code, $out] = deploy_git(['pull', '--ff-only', 'origin', $branch]);
    if ($code === 0) {
        if (function_exists('opcache_reset')) @opcache_reset();
        foreach (glob(sys_get_temp_dir() . '/vsrp_*.json') ?: [] as $f) @unlink($f);
    }
    return [$code, $out];
}
