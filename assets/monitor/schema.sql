CREATE TABLE IF NOT EXISTS ew_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, dedup CHAR(64) NOT NULL UNIQUE,
 observed_at DATETIME NOT NULL, path VARCHAR(512) NOT NULL, method VARCHAR(8) NOT NULL,
 status SMALLINT NOT NULL, duration_ms INT NOT NULL DEFAULT 0,
 bot VARCHAR(80) NOT NULL, provider VARCHAR(80) NOT NULL, purpose VARCHAR(40) NOT NULL,
 verification VARCHAR(32) NOT NULL, rule_hash VARCHAR(64) NOT NULL DEFAULT '', rule_version VARCHAR(32) NOT NULL DEFAULT '',
 source VARCHAR(64) NOT NULL, scope VARCHAR(16) NOT NULL,
 INDEX window_idx(scope,source,observed_at), INDEX bot_idx(bot,observed_at)
);
CREATE TABLE IF NOT EXISTS ew_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_id CHAR(64) NOT NULL UNIQUE,
 observed_at DATETIME NOT NULL, path VARCHAR(512) NOT NULL, event_name VARCHAR(40) NOT NULL,
 visitor CHAR(64) NOT NULL, referral VARCHAR(80) NOT NULL, device VARCHAR(16) NOT NULL,
 scope VARCHAR(16) NOT NULL, INDEX window_idx(scope,observed_at), INDEX rate_idx(visitor,observed_at)
);
CREATE TABLE IF NOT EXISTS ew_meta (name VARCHAR(80) PRIMARY KEY, value TEXT NOT NULL);
