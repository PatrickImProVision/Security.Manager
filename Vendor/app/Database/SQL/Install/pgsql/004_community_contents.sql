-- Product Store: community content tables (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__community_contents (
  id SERIAL PRIMARY KEY,
  c_id VARCHAR(128) NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'Unknown',
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'published',
  author_id INTEGER NULL,
  parent_id INTEGER NULL,
  is_locked BOOLEAN NOT NULL DEFAULT FALSE,
  is_sticky BOOLEAN NOT NULL DEFAULT FALSE,
  view_count INTEGER NOT NULL DEFAULT 0,
  last_reply_at TIMESTAMP NULL,
  last_reply_user_id INTEGER NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_category_idx ON __DB_PREFIX__community_contents (category);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_status_idx ON __DB_PREFIX__community_contents (status);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_author_idx ON __DB_PREFIX__community_contents (author_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_created_idx ON __DB_PREFIX__community_contents (created_at);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_parent_idx ON __DB_PREFIX__community_contents (parent_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_last_reply_idx ON __DB_PREFIX__community_contents (last_reply_at);
