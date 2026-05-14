-- Product Store: public content tables (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__public_contents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  c_id TEXT UNIQUE,
  title TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  summary TEXT,
  body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'draft',
  show_in_nav INTEGER NOT NULL DEFAULT 0,
  nav_label TEXT,
  nav_order INTEGER NOT NULL DEFAULT 0,
  author_id INTEGER,
  published_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__public_contents_status_idx ON __DB_PREFIX__public_contents (status);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__public_contents_nav_idx ON __DB_PREFIX__public_contents (show_in_nav, nav_order);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__public_contents_published_at_idx ON __DB_PREFIX__public_contents (published_at);
