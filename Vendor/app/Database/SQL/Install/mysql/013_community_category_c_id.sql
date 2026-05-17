-- Community forum/category CANG URL id (Security Manager). Token __DB_PREFIX__ is replaced at install time.
ALTER TABLE `__DB_PREFIX__community_categories`
  ADD COLUMN IF NOT EXISTS `c_id` VARCHAR(128) NULL DEFAULT NULL AFTER `slug`;
