-- Product Store: community content categories (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__community_categories (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INTEGER NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  is_system BOOLEAN NOT NULL DEFAULT FALSE,
  parent_id INTEGER NULL,
  slug VARCHAR(100) NULL,
  c_id VARCHAR(128) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__community_categories_active_idx ON __DB_PREFIX__community_categories (is_active, sort_order);

INSERT INTO __DB_PREFIX__community_categories (name, description, sort_order, is_active, is_system, parent_id, slug, created_at, updated_at) VALUES
('Unknown', 'Posts created without a category.', 0, TRUE, TRUE, NULL, 'unknown', CURRENT_TIMESTAMP, NULL)
ON CONFLICT (name) DO UPDATE SET
  description = EXCLUDED.description,
  is_active = TRUE,
  is_system = TRUE;
