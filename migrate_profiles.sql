-- CSHunter: Profile system migration
-- Run once on your server:
--   mysql -u USER -p DBNAME < migrate_profiles.sql

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS country           VARCHAR(5)   NOT NULL DEFAULT '' AFTER profile_url,
  ADD COLUMN IF NOT EXISTS fav_team_id       INT          NULL                AFTER country,
  ADD COLUMN IF NOT EXISTS faceit_level      TINYINT      NOT NULL DEFAULT 0  AFTER fav_team_id,
  ADD COLUMN IF NOT EXISTS faceit_updated_at TIMESTAMP    NULL                AFTER faceit_level;

CREATE TABLE IF NOT EXISTS follows (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_id INT UNSIGNED NOT NULL,
    followed_id INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY  uq_follow    (follower_id, followed_id),
    INDEX       idx_follower (follower_id),
    INDEX       idx_followed (followed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
