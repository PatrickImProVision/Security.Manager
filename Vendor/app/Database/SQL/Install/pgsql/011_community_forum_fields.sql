-- Product Store: phpBB-style forum fields on community_contents (PostgreSQL).
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN IF NOT EXISTS parent_id INTEGER NULL;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN IF NOT EXISTS is_locked BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN IF NOT EXISTS is_sticky BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN IF NOT EXISTS view_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN IF NOT EXISTS last_reply_at TIMESTAMP NULL;
ALTER TABLE __DB_PREFIX__community_contents ADD COLUMN IF NOT EXISTS last_reply_user_id INTEGER NULL;

CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_parent_idx ON __DB_PREFIX__community_contents (parent_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_last_reply_idx ON __DB_PREFIX__community_contents (last_reply_at);
