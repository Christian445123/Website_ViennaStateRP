<?php
declare(strict_types=1);
// ============================================================
//  ViennaStateRP – Changelog Datenzugriff
//  Tabelle wird bei Bedarf automatisch angelegt (wie fraktionen).
// ============================================================

if (defined('_VSRP_CHANGELOG_LOADED')) return;
define('_VSRP_CHANGELOG_LOADED', true);

require_once __DIR__ . '/discord_config.php';
require_once __DIR__ . '/site_settings.php';

const CHANGELOG_TAGS = [
    'feature'     => ['label' => 'Neu',         'color' => '#22c55e'],
    'improvement' => ['label' => 'Verbessert',  'color' => '#3b82f6'],
    'fix'         => ['label' => 'Behoben',     'color' => '#e8b86d'],
    'balance'     => ['label' => 'Balance',     'color' => '#7c3aed'],
    'security'    => ['label' => 'Sicherheit',  'color' => '#ef4444'],
    'removed'     => ['label' => 'Entfernt',    'color' => '#64748b'],
];

function changelog_tag_meta(string $tag): array
{
    return CHANGELOG_TAGS[$tag] ?? ['label' => ucfirst($tag), 'color' => '#64748b'];
}

function changelog_migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `changelogs` (
              `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `version`         VARCHAR(30)  NOT NULL DEFAULT '',
              `title`           VARCHAR(150) NOT NULL,
              `tag`             VARCHAR(30)  NOT NULL DEFAULT 'update',
              `entry_date`      DATE         NOT NULL,
              `content`         TEXT         NOT NULL COMMENT 'Ein Punkt pro Zeile',
              `published`       TINYINT(1)   NOT NULL DEFAULT 1,
              `visible_at`      DATETIME     NULL      COMMENT 'Geplante Sichtbarkeit; NULL = sofort',
              `discord_sent_at` DATETIME     NULL      COMMENT 'Wann die Discord-Ankuendigung verschickt wurde',
              `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_date`       (`entry_date`),
              KEY `idx_published`  (`published`),
              KEY `idx_visible_at` (`visible_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // Upgrade fuer bereits bestehende Installationen ohne die neuen Spalten
        $pdo->exec("
            ALTER TABLE `changelogs`
              ADD COLUMN IF NOT EXISTS `visible_at`      DATETIME NULL AFTER `published`,
              ADD COLUMN IF NOT EXISTS `discord_sent_at` DATETIME NULL AFTER `visible_at`
        ");
    } catch (\Throwable $e) {
        error_log('[Changelog] Migration fehlgeschlagen: ' . $e->getMessage());
    }
}

/**
 * Entwurf / Geplant / Veröffentlicht anhand von `published` + `visible_at`.
 */
function changelog_status(array $row): string
{
    if ((int) ($row['published'] ?? 0) !== 1) return 'draft';
    $visibleAt = $row['visible_at'] ?? null;
    if ($visibleAt && strtotime($visibleAt) > time()) return 'scheduled';
    return 'published';
}

/**
 * Wandelt eine DB-Zeile in ein Array mit geparster Punkteliste + Status um.
 */
function changelog_format_row(array $row): array
{
    $row['points'] = array_values(array_filter(array_map('trim', explode("\n", (string) $row['content']))));
    $row['status'] = changelog_status($row);
    return $row;
}

/**
 * Alle Changelog-Einträge (neueste zuerst).
 * $publishedOnly = true → nur öffentlich sichtbare Einträge (für Startseite/Changelog-Seite).
 */
function changelog_all(PDO $pdo, bool $publishedOnly = true): array
{
    changelog_migrate($pdo);
    try {
        $sql = "SELECT * FROM changelogs";
        if ($publishedOnly) {
            $sql .= " WHERE published = 1 AND (visible_at IS NULL OR visible_at <= NOW())";
        }
        $sql .= " ORDER BY entry_date DESC, id DESC";
        $rows = $pdo->query($sql)->fetchAll();
        return array_map('changelog_format_row', $rows);
    } catch (\Throwable $e) {
        error_log('[Changelog] Laden fehlgeschlagen: ' . $e->getMessage());
        return [];
    }
}

/**
 * Der zuletzt veröffentlichte Eintrag (für die Startseite).
 */
function changelog_latest(PDO $pdo): ?array
{
    $all = changelog_all($pdo, true);
    return $all[0] ?? null;
}

/**
 * Schickt eine Discord-Ankündigung für einen Changelog-Eintrag.
 */
function changelog_notify_discord(array $row): bool
{
    // Admin-Panel-Einstellung hat Vorrang vor .env-Konstante
    $webhookUrl = (string) setting('discord_changelog_webhook');
    if ($webhookUrl === '') {
        $webhookUrl = DISCORD_CHANGELOG_WEBHOOK_URL;
    }
    if (!$webhookUrl) return false;

    $meta  = changelog_tag_meta($row['tag']);
    $color = hexdec(ltrim($meta['color'], '#'));
    $points = $row['points'] ?? array_values(array_filter(array_map('trim', explode("\n", (string) $row['content']))));
    $desc   = $points ? ('• ' . implode("\n• ", $points)) : '';

    $titleParts = [];
    if (!empty($row['version'])) $titleParts[] = $row['version'];
    $titleParts[] = $row['title'];

    $embed = [
        'title'       => '🆕 ' . implode(' – ', $titleParts),
        'description' => mb_substr($desc, 0, 4096),
        'color'       => $color,
        'fields'      => [
            ['name' => 'Kategorie', 'value' => $meta['label'], 'inline' => true],
        ],
        'footer'    => ['text' => 'ViennaStateRP Changelog'],
        'timestamp' => date('c'),
    ];
    if (defined('SITE_URL') && SITE_URL) {
        $embed['url'] = rtrim(SITE_URL, '/') . '/changelog.php';
    }

    $payload = ['username' => DISCORD_BOT_NAME, 'embeds' => [$embed]];
    if (DISCORD_BOT_AVATAR) $payload['avatar_url'] = DISCORD_BOT_AVATAR;

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return $result !== false && $httpCode >= 200 && $httpCode < 300;
}

/**
 * Findet alle veröffentlichten/fälligen Einträge ohne Discord-Ankündigung,
 * verschickt sie und markiert sie als gesendet. Gibt die Anzahl zurück.
 * Wird sowohl nach dem Admin-Speichern als auch per Cronjob aufgerufen.
 */
function changelog_process_due_notifications(PDO $pdo): int
{
    changelog_migrate($pdo);
    try {
        $rows = $pdo->query(
            "SELECT * FROM changelogs
             WHERE published = 1
               AND (visible_at IS NULL OR visible_at <= NOW())
               AND discord_sent_at IS NULL
             ORDER BY entry_date ASC, id ASC"
        )->fetchAll();
    } catch (\Throwable $e) {
        error_log('[Changelog] Faellige Eintraege konnten nicht geladen werden: ' . $e->getMessage());
        return 0;
    }

    $sent = 0;
    foreach ($rows as $row) {
        $row = changelog_format_row($row);
        try {
            if (changelog_notify_discord($row)) {
                $pdo->prepare("UPDATE changelogs SET discord_sent_at = NOW() WHERE id = ?")->execute([$row['id']]);
                $sent++;
            } else {
                error_log('[Changelog] Discord-Versand fehlgeschlagen fuer Eintrag #' . $row['id']);
            }
        } catch (\Throwable $e) {
            error_log('[Changelog] Discord-Versand-Fehler #' . $row['id'] . ': ' . $e->getMessage());
        }
    }
    return $sent;
}
