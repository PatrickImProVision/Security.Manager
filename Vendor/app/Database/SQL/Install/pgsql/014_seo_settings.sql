-- Product Store: site-wide SEO defaults (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__seo_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

INSERT INTO __DB_PREFIX__seo_settings (setting_key, setting_value, created_at, updated_at) VALUES
('seo_meta_title', 'Change Name', CURRENT_TIMESTAMP, NULL),
('seo_meta_description', 'Change Description', CURRENT_TIMESTAMP, NULL),
('seo_meta_keywords', '', CURRENT_TIMESTAMP, NULL),
('seo_canonical_url', '', CURRENT_TIMESTAMP, NULL),
('seo_robots', 'index,follow', CURRENT_TIMESTAMP, NULL),
('seo_og_image', '', CURRENT_TIMESTAMP, NULL)
ON CONFLICT (setting_key) DO NOTHING;
