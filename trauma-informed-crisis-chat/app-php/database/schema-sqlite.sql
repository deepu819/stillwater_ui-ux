-- ---------------------------------------------------------------------------
-- Stillwater - SQLite schema. This file is documentation only: the app creates
-- these tables itself on first run (see includes/db.php). Kept here so the
-- data model in the report matches the code exactly.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sessions (
  id          TEXT    NOT NULL PRIMARY KEY,
  alias       TEXT    NOT NULL,
  consent     INTEGER NOT NULL DEFAULT 1,
  keep_words  INTEGER NOT NULL DEFAULT 0,
  tier_max    INTEGER NOT NULL DEFAULT 0,
  msg_count   INTEGER NOT NULL DEFAULT 0,
  recent      TEXT    NULL,
  created_at  DATETIME NOT NULL,
  last_seen   DATETIME NOT NULL,
  ended_at    DATETIME NULL,
  deleted_at  DATETIME NULL
);

CREATE TABLE IF NOT EXISTS messages (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  session_id  TEXT    NOT NULL,
  role        VARCHAR(16) NOT NULL,
  body        TEXT    NULL,
  category    TEXT    NULL,
  tier        INTEGER NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS risk_events (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  session_id  TEXT    NOT NULL,
  category    TEXT    NOT NULL,
  tier        INTEGER NOT NULL,
  created_at  DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS escalations (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  session_id  TEXT    NOT NULL,
  tier        INTEGER NOT NULL,
  shown_at    DATETIME NOT NULL,
  accepted    INTEGER NULL,
  resolved_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS grounding_runs (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  session_id  TEXT    NOT NULL,
  kind        TEXT    NOT NULL,
  rounds      INTEGER NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_messages_session ON messages (session_id);
CREATE INDEX IF NOT EXISTS idx_risk_session     ON risk_events (session_id);
CREATE INDEX IF NOT EXISTS idx_esc_session      ON escalations (session_id);
