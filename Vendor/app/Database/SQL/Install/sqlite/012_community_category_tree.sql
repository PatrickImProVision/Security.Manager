-- Nested forum categories (phpBB-style). SQLite has no IF NOT EXISTS on ADD COLUMN; runtime migration handles existing DBs.
ALTER TABLE __DB_PREFIX__community_categories ADD COLUMN parent_id INTEGER NULL;
ALTER TABLE __DB_PREFIX__community_categories ADD COLUMN slug TEXT NULL;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN category_id INTEGER NULL;
