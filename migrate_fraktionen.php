<?php
declare(strict_types=1);
/**
 * Migration: Fraktionen-Tabelle anlegen
 * Einmalig aufrufen: /migrate_fraktionen.php (danach löschen!)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/permissions.php';

require_login();
if (!has_permission('admin')) { http_response_code(403); exit('Kein Zugriff'); }

if (!isset($pdo)) { exit('Keine DB-Verbindung'); }

$pdo->exec("
CREATE TABLE IF NOT EXISTS `fraktionen` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `emoji`       VARCHAR(10)  NOT NULL DEFAULT '🏢',
  `name`        VARCHAR(100) NOT NULL,
  `typ`         VARCHAR(60)  NOT NULL COMMENT 'z.B. STAATLICH · LEGAL',
  `farbe`       VARCHAR(20)  NOT NULL DEFAULT '#64748b' COMMENT 'Hex-Farbe',
  `beschreibung`TEXT         NOT NULL,
  `slots_label` VARCHAR(50)  NOT NULL DEFAULT 'Offen' COMMENT 'Text-Label für Slots-Badge',
  `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
  `aktiv`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Standard-Daten einfügen (nur wenn Tabelle leer)
$count = (int)$pdo->query("SELECT COUNT(*) FROM fraktionen")->fetchColumn();
if ($count === 0) {
    $insert = $pdo->prepare("
        INSERT INTO fraktionen (emoji, name, typ, farbe, beschreibung, slots_label, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $defaults = [
        ['👮', 'Landespolizeidirektion Wien', 'STAATLICH · LEGAL',   '#3b82f6', 'Die LSPD ist die wichtigste Strafverfolgungsbehörde. Beamte patrouillieren, ermitteln und sorgen für Recht und Ordnung.',  'Offen',           1],
        ['🚑', 'Emergency Medical Services',  'STAATLICH · LEGAL',   '#ef4444', 'Das EMS versorgt Verletzte, reanimiert Bewusstlose und betreibt das Krankenhaus im Schichtbetrieb.',                        'Offen',           2],
        ['🚒', 'Feuerwehr Wien',              'STAATLICH · LEGAL',   '#f97316', 'Die Feuerwehr bekämpft Brände, rettet eingeklemmte Personen und unterstützt das EMS bei Großeinsätzen.',                    'Offen',           3],
        ['⚖️', 'Staatsanwaltschaft & Justiz', 'STAATLICH · LEGAL',   '#8b5cf6', 'Staatsanwälte, Richter und Anwälte – Verfahren werden vollständig im Roleplay verhandelt.',                                  'Offen',           4],
        ['🔫', 'Kriminelle Organisationen',   'KRIMINELL · ILLEGAL', '#64748b', 'Mehrere kriminelle Gruppierungen mit eigenen Territorien, Hierarchien und illegalen Einnahmequellen.',                      'Bewährungsphase', 5],
        ['🧑‍💼', 'Unabhängige / Zivilisten', 'FREELANCE · NEUTRAL', '#22c55e', 'Als Zivilist betreibst du ein eigenes Unternehmen, arbeitest als Journalist, Anwalt oder Unternehmer.',                     'OFFEN',           6],
    ];
    foreach ($defaults as $row) {
        $insert->execute($row);
    }
}

echo '<p style="font-family:monospace;color:green">✅ Tabelle <strong>fraktionen</strong> angelegt und Standarddaten eingefügt.<br>Diese Datei kann jetzt gelöscht werden.</p>';
