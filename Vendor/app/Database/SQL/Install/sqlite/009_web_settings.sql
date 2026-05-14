-- Product Store: web display settings (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__web_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

INSERT OR IGNORE INTO __DB_PREFIX__web_settings (setting_key, setting_value, created_at, updated_at) VALUES
('web_name', 'Change Name', CURRENT_TIMESTAMP, NULL),
('web_description', 'Change Description', CURRENT_TIMESTAMP, NULL);
