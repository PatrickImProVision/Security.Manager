-- Product Store: module settings (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__module_settings (
  module_key TEXT PRIMARY KEY,
  label TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  is_enabled INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

INSERT OR IGNORE INTO __DB_PREFIX__module_settings (module_key, label, description, is_enabled, created_at, updated_at) VALUES
('content_public', 'Content Manager - Public', 'Public pages, navigation pages, and public content display.', 1, CURRENT_TIMESTAMP, NULL),
('content_community', 'Content Manager - Community', 'Community posts shared between members and public visitors.', 1, CURRENT_TIMESTAMP, NULL),
('content_personal', 'Content Manager - Personal', 'Private messages sent between registered users.', 1, CURRENT_TIMESTAMP, NULL),
('web_analytics', 'Web Usage Analytics', 'Whole-site request tracking and Dashboard usage graph.', 1, CURRENT_TIMESTAMP, NULL),
('security_manager', 'Security Manager', 'Security tools, CANG profiles, and application ID generator settings.', 1, CURRENT_TIMESTAMP, NULL);
