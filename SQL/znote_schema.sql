-- Start of Znote AAC database schema

SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;

SET @znote_version = '2.0.0';

CREATE TABLE IF NOT EXISTS `znote` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(30) NOT NULL COMMENT 'Znote AAC version',
  `installed` int NOT NULL,
  `cached` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `ip` bigint UNSIGNED NOT NULL,
  `created` int NOT NULL,
  `points` int DEFAULT 0,
  `cooldown` int DEFAULT 0,
  `active` tinyint NOT NULL DEFAULT '0',
  `active_email` tinyint NOT NULL DEFAULT '0',
  `activekey` int NOT NULL DEFAULT '0',
  `flag` varchar(20) NOT NULL,
  `secret` char(16) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(30) NOT NULL,
  `text` text NOT NULL,
  `date` int NOT NULL,
  `pid` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(30) NOT NULL,
  `desc` text NOT NULL,
  `date` int NOT NULL,
  `status` int NOT NULL,
  `image` varchar(255) NOT NULL,
  `delhash` varchar(30) NOT NULL,
  `account_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_paypal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `txn_id` varchar(30) NOT NULL,
  `email` varchar(255) NOT NULL,
  `accid` int NOT NULL,
  `price` int NOT NULL,
  `points` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_paygol` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `price` int NOT NULL,
  `points` int NOT NULL,
  `message_id` varchar(255) NOT NULL,
  `service_id` varchar(255) NOT NULL,
  `shortcode` varchar(255) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `message` varchar(255) NOT NULL,
  `sender` varchar(255) NOT NULL,
  `operator` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `currency` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_pagseguro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction` varchar(36) NOT NULL,
  `account` int NOT NULL,
  `price` decimal(11,2) NOT NULL,
  `points` int NOT NULL,
  `payment_status` tinyint NOT NULL,
  `completed` tinyint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction` (`transaction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_pagseguro_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notification_code` varchar(40) NOT NULL,
  `details` text NOT NULL,
  `receive_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_payment_transactions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL,
  `reference` varchar(128) NOT NULL,
  `provider_reference` varchar(128) DEFAULT NULL,
  `account_id` int NOT NULL,
  `price` decimal(11,2) NOT NULL,
  `currency` varchar(8) NOT NULL,
  `points` int NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `credited` tinyint NOT NULL DEFAULT '0',
  `test_mode` tinyint NOT NULL DEFAULT '0',
  `created_at` int NOT NULL,
  `updated_at` int NOT NULL,
  `credited_at` int DEFAULT NULL,
  `payload` longtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_reference_internal` (`provider`, `reference`),
  KEY `provider_reference_external` (`provider`, `provider_reference`),
  KEY `account_status` (`account_id`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_payment_events` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL,
  `event_id` varchar(128) NOT NULL,
  `provider_reference` varchar(128) DEFAULT NULL,
  `payment_reference` varchar(128) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'received',
  `payload` longtext,
  `received_at` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_event` (`provider`, `event_id`),
  KEY `payment_reference` (`provider`, `payment_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_players` (
  `id` int NOT NULL AUTO_INCREMENT,
  `player_id` int NOT NULL,
  `created` int NOT NULL,
  `hide_char` tinyint NOT NULL,
  `comment` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_player_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `posx` int NOT NULL,
  `posy` int NOT NULL,
  `posz` int NOT NULL,
  `report_description` varchar(255) NOT NULL,
  `date` int NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_changelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `text` varchar(255) NOT NULL,
  `time` int NOT NULL,
  `report_id` int NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_shop` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` int NOT NULL,
  `itemid` int DEFAULT NULL,
  `count` int NOT NULL DEFAULT '1',
  `description` varchar(255) NOT NULL,
  `points` int NOT NULL DEFAULT '10',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_shop_offers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` int NOT NULL,
  `itemid` int DEFAULT NULL,
  `count` int NOT NULL DEFAULT '1',
  `description` varchar(255) NOT NULL,
  `points` int NOT NULL DEFAULT '10',
  `active` tinyint NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` int DEFAULT NULL,
  `updated_at` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `active_sort` (`active`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_shop_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `player_id` int NOT NULL,
  `type` int NOT NULL,
  `itemid` int NOT NULL,
  `count` int NOT NULL,
  `points` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_shop_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `type` int NOT NULL,
  `itemid` int NOT NULL,
  `count` int NOT NULL,
  `time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Audit trail of mutating actions taken from the admin panel: bans, skill
-- edits, points, settings changes, plugin lifecycle, etc. Written by
-- acp_log() in engine/function/adminlog.php, read by Admin Panel > Admin Log.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Key/value settings written from the admin panel (active layout, etc).
CREATE TABLE IF NOT EXISTS `znote_config` (
  `key` varchar(64) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Navigation entries, managed from Admin Panel > Menus.
-- A theme declares the locations it renders (see layouts/README.md); entries
-- are grouped by that location and ordered by sort_order.
CREATE TABLE IF NOT EXISTS `znote_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location` varchar(32) NOT NULL COMMENT 'Theme-declared slot: main, sidebar, footer...',
  `parent_id` int NOT NULL DEFAULT '0' COMMENT '0 = top level, else the id of the parent entry',
  `label` varchar(64) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon` varchar(48) NOT NULL DEFAULT '' COMMENT 'Optional Font Awesome class',
  `target` varchar(10) NOT NULL DEFAULT '',
  `visibility` varchar(10) NOT NULL DEFAULT 'all' COMMENT 'all, guest, user or admin',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `loc_sort` (`location`, `active`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Database-backed pages, used by importers and editable site content.
CREATE TABLE IF NOT EXISTS `znote_pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `title` varchar(100) NOT NULL,
  `body` mediumtext NOT NULL,
  `created` int NOT NULL DEFAULT '0',
  `updated` int NOT NULL DEFAULT '0',
  `player_id` int NOT NULL DEFAULT '0',
  `access` tinyint NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Keeps legacy imports idempotent and stores source ids for follow-up imports.
CREATE TABLE IF NOT EXISTS `znote_convert_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL,
  `source_table` varchar(64) NOT NULL,
  `source_id` varchar(64) NOT NULL,
  `target_table` varchar(64) NOT NULL,
  `target_id` int NOT NULL,
  `created` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_row` (`source`, `source_table`, `source_id`, `target_table`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Raw archive of legacy AAC tables, including custom columns ZnoteX does not
-- understand yet. This keeps migrations lossless.
CREATE TABLE IF NOT EXISTS `znote_legacy_tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `schema_sql` longtext NOT NULL,
  `row_count` int NOT NULL DEFAULT '0',
  `captured` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_table` (`source`, `table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_legacy_rows` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `source_pk` varchar(128) NOT NULL DEFAULT '',
  `row_json` longtext NOT NULL,
  `captured` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `source_table` (`source`, `table_name`),
  KEY `source_pk` (`source`, `table_name`, `source_pk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_visitors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` bigint NOT NULL,
  `value` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_visitors_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` bigint NOT NULL,
  `time` int NOT NULL,
  `type` tinyint NOT NULL,
  `account_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Forum 1/3 (boards)
CREATE TABLE IF NOT EXISTS `znote_forum` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `access` tinyint NOT NULL,
  `closed` tinyint NOT NULL,
  `hidden` tinyint NOT NULL,
  `guild_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Forum 2/3 (threads)
CREATE TABLE IF NOT EXISTS `znote_forum_threads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `forum_id` int NOT NULL,
  `player_id` int NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `text` text NOT NULL,
  `created` int NOT NULL,
  `updated` int NOT NULL,
  `sticky` tinyint NOT NULL,
  `hidden` tinyint NOT NULL,
  `closed` tinyint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Forum 3/3 (posts)
CREATE TABLE IF NOT EXISTS `znote_forum_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `thread_id` int NOT NULL,
  `player_id` int NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `text` text NOT NULL,
  `created` int NOT NULL,
  `updated` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pending characters for deletion
CREATE TABLE IF NOT EXISTS `znote_deleted_characters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_account_id` int NOT NULL,
  `character_name` varchar(255) NOT NULL,
  `time` datetime NOT NULL,
  `done` tinyint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_guild_wars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `limit` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Helpdesk system
CREATE TABLE IF NOT EXISTS `znote_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner` int NOT NULL,
  `username` varchar(32) NOT NULL,
  `subject` text NOT NULL,
  `message` text NOT NULL,
  `ip` bigint NOT NULL,
  `creation` int NOT NULL,
  `status` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_tickets_replies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tid` int NOT NULL,
  `username` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `created` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `znote_global_storage` (
  `key` varchar(32) NOT NULL,
  `value` TEXT NOT NULL,
  UNIQUE (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Character auction system
CREATE TABLE IF NOT EXISTS `znote_auction_player` (
  `id` int NOT NULL AUTO_INCREMENT,
  `player_id` int NOT NULL,
  `original_account_id` int NOT NULL,
  `bidder_account_id` int NOT NULL,
  `time_begin` int NOT NULL,
  `time_end` int NOT NULL,
  `price` int NOT NULL,
  `bid` int NOT NULL,
  `deposit` int NOT NULL,
  `sold` tinyint NOT NULL,
  `claimed` tinyint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Populate basic info
INSERT INTO `znote` (`version`, `installed`) VALUES
(@znote_version, UNIX_TIMESTAMP(CURDATE()));

-- Default settings
INSERT INTO `znote_config` (`key`, `value`) VALUES
('layout', 'default');

INSERT INTO `znote_menu` (`location`, `parent_id`, `label`, `url`, `icon`, `visibility`, `sort_order`)
SELECT `seed`.`location`, `seed`.`parent_id`, `seed`.`label`, `seed`.`url`, `seed`.`icon`, `seed`.`visibility`, `seed`.`sort_order`
FROM (
  SELECT 'main' AS `location`, 0 AS `parent_id`, 'Home' AS `label`, 'index.php' AS `url`, 'fa-home' AS `icon`, 'all' AS `visibility`, 10 AS `sort_order`
  UNION ALL SELECT 'main', 0, 'Changelog', 'changelog.php', '', 'all', 20
  UNION ALL SELECT 'main', 0, 'Account', 'myaccount.php', 'fa-user-circle', 'user', 30
  UNION ALL SELECT 'main', 0, 'Login', 'login.php', 'fa-user-circle', 'guest', 30
  UNION ALL SELECT 'main', 0, 'Register', 'register.php', 'fa-key', 'guest', 40
  UNION ALL SELECT 'main', 0, 'Downloads', 'downloads.php', '', 'all', 50
  UNION ALL SELECT 'main', 0, 'Community', 'onlinelist.php', 'fa-users', 'all', 60
  UNION ALL SELECT 'main', 0, 'Highscores', 'highscores.php', '', 'all', 70
  UNION ALL SELECT 'main', 0, 'Guilds', 'guilds.php', '', 'all', 80
  UNION ALL SELECT 'main', 0, 'Forum', 'forum.php', '', 'all', 90
  UNION ALL SELECT 'main', 0, 'Houses', 'houses.php', '', 'all', 100
  UNION ALL SELECT 'main', 0, 'Latest deaths', 'deaths.php', '', 'all', 110
  UNION ALL SELECT 'main', 0, 'Kill statistics', 'killers.php', '', 'all', 120
  UNION ALL SELECT 'main', 0, 'Bans', 'bans.php', '', 'all', 125
  UNION ALL SELECT 'main', 0, 'Creatures', 'creatures.php', '', 'all', 135
  UNION ALL SELECT 'main', 0, 'Library', 'serverinfo.php', 'fa-book', 'all', 130
  UNION ALL SELECT 'main', 0, 'Spells', 'spells.php', '', 'all', 140
  UNION ALL SELECT 'main', 0, 'Support', 'support.php', 'fa-info-circle', 'all', 150
  UNION ALL SELECT 'main', 0, 'Helpdesk', 'helpdesk.php', '', 'all', 160
  UNION ALL SELECT 'main', 0, 'Shop', 'shop.php', 'fa-shopping-cart', 'all', 170
  UNION ALL SELECT 'main', 0, 'Buy points', 'buypoints.php', '', 'all', 180
  UNION ALL SELECT 'main', 0, 'Admin Panel', 'admin/index.php', 'fa-sliders', 'admin', 190
) `seed`
LEFT JOIN `znote_menu` `existing`
  ON `existing`.`location` = `seed`.`location`
 AND `existing`.`label` = `seed`.`label`
 AND `existing`.`url` = `seed`.`url`
WHERE `existing`.`id` IS NULL;


-- Nest the sub-entries under their section. Done as a second pass because the
-- parent ids are only known once the rows above exist.
UPDATE `znote_menu` `c`
  JOIN `znote_menu` `p` ON `p`.`location` = 'main' AND `p`.`parent_id` = 0 AND `p`.`label` = 'Home'
  SET `c`.`parent_id` = `p`.`id`
  WHERE `c`.`location` = 'main' AND `c`.`label` IN ('Changelog');

UPDATE `znote_menu` `c`
  JOIN `znote_menu` `p` ON `p`.`location` = 'main' AND `p`.`parent_id` = 0 AND `p`.`label` = 'Account'
  SET `c`.`parent_id` = `p`.`id`
  WHERE `c`.`location` = 'main' AND `c`.`label` IN ('Downloads');

UPDATE `znote_menu` `c`
  JOIN `znote_menu` `p` ON `p`.`location` = 'main' AND `p`.`parent_id` = 0 AND `p`.`label` = 'Community'
  SET `c`.`parent_id` = `p`.`id`
  WHERE `c`.`location` = 'main' AND `c`.`label` IN ('Highscores','Guilds','Forum','Houses','Latest deaths','Kill statistics','Bans');

UPDATE `znote_menu` `c`
  JOIN `znote_menu` `p` ON `p`.`location` = 'main' AND `p`.`parent_id` = 0 AND `p`.`label` = 'Library'
  SET `c`.`parent_id` = `p`.`id`
  WHERE `c`.`location` = 'main' AND `c`.`label` IN ('Spells','Creatures');

UPDATE `znote_menu` `c`
  JOIN `znote_menu` `p` ON `p`.`location` = 'main' AND `p`.`parent_id` = 0 AND `p`.`label` = 'Support'
  SET `c`.`parent_id` = `p`.`id`
  WHERE `c`.`location` = 'main' AND `c`.`label` IN ('Helpdesk');

UPDATE `znote_menu` `c`
  JOIN `znote_menu` `p` ON `p`.`location` = 'main' AND `p`.`parent_id` = 0 AND `p`.`label` = 'Shop'
  SET `c`.`parent_id` = `p`.`id`
  WHERE `c`.`location` = 'main' AND `c`.`label` IN ('Buy points');

-- Add default forum boards
INSERT INTO `znote_forum` (`name`, `access`, `closed`, `hidden`, `guild_id`)
SELECT 'Staff Board', '4', '0', '0', '0' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `znote_forum` WHERE `name` = 'Staff Board' AND `guild_id` = '0');
INSERT INTO `znote_forum` (`name`, `access`, `closed`, `hidden`, `guild_id`)
SELECT 'Tutors Board', '2', '0', '0', '0' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `znote_forum` WHERE `name` = 'Tutors Board' AND `guild_id` = '0');
INSERT INTO `znote_forum` (`name`, `access`, `closed`, `hidden`, `guild_id`)
SELECT 'Discussion', '1', '0', '0', '0' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `znote_forum` WHERE `name` = 'Discussion' AND `guild_id` = '0');
INSERT INTO `znote_forum` (`name`, `access`, `closed`, `hidden`, `guild_id`)
SELECT 'Feedback', '1', '0', '1', '0' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `znote_forum` WHERE `name` = 'Feedback' AND `guild_id` = '0');

-- Convert existing accounts in database to be Znote AAC compatible
INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `flag`)
SELECT
  `a`.`id` AS `account_id`,
  0 AS `ip`,
  UNIX_TIMESTAMP(CURDATE()) AS `created`,
  '' AS `flag`
FROM `accounts` AS `a`
LEFT JOIN `znote_accounts` AS `z`
  ON `a`.`id` = `z`.`account_id`
WHERE `z`.`created` IS NULL;

-- Convert existing players in database to be Znote AAC compatible
INSERT INTO `znote_players` (`player_id`, `created`, `hide_char`, `comment`)
SELECT
  `p`.`id` AS `player_id`,
  UNIX_TIMESTAMP(CURDATE()) AS `created`,
  0 AS `hide_char`,
  '' AS `comment`
FROM `players` AS `p`
LEFT JOIN `znote_players` AS `z`
  ON `p`.`id` = `z`.`player_id`
WHERE `z`.`created` IS NULL;

-- Delete duplicate account records
DELETE `d` FROM `znote_accounts` AS `d`
INNER JOIN (
  SELECT `i`.`account_id`,
  MAX(`i`.`id`) AS `retain`
  FROM `znote_accounts` AS `i`
  GROUP BY `i`.`account_id`
  HAVING COUNT(`i`.`id`) > 1
) AS `x`
  ON `d`.`account_id` = `x`.`account_id`
  AND `d`.`id` != `x`.`retain`;

-- Delete duplicate player records
DELETE `d` FROM `znote_players` AS `d`
INNER JOIN (
  SELECT `i`.`player_id`,
  MAX(`i`.`id`) AS `retain`
  FROM `znote_players` AS `i`
  GROUP BY `i`.`player_id`
  HAVING COUNT(`i`.`id`) > 1
) AS `x`
  ON `d`.`player_id` = `x`.`player_id`
  AND `d`.`id` != `x`.`retain`;

-- End of Znote AAC database schema
