-- ============================================================
--  ViennaStateRP - Datenbankschema v2.0
--  MySQL 8.0+ / MariaDB 10.6+
--  Ausfuehren: mysql -u root -p viennastaterp < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `viennastaterp`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `viennastaterp`;

-- ============================================================
--  users
--  Haupttabelle fuer alle registrierten Nutzer.
--  Speichert Discord-Profil vollstaendig.
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,

  -- Discord Identitaet
  `discord_id`            VARCHAR(20)          NULL  COMMENT 'Discord Snowflake ID',
  `username`              VARCHAR(64)      NOT NULL  COMMENT 'Discord username (ohne #0000)',
  `global_name`           VARCHAR(64)      NOT NULL  DEFAULT '' COMMENT 'Anzeigename',
  `avatar`                VARCHAR(64)          NULL  COMMENT 'Avatar-Hash',
  `banner`                VARCHAR(64)          NULL  COMMENT 'Profil-Banner-Hash',
  `accent_color`          INT UNSIGNED         NULL  COMMENT 'Profil-Farbe (hex int)',
  `email`                 VARCHAR(255)         NULL,
  `discord_email_verified`TINYINT(1)       NOT NULL  DEFAULT 0 COMMENT 'E-Mail bei Discord bestaetigt',
  `discord_locale`        VARCHAR(10)          NULL  COMMENT 'z.B. de, en-US',
  `discord_mfa_enabled`   TINYINT(1)       NOT NULL  DEFAULT 0,
  `discord_premium_type`  TINYINT UNSIGNED NOT NULL  DEFAULT 0 COMMENT '0=kein Nitro,1=Classic,2=Nitro,3=Basic',
  `discord_public_flags`  INT UNSIGNED     NOT NULL  DEFAULT 0 COMMENT 'Badge-Flags (Bitfield)',

  -- Passwort-Login (optional)
  `password_hash`         VARCHAR(255)         NULL,

  -- Status
  `is_verified`           TINYINT(1)       NOT NULL  DEFAULT 1,
  `role`                  ENUM('player','supporter','moderator','admin') NOT NULL DEFAULT 'player',
  `is_whitelisted`        TINYINT(1)       NOT NULL  DEFAULT 0,
  `is_banned`             TINYINT(1)       NOT NULL  DEFAULT 0,
  `ban_reason`            TEXT                 NULL,
  `banned_by`             BIGINT UNSIGNED      NULL,
  `banned_at`             DATETIME             NULL,

  -- Zeitstempel
  `last_login`            DATETIME             NULL,
  `created_at`            DATETIME         NOT NULL  DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME         NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_discord_id` (`discord_id`),
  UNIQUE KEY `uq_username`   (`username`),
  UNIQUE KEY `uq_email`      (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  discord_tokens
--  OAuth2-Tokens getrennt gespeichert (Sicherheit).
--  Access- und Refresh-Token verschluesselt (AES-256-CBC).
-- ============================================================
CREATE TABLE IF NOT EXISTS `discord_tokens` (
  `user_id`        BIGINT UNSIGNED  NOT NULL,
  `access_token`   TEXT             NOT NULL  COMMENT 'Verschluesselt via vsrp_env_encrypt()',
  `refresh_token`  TEXT                 NULL  COMMENT 'Verschluesselt',
  `token_type`     VARCHAR(20)      NOT NULL  DEFAULT 'Bearer',
  `scope`          VARCHAR(255)         NULL  COMMENT 'z.B. identify email guilds',
  `expires_at`     DATETIME         NOT NULL  COMMENT 'Ablaufzeitpunkt',
  `created_at`     DATETIME         NOT NULL  DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME         NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  user_identifiers
--  FiveM / Steam / CFX Identifiers pro User.
--  Ein User kann mehrere (Steam-Account-Wechsel etc.).
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_identifiers` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       ENUM('license','steam','discord','xbl','live','ip','fivem') NOT NULL,
  `value`      VARCHAR(255)    NOT NULL  COMMENT 'z.B. license:abc123',
  `first_seen` DATETIME        NOT NULL  DEFAULT CURRENT_TIMESTAMP,
  `last_seen`  DATETIME        NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_type_val` (`user_id`, `type`, `value`),
  KEY `idx_value` (`value`),
  KEY `idx_type`  (`type`),
  CONSTRAINT `fk_ident_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  discord_guilds
