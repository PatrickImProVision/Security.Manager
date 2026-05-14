-- Product Store: web display settings (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__web_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

INSERT INTO __DB_PREFIX__web_settings (setting_key, setting_value, created_at, updated_at) VALUES
('web_name', 'Change Name', CURRENT_TIMESTAMP, NULL),
('web_description', 'Change Description', CURRENT_TIMESTAMP, NULL)
ON CONFLICT (setting_key) DO NOTHING;
