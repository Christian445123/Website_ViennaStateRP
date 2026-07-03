<?php
// ============================================================
//  ViennaStateRP - Discord OAuth2 Callback  v2.0
//  Speichert alle Discord-Felder, OAuth-Tokens, Guilds
// ============================================================
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/discord_config.php';

if (isset($pdo)) {
    require_once __DIR__ . '/includes/rate_limit.php';
    require_once __DIR__ . '/includes/logger.php';
    if (!checkRateLimit('discord_callback', 10, 300)) {
        rateLimitExceeded();
    }
}

$siteUrl = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');

// ── State / CSRF ──────────────────────────────────────────────────────────────
if (empty($_GET['state']) || empty($_SESSION['oauth_state'])
    || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
    error_log('[Callback] Ungueltiger OAuth State');
    header('Location: ' . $siteUrl . '/index.php?error=' . urlencode('Ungueltige Anfrage'));
    exit;
}
unset($_SESSION['oauth_state']);

if (isset($_GET['error'])) {
    header('Location: ' . $siteUrl . '/login.php?error=' . urlencode($_GET['error_description'] ?? 'Zugriff verweigert'));
    exit;
}

if (empty($_GET['code'])) {
    header('Location: ' . $siteUrl . '/login.php?error=' . urlencode('Kein Code empfangen'));
    exit;
}

// ── Token-Austausch ───────────────────────────────────────────────────────────
$token = _discord_post('https://discord.com/api/oauth2/token', [
    'client_id'     => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => DISCORD_REDIRECT_URI,
]);

if (empty($token['access_token'])) {
    error_log('[Callback] Token-Austausch fehlgeschlagen: ' . json_encode($token));
    header('Location: ' . $siteUrl . '/login.php?error=' . urlencode('Login fehlgeschlagen – bitte erneut versuchen'));
    exit;
}

$accessToken  = $token['access_token'];
$refreshToken = $token['refresh_token'] ?? null;
$expiresIn    = (int) ($token['expires_in'] ?? 604800);
$scope        = $token['scope'] ?? '';

// ── Discord User holen ────────────────────────────────────────────────────────
$du = _discord_get('https://discord.com/api/users/@me', $accessToken);

if (empty($du['id'])) {
    error_log('[Callback] Discord User-Abfrage fehlgeschlagen: ' . json_encode($du));
    header('Location: ' . $siteUrl . '/login.php?error=' . urlencode('Benutzerdaten konnten nicht abgerufen werden'));
    exit;
}

// ── Discord Guilds holen (zur Guild-Pruefung) ─────────────────────────────────
$guilds = [];
if (str_contains($scope, 'guilds')) {
    $guildsRaw = _discord_get('https://discord.com/api/users/@me/guilds', $accessToken);
    if (is_array($guildsRaw)) {
        $guilds = $guildsRaw;
    }
}

// ── DB: User anlegen / aktualisieren ─────────────────────────────────────────
$dbRow = null;
if (!isset($pdo)) {
    error_log('[Callback] Keine Datenbankverbindung – Login nicht moeglich');
    header('Location: ' . $siteUrl . '/login.php?error=' . urlencode('Datenbankfehler – bitte erneut versuchen'));
    exit;
}

