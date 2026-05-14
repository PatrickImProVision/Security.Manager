-- Product Store: personal user messages (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__personal_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `c_id` VARCHAR(128) NULL DEFAULT NULL,
  `subject` VARCHAR(180) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `__DB_PREFIX__personal_messages_c_id_unique` (`c_id`),
  KEY `__DB_PREFIX__personal_messages_sender_idx` (`sender_id`),
  KEY `__DB_PREFIX__personal_messages_recipient_idx` (`recipient_id`),
  KEY `__DB_PREFIX__personal_messages_status_idx` (`status`),
  KEY `__DB_PREFIX__personal_messages_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