--  Welchen Discord-Servern der User angehoert (gecached).
-- ============================================================
CREATE TABLE IF NOT EXISTS `discord_guilds` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `guild_id`    VARCHAR(20)     NOT NULL,
  `guild_name`  VARCHAR(100)        NULL,
  `is_owner`    TINYINT(1)      NOT NULL DEFAULT 0,
  `permissions` VARCHAR(20)         NULL  COMMENT 'Permissions-Bitfield als String',
  `checked_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_guild` (`user_id`, `guild_id`),
  KEY `idx_guild_id` (`guild_id`),
  CONSTRAINT `fk_guild_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  server_status_log
--  Historische Server-Status Snapshots (alle 5 Min gespeichert).
--  Fuer Statistiken, Uptime-Berechnung, Graphen.
-- ============================================================
CREATE TABLE IF NOT EXISTS `server_status_log` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `online`         TINYINT(1)    NOT NULL,
  `players`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `max_players`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `map`            VARCHAR(100)      NULL,
  `gametype`       VARCHAR(100)      NULL,
  `resource_count` SMALLINT UNSIGNED NULL,
  `upvote_power`   INT UNSIGNED      NULL,
  `snapshot_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  whitelist_applications
-- ============================================================
CREATE TABLE IF NOT EXISTS `whitelist_applications` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED  NOT NULL,
  `real_name`     VARCHAR(100)     NOT NULL,
  `age`           TINYINT UNSIGNED NOT NULL,
  `char_name`     VARCHAR(100)     NOT NULL,
  `char_story`    TEXT             NOT NULL,
  `motivation`    TEXT             NOT NULL,
  `rp_experience` TEXT             NOT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `staff_note`    TEXT                 NULL,
  `reviewed_by`   BIGINT UNSIGNED      NULL,
  `reviewed_at`   DATETIME             NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status`  (`status`),
  CONSTRAINT `fk_wl_user`  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wl_staff` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  login_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_log` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `method`       ENUM('discord','password') NOT NULL DEFAULT 'discord',
  `ip_hash`      VARCHAR(64)     NOT NULL,
  `user_agent`   VARCHAR(255)        NULL,
  `logged_in_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id`  (`user_id`),
  KEY `idx_logged_at`(`logged_in_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  activity_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `type`       VARCHAR(50)     NOT NULL,
  `message`    TEXT            NOT NULL,
  `user_id`    BIGINT UNSIGNED     NULL,
  `username`   VARCHAR(64)         NULL,
  `ip`         VARCHAR(45)         NULL,
  `meta`       JSON                NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type`    (`type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  rate_limits
-- ============================================================
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`           VARCHAR(45)  NOT NULL,
  `action`       VARCHAR(100) NOT NULL,
  `attempts`     INT          NOT NULL DEFAULT 1,
  `last_attempt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip_action` (`ip`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Migration fuer bestehende Installationen (ALTER TABLE)
--  Nur ausfuehren wenn Tabellen schon existieren.
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `banner`                 VARCHAR(64)      NULL    AFTER `avatar`,
  ADD COLUMN IF NOT EXISTS `accent_color`           INT UNSIGNED     NULL    AFTER `banner`,
  ADD COLUMN IF NOT EXISTS `discord_email_verified` TINYINT(1)   NOT NULL DEFAULT 0 AFTER `email`,
  ADD COLUMN IF NOT EXISTS `discord_locale`         VARCHAR(10)      NULL    AFTER `discord_email_verified`,
  ADD COLUMN IF NOT EXISTS `discord_mfa_enabled`    TINYINT(1)   NOT NULL DEFAULT 0 AFTER `discord_locale`,
  ADD COLUMN IF NOT EXISTS `discord_premium_type`   TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `discord_mfa_enabled`,
  ADD COLUMN IF NOT EXISTS `discord_public_flags`   INT UNSIGNED NOT NULL DEFAULT 0 AFTER `discord_premium_type`,
  ADD COLUMN IF NOT EXISTS `banned_by`              BIGINT UNSIGNED  NULL    AFTER `ban_reason`,
  ADD COLUMN IF NOT EXISTS `banned_at`              DATETIME         NULL    AFTER `banned_by`;

ALTER TABLE `login_log`
  ADD COLUMN IF NOT EXISTS `method` ENUM('discord','password') NOT NULL DEFAULT 'discord' AFTER `user_id`;