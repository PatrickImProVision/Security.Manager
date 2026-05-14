-- Product Store: personal user messages (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__personal_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  c_id TEXT UNIQUE,
  subject TEXT NOT NULL,
  body TEXT NOT NULL,
  sender_id INTEGER NOT NULL,
  recipient_id INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'sent',
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_sender_idx ON __DB_PREFIX__personal_messages (sender_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_recipient_idx ON __DB_PREFIX__personal_messages (recipient_id);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_status_idx ON __DB_PREFIX__personal_messages (status);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__personal_messages_created_idx ON __DB_PREFIX__personal_messages (created_at);
