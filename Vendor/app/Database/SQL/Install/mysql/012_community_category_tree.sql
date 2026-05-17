-- Nested forum categories (phpBB-style). Token __DB_PREFIX__ is replaced at install time.
ALTER TABLE `__DB_PREFIX__community_categories`
  ADD COLUMN IF NOT EXISTS `parent_id` INT UNSIGNED NULL DEFAULT NULL AFTER `is_system`,
  ADD COLUMN IF NOT EXISTS `slug` VARCHAR(100) NULL DEFAULT NULL AFTER `parent_id`;

ALTER TABLE `__DB_PREFIX__community_contents`
  ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED NULL DEFAULT NULL AFTER `category`;
