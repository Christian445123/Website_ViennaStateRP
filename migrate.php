<?php
// ============================================================
//  ViennaStateRP – Datenbank-Migration
//  Einmalig aufrufen: https://viennastaterp.at/migrate.php
//  DANACH SOFORT LOESCHEN oder .htaccess schützen!
// ============================================================
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';

if (!isset($pdo)) {
    die('<h2 style="color:red">Keine Datenbankverbindung!</h2>');
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Migration – ViennaStateRP</title>
<style>
  body{font-family:monospace;background:#0a0a0f;color:#e2e8f0;padding:30px;font-size:14px}
  h1{color:#e8b86d;font-family:sans-serif}
  .ok{color:#22c55e} .err{color:#ef4444} .skip{color:#94a3b8} .warn{color:#f59e0b}
  pre{background:#111118;border:1px solid #333;padding:12px;border-radius:6px;margin:4px 0}
  hr{border-color:#333;margin:20px 0}
</style></head><body>';

echo '<h1>🗄️ ViennaStateRP – Datenbank-Migration</h1>';
echo '<p>Datum: ' . date('d.m.Y H:i:s') . '</p><hr>';

$results = [];

function run(PDO $pdo, string $label, string $sql): void {
    echo "<b>$label</b><br>";
    try {
        $pdo->exec($sql);
        echo '<span class="ok">✓ OK</span><br><br>';
    } catch (\PDOException $e) {
        // "Duplicate column" oder "already exists" = schon vorhanden = OK
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists')) {
            echo '<span class="skip">⬤ Bereits vorhanden – übersprungen</span><br><br>';
        } else {
            echo '<span class="err">✗ FEHLER: ' . htmlspecialchars($msg) . '</span><br><br>';
        }
    }
}

// ── Bestehende Tabellen prüfen ────────────────────────────────────────────────
echo '<h2>Bestehende Tabellen:</h2>';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo '<span class="ok">✓</span> ' . htmlspecialchars($t) . '<br>';
}
echo '<hr>';

// ── Spalten zu users hinzufügen ───────────────────────────────────────────────
echo '<h2>users – neue Spalten:</h2>';
run($pdo, 'banner', "ALTER TABLE `users` ADD COLUMN `banner` VARCHAR(64) NULL AFTER `avatar`");
run($pdo, 'accent_color', "ALTER TABLE `users` ADD COLUMN `accent_color` INT UNSIGNED NULL AFTER `banner`");
run($pdo, 'discord_email_verified', "ALTER TABLE `users` ADD COLUMN `discord_email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`");
run($pdo, 'discord_locale', "ALTER TABLE `users` ADD COLUMN `discord_locale` VARCHAR(10) NULL AFTER `discord_email_verified`");
run($pdo, 'discord_mfa_enabled', "ALTER TABLE `users` ADD COLUMN `discord_mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `discord_locale`");
run($pdo, 'discord_premium_type', "ALTER TABLE `users` ADD COLUMN `discord_premium_type` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `discord_mfa_enabled`");
run($pdo, 'discord_public_flags', "ALTER TABLE `users` ADD COLUMN `discord_public_flags` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `discord_premium_type`");
run($pdo, 'banned_by', "ALTER TABLE `users` ADD COLUMN `banned_by` BIGINT UNSIGNED NULL AFTER `ban_reason`");
run($pdo, 'banned_at', "ALTER TABLE `users` ADD COLUMN `banned_at` DATETIME NULL AFTER `banned_by`");

// ── login_log ─────────────────────────────────────────────────────────────────
echo '<h2>login_log – neue Spalten:</h2>';
run($pdo, 'method', "ALTER TABLE `login_log` ADD COLUMN `method` ENUM('discord','password') NOT NULL DEFAULT 'discord' AFTER `user_id`");

// ── Neue Tabellen ─────────────────────────────────────────────────────────────
echo '<h2>Neue Tabellen:</h2>';
run($pdo, 'discord_tokens', "
CREATE TABLE IF NOT EXISTS `discord_tokens` (
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `access_token`  TEXT NOT NULL,
  `refresh_token` TEXT NULL,
  `token_type`    VARCHAR(20) NOT NULL DEFAULT 'Bearer',
  `scope`         VARCHAR(255) NULL,
  `expires_at`    DATETIME NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($pdo, 'discord_guilds', "
CREATE TABLE IF NOT EXISTS `discord_guilds` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `guild_id`    VARCHAR(20) NOT NULL,
  `guild_name`  VARCHAR(100) NULL,
  `is_owner`    TINYINT(1) NOT NULL DEFAULT 0,
  `permissions` VARCHAR(20) NULL,
  `checked_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_guild` (`user_id`, `guild_id`),
  CONSTRAINT `fk_guild_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($pdo, 'user_identifiers', "
CREATE TABLE IF NOT EXISTS `user_identifiers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       ENUM('license','steam','discord','xbl','live','ip','fivem') NOT NULL,
  `value`      VARCHAR(255) NOT NULL,
  `first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_type_val` (`user_id`, `type`, `value`),
  CONSTRAINT `fk_ident_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($pdo, 'server_status_log', "
CREATE TABLE IF NOT EXISTS `server_status_log` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `online`         TINYINT(1) NOT NULL,
  `players`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `max_players`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `map`            VARCHAR(100) NULL,
  `gametype`       VARCHAR(100) NULL,
  `resource_count` SMALLINT UNSIGNED NULL,
  `upvote_power`   INT UNSIGNED NULL,
  `snapshot_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo '<hr><h2>Aktueller users-Tabellen-Status:</h2>';
$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo '<pre>';
foreach ($cols as $c) {
    printf("%-35s %-20s %s\n",
        htmlspecialchars($c['Field']),
        htmlspecialchars($c['Type']),
        $c['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
    );
}
echo '</pre>';

echo '<hr><p style="color:#f59e0b">⚠️ <b>Diese Datei nach erfolgreicher Migration sofort löschen!</b></p>';
echo '<form method="post" action=""><button style="background:#ef4444;color:#fff;border:none;padding:10px 20px;cursor:pointer;border-radius:6px" onclick="return confirm(\'Datei wirklich löschen?\')">🗑 migrate.php jetzt löschen</button></form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (unlink(__FILE__)) {
        echo '<p style="color:#22c55e">✓ migrate.php wurde gelöscht.</p>';
    } else {
        echo '<p style="color:#ef4444">✗ Löschen fehlgeschlagen – bitte manuell löschen.</p>';
    }
}

echo '</body></html>';
