-- Product Store: module settings (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__module_settings (
  module_key VARCHAR(80) PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

INSERT INTO __DB_PREFIX__module_settings (module_key, label, description, is_enabled, created_at, updated_at) VALUES
('content_public', 'Content Manager - Public', 'Public pages, navigation pages, and public content display.', TRUE, CURRENT_TIMESTAMP, NULL),
('content_community', 'Content Manager - Community', 'Community posts shared between members and public visitors.', TRUE, CURRENT_TIMESTAMP, NULL),
('content_personal', 'Content Manager - Personal', 'Private messages sent between registered users.', TRUE, CURRENT_TIMESTAMP, NULL),
('web_analytics', 'Web Usage Analytics', 'Whole-site request tracking and Dashboard usage graph.', TRUE, CURRENT_TIMESTAMP, NULL),
('security_manager', 'Security Manager', 'Security tools, CANG profiles, and application ID generator settings.', TRUE, CURRENT_TIMESTAMP, NULL)
ON CONFLICT (module_key) DO NOTHING;
