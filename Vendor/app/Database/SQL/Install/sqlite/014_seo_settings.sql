-- Product Store: site-wide SEO defaults (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__seo_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

INSERT OR IGNORE INTO __DB_PREFIX__seo_settings (setting_key, setting_value, created_at, updated_at) VALUES
('seo_meta_title', 'Change Name', datetime('now'), NULL),
('seo_meta_description', 'Change Description', datetime('now'), NULL),
('seo_meta_keywords', '', datetime('now'), NULL),
('seo_canonical_url', '', datetime('now'), NULL),
('seo_robots', 'index,follow', datetime('now'), NULL),
('seo_og_image', '', datetime('now'), NULL);
