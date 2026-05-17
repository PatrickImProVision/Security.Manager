-- Product Store: phpBB-style forum fields on community_contents (MySQL).
ALTER TABLE `__DB_PREFIX__community_contents`
  ADD COLUMN `parent_id` INT UNSIGNED NULL DEFAULT NULL AFTER `author_id`,
  ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `parent_id`,
  ADD COLUMN `is_sticky` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_locked`,
  ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_sticky`,
  ADD COLUMN `last_reply_at` DATETIME NULL DEFAULT NULL AFTER `view_count`,
  ADD COLUMN `last_reply_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `last_reply_at`;

ALTER TABLE `__DB_PREFIX__community_contents`
  ADD KEY `__DB_PREFIX__community_contents_parent_idx` (`parent_id`),
  ADD KEY `__DB_PREFIX__community_contents_last_reply_idx` (`last_reply_at`);
