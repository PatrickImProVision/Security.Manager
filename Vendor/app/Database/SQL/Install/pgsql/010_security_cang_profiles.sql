-- =============================================================================
-- Product Store: Security Manager — CANG profiles (PostgreSQL)
-- Token __DB_PREFIX__ is replaced at install time (may be empty).
--
-- language_id = CANG selector (metadata in App\Libraries\SecurityCangService):
--   1  Alphabet_Upper                   type [A-Z]
--   2  Alphabet_Lower                   type [a-z]
--   3  Alphabet_Mix                     type [A-Z,a-z]
--   4  Numeric                            type [0-9]
--   5  Alphabet_Upper_Num               type [A-Z,0-9]
--   6  Alphabet_Lower_Num               type [a-z,0-9]
--   7  Alphabet_Mix_Num                 type [A-Z,a-z,0-9]
--   8  Alphabet_Mix_Num_SpecialShort      type [A-Z,a-z,0-9,-_]
--   9  Alphabet_Mix_Num_SpecialFull     type [A-Z,a-z,0-9,@-?]  (display label;
--        generation pool = A-Z, a-z, 0-9 plus @ # $ % & * - _ = + : ? in PHP)
--
-- Character pools for generation: SecurityCangService::charactersForLanguage()
-- split_by / split_length: optional grouping applied after generation (formatted length <= 128).
-- Keep INSERT defaults aligned with SecurityCangService::DEFAULT_PROFILES.
-- Re-run: ON CONFLICT does not update existing rows; change data via app or migration.
-- =============================================================================

CREATE TABLE IF NOT EXISTS __DB_PREFIX__security_cang_profiles (
  id SERIAL PRIMARY KEY,
  target_key VARCHAR(80) NOT NULL UNIQUE,
  label VARCHAR(140) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  language_id INTEGER NOT NULL DEFAULT 7,
  code_length INTEGER NOT NULL DEFAULT 12,
  generation_mode VARCHAR(20) NOT NULL DEFAULT 'random',
  split_by VARCHAR(16) NOT NULL DEFAULT '',
  split_length INTEGER NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  sequence_value INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

INSERT INTO __DB_PREFIX__security_cang_profiles (target_key, label, description, language_id, code_length, generation_mode, is_active, sequence_value, created_at, updated_at) VALUES
('user_url_id', 'User Url.Id', 'Public user URL identifier.', 5, 12, 'random', TRUE, 0, CURRENT_TIMESTAMP, NULL),
('password_id', 'Password.Id', 'Activation, reset, and password related security identifiers.', 9, 32, 'random', TRUE, 0, CURRENT_TIMESTAMP, NULL),
('public_content_url_id', 'Public Content Url.Id', 'Public content URL identifier.', 8, 12, 'random', TRUE, 0, CURRENT_TIMESTAMP, NULL),
('community_content_url_id', 'Community Content Url.Id', 'Community content URL identifier.', 8, 12, 'random', TRUE, 0, CURRENT_TIMESTAMP, NULL),
('personal_content_url_id', 'Personal Content Url.Id', 'Personal content/message URL identifier.', 8, 12, 'random', TRUE, 0, CURRENT_TIMESTAMP, NULL),
('product_key_id', 'Product Key.Id', 'Future product key identifier.', 5, 20, 'random', TRUE, 0, CURRENT_TIMESTAMP, NULL)
ON CONFLICT (target_key) DO NOTHING;
