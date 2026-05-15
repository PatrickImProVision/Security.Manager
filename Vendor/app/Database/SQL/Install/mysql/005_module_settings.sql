-- Product Store: module settings (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__module_settings` (
  `module_key` VARCHAR(80) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `__DB_PREFIX__module_settings` (`module_key`, `label`, `description`, `is_enabled`, `created_at`, `updated_at`) VALUES
('content_public', 'Content Manager - Public', 'Public pages, navigation pages, and public content display.', 1, CURRENT_TIMESTAMP, NULL),
('content_community', 'Content Manager - Community', 'Community posts shared between members and public visitors.', 1, CURRENT_TIMESTAMP, NULL),
('content_personal', 'Content Manager - Personal', 'Private messages sent between registered users.', 1, CURRENT_TIMESTAMP, NULL),
('web_analytics', 'Web Usage Analytics', 'Whole-site request tracking and Dashboard usage graph.', 1, CURRENT_TIMESTAMP, NULL),
-- CANG defaults and language_id map: see 010_security_cang_profiles.sql (Security Manager).
('security_manager', 'Security Manager', 'Security tools, CANG profiles, and application ID generator settings.', 1, CURRENT_TIMESTAMP, NULL)
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `description` = VALUES(`description`);
