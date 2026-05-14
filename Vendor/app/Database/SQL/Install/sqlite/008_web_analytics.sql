-- Product Store: whole-site web usage analytics (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__web_analytics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  route_path TEXT NOT NULL,
  request_method TEXT NOT NULL,
  member_user_id INTEGER NULL,
  ip_address TEXT NOT NULL DEFAULT '',
  user_agent TEXT NOT NULL DEFAULT '',
  referrer TEXT NOT NULL DEFAULT '',
  occurred_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__web_analytics_occurred_idx ON __DB_PREFIX__web_analytics (occurred_at);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__web_analytics_route_idx ON __DB_PREFIX__web_analytics (route_path);
