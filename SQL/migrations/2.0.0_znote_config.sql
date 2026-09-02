-- ---------------------------------------------------------------------------
-- ZnoteX 2.0.0 - settings table (needed by the layout system)
--
-- Only needed for databases created BEFORE 2.0.0. A fresh import of
-- SQL/znote_schema.sql already contains this table.
--
-- Key/value settings written from the admin panel. The active layout is the
-- first user of it; anything else the panel should be able to change without
-- editing config.php belongs here too.
--
-- Run once:
--   mysql -u <user> -p <database> < SQL/migrations/2.0.0_znote_config.sql
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `znote_config` (
  `key` varchar(64) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;

-- Existing sites keep the look they have: 'default' is the current Snavy
-- layout, moved from layout/ to layouts/default/.
INSERT INTO `znote_config` (`key`, `value`) VALUES
('layout', 'default')
ON DUPLICATE KEY UPDATE `key` = `key`;
