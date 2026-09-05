-- ---------------------------------------------------------------------------
-- ZnoteX 2.0.0 - admin action log
--
-- Only needed for databases created BEFORE this feature. A fresh import of
-- SQL/znote_schema.sql already contains the table.
--
-- Records mutating actions taken from the admin panel (bans, skill edits,
-- points, settings changes, plugin lifecycle, etc). Written by acp_log() in
-- engine/function/adminlog.php, read from Admin Panel > Admin Log.
--
-- Run once:
--   mysql -u <user> -p <database> < SQL/migrations/2.0.0_admin_log.sql
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `znote_admin_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL DEFAULT '0',
  `admin_name` varchar(50) NOT NULL DEFAULT '',
  `action` varchar(64) NOT NULL,
  `target` varchar(191) NOT NULL DEFAULT '',
  `details` text NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_created` (`admin_id`, `created`),
  KEY `action_created` (`action`, `created`),
  KEY `created` (`created`)
) ENGINE=InnoDB;
