-- Product Store: community content tables (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__community_contents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  c_id TEXT UNIQUE,
  title TEXT NOT NULL,
  category TEXT NOT NULL DEFAULT 'Unknown',
  body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'published',
  author_id INTEGER,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_category_idx ON __DB_PREFIX__community_contents (category);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_status_idx ON __DB_PREFIX__community_contents (status);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_author_idx ON __DB_PREFIX__community_contents (author_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_contents_created_idx ON __DB_PREFIX__community_contents (created_at);
