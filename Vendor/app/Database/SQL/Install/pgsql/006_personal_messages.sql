-- Product Store: personal user messages (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__personal_messages (
  id SERIAL PRIMARY KEY,
  c_id VARCHAR(128) NULL UNIQUE,
  subject VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  sender_id INTEGER NOT NULL,
  recipient_id INTEGER NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'sent',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_sender_idx ON __DB_PREFIX__personal_messages (sender_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_recipient_idx ON __DB_PREFIX__personal_messages (recipient_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_status_idx ON __DB_PREFIX__personal_messages (status);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_created_idx ON __DB_PREFIX__personal_messages (created_at);
