-- Product Store: whole-site web usage analytics (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__web_analytics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `route_path` VARCHAR(255) NOT NULL,
  `request_method` VARCHAR(12) NOT NULL,
  `member_user_id` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `referrer` VARCHAR(255) NOT NULL DEFAULT '',
  `occurred_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `__DB_PREFIX__web_analytics_occurred_idx` (`occurred_at`),
  KEY `__DB_PREFIX__web_analytics_route_idx` (`route_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
