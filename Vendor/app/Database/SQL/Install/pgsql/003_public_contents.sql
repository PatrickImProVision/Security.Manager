-- Product Store: public content tables (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__public_contents (
  id SERIAL PRIMARY KEY,
  c_id VARCHAR(128) NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(191) NOT NULL UNIQUE,
  summary VARCHAR(500) NULL,
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  show_in_nav BOOLEAN NOT NULL DEFAULT FALSE,
  nav_label VARCHAR(100) NULL,
  nav_order INTEGER NOT NULL DEFAULT 0,
  author_id INTEGER NULL,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__public_contents_status_idx ON __DB_PREFIX__public_contents (status);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__public_contents_nav_idx ON __DB_PREFIX__public_contents (show_in_nav, nav_order);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__public_contents_published_at_idx ON __DB_PREFIX__public_contents (published_at);
