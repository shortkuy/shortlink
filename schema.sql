-- ============================================================
-- SafeLink Shared — MySQL Schema for Shared Hosting
-- ============================================================
-- Run this via phpMyAdmin → SQL tab
-- Optimized for shared hosting: small footprint, indexed, rate-limited
-- ============================================================

-- ============================================================
-- 1. Table: links (main short link storage)
-- ============================================================
CREATE TABLE IF NOT EXISTS `links` (
  `id` VARCHAR(20) NOT NULL,
  `dest` TEXT NOT NULL,
  `timer` TINYINT NOT NULL DEFAULT 5,
  `mode` ENUM('shortlink','safelink') NOT NULL DEFAULT 'safelink',
  `title` VARCHAR(255) DEFAULT NULL,
  `article_title` VARCHAR(500) DEFAULT NULL,
  `article_intro` TEXT DEFAULT NULL,
  `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_click_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creator_ip` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_links_created_at` (`created_at`),
  KEY `idx_links_mode` (`mode`),
  KEY `idx_links_creator_ip` (`creator_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Table: rate_limits (anti-abuse, prevent suspension)
-- ============================================================
-- Tracks API calls per IP within time window. Auto-cleans old entries.
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `ip` VARCHAR(45) NOT NULL,
  `endpoint` VARCHAR(50) NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`, `endpoint`),
  KEY `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Table: settings (key-value store for site config)
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. Default settings
-- ============================================================
INSERT INTO `settings` (`key`, `value`) VALUES
  ('site_name', 'SafeLink'),
  ('site_tagline', 'Shortlink & Safelink Generator'),
  ('default_timer', '5'),
  ('default_mode', 'safelink'),
  ('default_id_length', '6'),
  ('max_links_per_ip_per_day', '50'),
  ('rate_limit_window_minutes', '60'),
  ('rate_limit_max_create', '20'),
  ('rate_limit_max_get', '120'),
  ('rate_limit_max_increment', '120'),
  ('rate_limit_max_list', '30'),
  ('enable_ads', 'true')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ============================================================
-- 5. Migration for existing installations (safe to run)
-- ============================================================
-- If you already have the `links` table from a previous version,
-- run these ALTER statements to add the new columns:
ALTER TABLE `links` ADD COLUMN IF NOT EXISTS `article_title` VARCHAR(500) DEFAULT NULL AFTER `title`;
ALTER TABLE `links` ADD COLUMN IF NOT EXISTS `article_intro` TEXT DEFAULT NULL AFTER `article_title`;

-- ============================================================
-- 6. Done! Verify with:
--    SHOW TABLES;
--    DESCRIBE links;
--    SELECT * FROM settings;
-- ============================================================

-- Note: All operations (insert, get, increment, list, delete)
-- are handled by api.php with prepared statements + rate limiting.
-- Never run queries directly from client JavaScript.
