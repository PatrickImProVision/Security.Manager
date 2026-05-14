-- Product Store: public content tables (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__public_contents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `c_id` VARCHAR(128) NULL DEFAULT NULL,
  `title` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `summary` VARCHAR(500) NULL DEFAULT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `show_in_nav` TINYINT(1) NOT NULL DEFAULT 0,
  `nav_label` VARCHAR(100) NULL DEFAULT NULL,
  `nav_order` INT NOT NULL DEFAULT 0,
  `author_id` INT UNSIGNED NULL DEFAULT NULL,
  `published_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `__DB_PREFIX__public_contents_c_id_unique` (`c_id`),
  UNIQUE KEY `__DB_PREFIX__public_contents_slug_unique` (`slug`),
  KEY `__DB_PREFIX__public_contents_status_idx` (`status`),
  KEY `__DB_PREFIX__public_contents_nav_idx` (`show_in_nav`, `nav_order`),
  KEY `__DB_PREFIX__public_contents_published_at_idx` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
