-- Product Store: community content categories (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__community_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `__DB_PREFIX__community_categories_name_unique` (`name`),
  KEY `__DB_PREFIX__community_categories_active_idx` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `__DB_PREFIX__community_categories` (`name`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES
('Unknown', 'Posts created without a category.', 0, 1, 1, CURRENT_TIMESTAMP, NULL)
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `is_active` = 1,
  `is_system` = 1;
