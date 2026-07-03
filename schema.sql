-- ============================================================
--  ViennaStateRP - Datenbankschema
--  MySQL 8.0+ / MariaDB 10.6+
--  Ausfuehren: mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `viennastaterp`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `viennastaterp`;

-- users
CREATE TABLE IF NOT EXISTS `users` (
  `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `discord_id`      VARCHAR(20)      NOT NULL,
  `username`        VARCHAR(64)      NOT NULL,
  `global_name`     VARCHAR(64)      NOT NULL DEFAULT '',
  `avatar`          VARCHAR(64)          NULL,
  `email`           VARCHAR(255)         NULL,
  `role`            ENUM('player','supporter','moderator','admin') NOT NULL DEFAULT 'player',
  `is_whitelisted`  TINYINT(1)       NOT NULL DEFAULT 0,
  `is_banned`       TINYINT(1)       NOT NULL DEFAULT 0,
  `ban_reason`      TEXT                 NULL,
  `last_login`      DATETIME             NULL,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_discord_id` (`discord_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
d
-- whitelist_applications
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

-- login_log
CREATE TABLE IF NOT EXISTS `login_log` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `ip_hash`      VARCHAR(64)     NOT NULL,
  `user_agent`   VARCHAR(255)        NULL,
  `logged_in_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- activity_logs
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

-- rate_limits
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`           VARCHAR(45)  NOT NULL,
  `action`       VARCHAR(100) NOT NULL,
  `attempts`     INT          NOT NULL DEFAULT 1,
  `last_attempt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip_action` (`ip`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
