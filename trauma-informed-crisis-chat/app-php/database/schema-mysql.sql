-- ---------------------------------------------------------------------------
-- Stillwater - MySQL schema (XAMPP / phpMyAdmin)
--
-- Import this only if you set 'driver' => 'mysql' in includes/config.php.
-- With the default 'sqlite' driver the same tables are created automatically
-- on first page load, so nothing has to be imported at all.
--
-- phpMyAdmin:  Databases -> New -> name it "stillwater" -> Import -> this file
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS stillwater
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stillwater;

-- One row per conversation. No name, no email, no IP address, no user agent.
-- `id` is a random 32-character hex string generated per tab.
CREATE TABLE IF NOT EXISTS sessions (
  id          VARCHAR(64)  NOT NULL PRIMARY KEY,
  alias       VARCHAR(255) NOT NULL,               -- generated word-name, e.g. quiet-river-42
  consent     TINYINT(1)   NOT NULL DEFAULT 1,     -- transparency sheet acknowledged
  keep_words  TINYINT(1)   NOT NULL DEFAULT 0,     -- 0 = do not store message text (default)
  tier_max    INT          NOT NULL DEFAULT 0,     -- highest concern level reached
  msg_count   INT          NOT NULL DEFAULT 0,
  recent      TEXT         NULL,                   -- reply ids already used, to avoid repetition
  created_at  DATETIME     NOT NULL,
  last_seen   DATETIME     NOT NULL,
  ended_at    DATETIME     NULL,
  deleted_at  DATETIME     NULL                    -- set when the person deletes the conversation
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat transcript. `body` stays NULL unless keep_words = 1: the words are not
-- stored by default, only the category and tier of what was said.
CREATE TABLE IF NOT EXISTS messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id  VARCHAR(64)  NOT NULL,
  role        VARCHAR(16)  NOT NULL,               -- 'user' | 'guide'
  body        TEXT         NULL,
  category    VARCHAR(255) NULL,
  tier        INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL,
  KEY idx_messages_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Safety events, kept as categories only (never the matched phrase).
CREATE TABLE IF NOT EXISTS risk_events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id  VARCHAR(64)  NOT NULL,
  category    VARCHAR(255) NOT NULL,
  tier        INT          NOT NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_risk_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every time a human handoff was offered, and whether it was taken.
-- This is the evidence for the report's escalation analysis.
CREATE TABLE IF NOT EXISTS escalations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id  VARCHAR(64)  NOT NULL,
  tier        INT          NOT NULL,
  shown_at    DATETIME     NOT NULL,
  accepted    TINYINT(1)   NULL,                   -- NULL = still deciding / never answered
  resolved_at DATETIME     NULL,
  KEY idx_esc_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grounding exercises used (breathing, 5-4-3-2-1).
CREATE TABLE IF NOT EXISTS grounding_runs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id  VARCHAR(64)  NOT NULL,
  kind        VARCHAR(255) NOT NULL,
  rounds      INT          NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
