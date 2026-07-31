-- ---------------------------------------------------------------------
-- ShareCrate - database schema (MySQL 5.7+ / MariaDB 10.3+)
-- Charset utf8mb4, engine InnoDB.
-- ---------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Users: administrators and uploaders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(64)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `display_name`  VARCHAR(120) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','uploader') NOT NULL DEFAULT 'uploader',
  `locale`        VARCHAR(5)   DEFAULT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `quota_bytes`   BIGINT UNSIGNED DEFAULT NULL,
  `last_login_at` DATETIME     DEFAULT NULL,
  `last_login_ip` VARCHAR(45)  DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Files: one row per shared file
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `files` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alias`            VARCHAR(160) NOT NULL,
  `title`            VARCHAR(190) DEFAULT NULL,
  `description`      TEXT         DEFAULT NULL,
  `original_name`    VARCHAR(255) NOT NULL,
  `path`             VARCHAR(512) NOT NULL COMMENT 'relative to STORAGE_PATH',
  `path_hash`        CHAR(64)     NOT NULL COMMENT 'sha256(path), used for uniqueness',
  `extension`        VARCHAR(32)  DEFAULT NULL,
  `mime_type`        VARCHAR(160) DEFAULT NULL,
  `size_bytes`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `checksum`         CHAR(64)     DEFAULT NULL,
  `password_hash`    VARCHAR(255) DEFAULT NULL,
  `owner_id`         INT UNSIGNED DEFAULT NULL,
  `source`           ENUM('web','ftp','cli') NOT NULL DEFAULT 'web',
  `status`           ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `allow_preview`    TINYINT(1)   NOT NULL DEFAULT 1,
  `expires_at`       DATETIME     DEFAULT NULL,
  `max_downloads`    INT UNSIGNED DEFAULT NULL,
  `download_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `last_download_at` DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_files_alias` (`alias`),
  UNIQUE KEY `uq_files_path_hash` (`path_hash`),
  KEY `idx_files_owner` (`owner_id`),
  KEY `idx_files_created` (`created_at`),
  KEY `idx_files_status` (`status`),
  CONSTRAINT `fk_files_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Downloads: one row per served download
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `downloads` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_id`    INT UNSIGNED NOT NULL,
  `ip`         VARCHAR(45)  DEFAULT NULL COMMENT 'NULL when privacy mode hides it',
  `ip_hash`    CHAR(64)     NOT NULL COMMENT 'sha256(ip + APP_KEY) for unique counting',
  `country`    CHAR(2)      DEFAULT NULL,
  `city`       VARCHAR(120) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `browser`    VARCHAR(60)  DEFAULT NULL,
  `platform`   VARCHAR(60)  DEFAULT NULL,
  `referer`    VARCHAR(255) DEFAULT NULL,
  `bytes_sent` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_downloads_file` (`file_id`, `created_at`),
  KEY `idx_downloads_created` (`created_at`),
  KEY `idx_downloads_ip_hash` (`ip_hash`),
  CONSTRAINT `fk_downloads_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Key/value application settings editable from the dashboard
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- "Remember me" tokens (selector / validator pattern)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `selector`       CHAR(32)  NOT NULL,
  `validator_hash` CHAR(64)  NOT NULL,
  `user_agent`     VARCHAR(255) DEFAULT NULL,
  `expires_at`     DATETIME  NOT NULL,
  `created_at`     DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_selector` (`selector`),
  KEY `idx_remember_user` (`user_id`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Brute force protection for logins and file passwords
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `bucket`     VARCHAR(160) NOT NULL,
  `attempts`   INT UNSIGNED NOT NULL DEFAULT 0,
  `reset_at`   DATETIME NOT NULL,
  PRIMARY KEY (`bucket`),
  KEY `idx_rate_limits_reset` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cache for IP -> country lookups (keeps the geo API calls rare)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `geo_cache` (
  `ip_hash`    CHAR(64) NOT NULL,
  `country`    CHAR(2)  DEFAULT NULL,
  `city`       VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip_hash`),
  KEY `idx_geo_cache_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Audit trail shown as "recent activity" in the dashboard
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(60)  NOT NULL,
  `subject`    VARCHAR(190) DEFAULT NULL,
  `meta`       TEXT         DEFAULT NULL,
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_user` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Default settings
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('site_name',          'ShareCrate'),
  ('site_tagline',       ''),
  ('contact_email',      ''),
  ('privacy_ip_mode',    'full'),
  ('log_retention_days', '365'),
  ('alias_style',        'slug'),
  ('alias_random_len',   '6'),
  ('allow_uploader_delete', '1'),
  ('max_upload_mb',      '0'),
  ('installed_at',       NULL)
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
