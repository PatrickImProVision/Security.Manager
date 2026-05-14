-- Product Store: Security Manager CANG profiles (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__security_cang_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  target_key TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  language_id INTEGER NOT NULL DEFAULT 7,
  code_length INTEGER NOT NULL DEFAULT 12,
  generation_mode TEXT NOT NULL DEFAULT 'random',
  is_active INTEGER NOT NULL DEFAULT 1,
  sequence_value INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

INSERT OR IGNORE INTO __DB_PREFIX__security_cang_profiles (target_key, label, description, language_id, code_length, generation_mode, is_active, sequence_value, created_at, updated_at) VALUES
('user_url_id', 'User Url.Id', 'Public user URL identifier.', 5, 12, 'random', 1, 0, CURRENT_TIMESTAMP, NULL),
('password_id', 'Password.Id', 'Activation, reset, and password related security identifiers.', 9, 32, 'random', 1, 0, CURRENT_TIMESTAMP, NULL),
('public_content_url_id', 'Public Content Url.Id', 'Public content URL identifier.', 8, 12, 'random', 1, 0, CURRENT_TIMESTAMP, NULL),
('community_content_url_id', 'Community Content Url.Id', 'Community content URL identifier.', 8, 12, 'random', 1, 0, CURRENT_TIMESTAMP, NULL),
('personal_content_url_id', 'Personal Content Url.Id', 'Personal content/message URL identifier.', 8, 12, 'random', 1, 0, CURRENT_TIMESTAMP, NULL),
('product_key_id', 'Product Key.Id', 'Future product key identifier.', 5, 20, 'random', 1, 0, CURRENT_TIMESTAMP, NULL);
