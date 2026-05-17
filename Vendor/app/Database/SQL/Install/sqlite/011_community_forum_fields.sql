-- Product Store: phpBB-style forum fields on community_contents (SQLite).
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN parent_id INTEGER NULL;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN is_sticky INTEGER NOT NULL DEFAULT 0;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN view_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN last_reply_at TEXT NULL;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN last_reply_user_id INTEGER NULL;

CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_parent_idx ON __DB_PREFIX__community_contents (parent_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_last_reply_idx ON __DB_PREFIX__community_contents (last_reply_at);