try {
    // Modus: Discord an bestehenden Account knoepfen
    if (is_logged_in()
        && !empty($_SESSION['discord_user']['db_id'])
        && empty($_SESSION['discord_user']['id'])) {

        $pdo->prepare(
            'UPDATE users SET
               discord_id=?, username=?, global_name=?, avatar=?, banner=?,
               accent_color=?, email=?, discord_email_verified=?, discord_locale=?,
               discord_mfa_enabled=?, discord_premium_type=?, discord_public_flags=?,
               last_login=NOW()
             WHERE id=?'
        )->execute([
            $du['id'],
            $du['username'],
            $du['global_name'] ?? $du['username'],
            $du['avatar']      ?? null,
            $du['banner']      ?? null,
            $du['accent_color'] ?? null,
            $du['email']       ?? null,
            (int) ($du['verified']     ?? 0),
            $du['locale']      ?? null,
            (int) ($du['mfa_enabled']  ?? 0),
            (int) ($du['premium_type'] ?? 0),
            (int) ($du['public_flags'] ?? 0),
            $_SESSION['discord_user']['db_id'],
        ]);

        $_SESSION['discord_user']['id']     = $du['id'];
        $_SESSION['discord_user']['avatar'] = $du['avatar'] ?? null;

        _save_tokens($pdo, (int) $_SESSION['discord_user']['db_id'], $accessToken, $refreshToken, $expiresIn, $scope);
        _save_guilds($pdo, (int) $_SESSION['discord_user']['db_id'], $guilds);

        header('Location: ' . $siteUrl . '/dashboard.php?success=' . urlencode('Discord erfolgreich verknuepft!'));
        exit;
    }

    // Normaler Discord-Login
    $stmt = $pdo->prepare('SELECT id, role, is_whitelisted, is_banned, ban_reason FROM users WHERE discord_id = ?');
    $stmt->execute([$du['id']]);
    $dbRow = $stmt->fetch() ?: null;

    if (!$dbRow) {
        // Neuen Account anlegen
        $pdo->prepare(
            'INSERT INTO users
               (discord_id, username, global_name, avatar, banner, accent_color,
                email, discord_email_verified, discord_locale, discord_mfa_enabled,
                discord_premium_type, discord_public_flags, is_verified, last_login)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())'
        )->execute([
            $du['id'],
            $du['username'],
            $du['global_name']  ?? $du['username'],
            $du['avatar']       ?? null,
            $du['banner']       ?? null,
            $du['accent_color'] ?? null,
            $du['email']        ?? null,
            (int) ($du['verified']     ?? 0),
            $du['locale']       ?? null,
            (int) ($du['mfa_enabled']  ?? 0),
            (int) ($du['premium_type'] ?? 0),
            (int) ($du['public_flags'] ?? 0),
        ]);

        $stmt->execute([$du['id']]);
        $dbRow = $stmt->fetch() ?: null;

        if (function_exists('log_event')) {
            log_event('discord_register', "Neuer Account: {$du['username']}", (int) ($dbRow['id'] ?? 0));
        }

    } else {
        // Bestehenden Account aktualisieren
        $pdo->prepare(
            'UPDATE users SET
               username=?, global_name=?, avatar=?, banner=?, accent_color=?,
               email=?, discord_email_verified=?, discord_locale=?,
               discord_mfa_enabled=?, discord_premium_type=?, discord_public_flags=?,
               last_login=NOW()
             WHERE discord_id=?'
        )->execute([
            $du['username'],
            $du['global_name']  ?? $du['username'],
            $du['avatar']       ?? null,
            $du['banner']       ?? null,
            $du['accent_color'] ?? null,
            $du['email']        ?? null,
            (int) ($du['verified']     ?? 0),
            $du['locale']       ?? null,
            (int) ($du['mfa_enabled']  ?? 0),
            (int) ($du['premium_type'] ?? 0),
            (int) ($du['public_flags'] ?? 0),
            $du['id'],
        ]);
    }

    if (!$dbRow || empty($dbRow['id'])) {
        throw new \RuntimeException('DB-Zeile nach INSERT nicht gefunden');
    }

    $userId = (int) $dbRow['id'];

    // Gebannter User
    if ($dbRow['is_banned']) {
        if (function_exists('log_event')) {
            log_event('login_banned', "Gebannter User {$du['username']}", $userId);
        }
        session_unset(); session_destroy();
        header('Location: ' . $siteUrl . '/index.php?error=' . urlencode('Du wurdest gebannt: ' . ($dbRow['ban_reason'] ?? 'Kein Grund')));
        exit;
    }

    // Tokens, Guilds, Login-Log speichern
    _save_tokens($pdo, $userId, $accessToken, $refreshToken, $expiresIn, $scope);
    _save_guilds($pdo, $userId, $guilds);

    $pdo->prepare(
        'INSERT INTO login_log (user_id, method, ip_hash, user_agent) VALUES (?,?,?,?)'
    )->execute([
        $userId,
        'discord',
        hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    if (function_exists('log_event')) {
        log_event('discord_login', "{$du['username']} eingeloggt", $userId);
    }

} catch (\Throwable $e) {
    error_log('[Callback] DB-Fehler: ' . $e->getMessage());
    header('Location: ' . $siteUrl . '/login.php?error=' . urlencode('Datenbankfehler – bitte erneut versuchen'));
    exit;
}

// ── Session aufbauen ──────────────────────────────────────────────────────────
session_regenerate_id(true);

$_SESSION['discord_user'] = [
    'id'            => $du['id'],
    'username'      => $du['username'],
    'global_name'   => $du['global_name'] ?? $du['username'],
    'avatar'        => $du['avatar']       ?? null,
    'banner'        => $du['banner']       ?? null,
    'accent_color'  => $du['accent_color'] ?? null,
    'email'         => $du['email']        ?? '',
    'locale'        => $du['locale']       ?? '',
    'premium_type'  => (int) ($du['premium_type'] ?? 0),
    'public_flags'  => (int) ($du['public_flags']  ?? 0),
    'db_id'         => (int) ($dbRow['id']          ?? 0),
    'role'          => $dbRow['role']          ?? 'player',
    'is_whitelisted'=> (bool) ($dbRow['is_whitelisted'] ?? false),
];
$_SESSION['last_activity'] = time();

header('Location: ' . $siteUrl . '/dashboard.php');
exit;

// ── Hilfs-Funktionen ─────────────────────────────────────────────────────────

/** OAuth-Tokens verschluesselt in DB speichern */
function _save_tokens(PDO $pdo, int $userId, string $access, ?string $refresh, int $expiresIn, string $scope): void
{
    $encAccess  = function_exists('vsrp_env_encrypt') ? vsrp_env_encrypt($access)            : $access;
    $encRefresh = ($refresh && function_exists('vsrp_env_encrypt')) ? vsrp_env_encrypt($refresh) : $refresh;
    $expiresAt  = date('Y-m-d H:i:s', time() + $expiresIn);

    $pdo->prepare(
        'INSERT INTO discord_tokens (user_id, access_token, refresh_token, scope, expires_at)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           access_token=VALUES(access_token),
           refresh_token=VALUES(refresh_token),
           scope=VALUES(scope),
           expires_at=VALUES(expires_at),
           updated_at=NOW()'
    )->execute([$userId, $encAccess, $encRefresh, $scope, $expiresAt]);
}

/** Discord-Guilds in DB cachen */
function _save_guilds(PDO $pdo, int $userId, array $guilds): void
{
    if (empty($guilds)) return;

    $stmt = $pdo->prepare(
        'INSERT INTO discord_guilds (user_id, guild_id, guild_name, is_owner, permissions)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           guild_name=VALUES(guild_name),
           is_owner=VALUES(is_owner),
           permissions=VALUES(permissions),
           checked_at=NOW()'
    );

    foreach ($guilds as $g) {
        if (empty($g['id'])) continue;
        $stmt->execute([
            $userId,
            $g['id'],
            substr((string) ($g['name'] ?? ''), 0, 100),
            (int) ($g['owner'] ?? 0),
            (string) ($g['permissions'] ?? '0'),
        ]);
    }
}