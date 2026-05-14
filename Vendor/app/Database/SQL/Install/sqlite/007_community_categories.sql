-- Product Store: community content categories (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__community_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  description TEXT NOT NULL DEFAULT '',
  sort_order INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  is_system INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_categories_active_idx ON __DB_PREFIX__community_categories (is_active, sort_order);

INSERT OR IGNORE INTO __DB_PREFIX__community_categories (name, description, sort_order, is_active, is_system, created_at, updated_at) VALUES
('Unknown', 'Posts created without a category.', 0, 1, 1, CURRENT_TIMESTAMP, NULL);
