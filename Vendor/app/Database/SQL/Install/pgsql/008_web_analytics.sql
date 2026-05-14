-- Product Store: whole-site web usage analytics (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__web_analytics (
  id SERIAL PRIMARY KEY,
  route_path VARCHAR(255) NOT NULL,
  request_method VARCHAR(12) NOT NULL,
  member_user_id INTEGER NULL,
  ip_address VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  referrer VARCHAR(255) NOT NULL DEFAULT '',
  occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__web_analytics_occurred_idx ON __DB_PREFIX__web_analytics (occurred_at);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__web_analytics_route_idx ON __DB_PREFIX__web_analytics (route_path);
