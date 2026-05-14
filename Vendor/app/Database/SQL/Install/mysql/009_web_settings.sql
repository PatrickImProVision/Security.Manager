-- Product Store: web display settings (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__web_settings` (
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `__DB_PREFIX__web_settings` (`setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
('web_name', 'Change Name', CURRENT_TIMESTAMP, NULL),
('web_description', 'Change Description', CURRENT_TIMESTAMP, NULL)
ON DUPLICATE KEY UPDATE
  `setting_value` = `setting_value`;
