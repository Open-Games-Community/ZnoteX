-- Start of Znote AAC database schema

SET @znote_version = '2.0.0';

CREATE TABLE IF NOT EXISTS `znote` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(30) NOT NULL COMMENT 'Znote AAC version',
  `installed` int NOT NULL,
  `cached` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(30) NOT NULL,
  `text` text NOT NULL,
  `date` int NOT NULL,
  `pid` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(30) NOT NULL,
  `desc` text NOT NULL,
  `date` int NOT NULL,
  `status` int NOT NULL,
  `image` varchar(50) NOT NULL,
  `delhash` varchar(30) NOT NULL,
  `account_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_paypal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `txn_id` varchar(30) NOT NULL,
  `email` varchar(255) NOT NULL,
  `accid` int NOT NULL,
  `price` int NOT NULL,
  `points` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_pagseguro_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notification_code` varchar(40) NOT NULL,
  `details` text NOT NULL,
  `receive_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_players` (
  `id` int NOT NULL AUTO_INCREMENT,
  `player_id` int NOT NULL,
  `created` int NOT NULL,
  `hide_char` tinyint NOT NULL,
  `comment` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_changelog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `text` varchar(255) NOT NULL,
  `time` int NOT NULL,
  `report_id` int NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_shop` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` int NOT NULL,
  `itemid` int DEFAULT NULL,
  `count` int NOT NULL DEFAULT '1',
  `description` varchar(255) NOT NULL,
  `points` int NOT NULL DEFAULT '10',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_shop_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `type` int NOT NULL,
  `itemid` int NOT NULL,
  `count` int NOT NULL,
  `time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- Key/value settings written from the admin panel (active layout, etc).
CREATE TABLE IF NOT EXISTS `znote_config` (
  `key` varchar(64) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_visitors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` bigint NOT NULL,
  `value` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_visitors_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` bigint NOT NULL,
  `time` int NOT NULL,
  `type` tinyint NOT NULL,
  `account_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- Forum 1/3 (boards)
CREATE TABLE IF NOT EXISTS `znote_forum` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `access` tinyint NOT NULL,
  `closed` tinyint NOT NULL,
  `hidden` tinyint NOT NULL,
  `guild_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

-- Pending characters for deletion
CREATE TABLE IF NOT EXISTS `znote_deleted_characters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_account_id` int NOT NULL,
  `character_name` varchar(255) NOT NULL,
  `time` datetime NOT NULL,
  `done` tinyint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_guild_wars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `limit` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- Helpdesk system
CREATE TABLE IF NOT EXISTS `znote_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner` int NOT NULL,
  `username` varchar(32) CHARACTER SET latin1 NOT NULL,
  `subject` text CHARACTER SET latin1 NOT NULL,
  `message` text CHARACTER SET latin1 NOT NULL,
  `ip` bigint NOT NULL,
  `creation` int NOT NULL,
  `status` varchar(20) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_tickets_replies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tid` int NOT NULL,
  `username` varchar(32) CHARACTER SET latin1 NOT NULL,
  `message` text CHARACTER SET latin1 NOT NULL,
  `created` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `znote_global_storage` (
  `key` varchar(32) NOT NULL,
  `value` TEXT NOT NULL,
  UNIQUE (`key`)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

-- Populate basic info
INSERT INTO `znote` (`version`, `installed`) VALUES
(@znote_version, UNIX_TIMESTAMP(CURDATE()));

-- Default settings
INSERT INTO `znote_config` (`key`, `value`) VALUES
('layout', 'default');

-- Default navigation, mirroring the stock layout's menu.
INSERT INTO `znote_menu` (`location`, `parent_id`, `label`, `url`, `icon`, `visibility`, `sort_order`) VALUES
('main', 0, 'Home',        'index.php',       'fa-home',            'all',   10),
('main', 0, 'Changelog',   'changelog.php',   '',                   'all',   20),
('main', 0, 'Account',     'myaccount.php',   'fa-user-circle',     'user',  30),
('main', 0, 'Login',       'login.php',       'fa-user-circle',     'guest', 30),
('main', 0, 'Register',    'register.php',    'fa-key',             'guest', 40),
('main', 0, 'Downloads',   'downloads.php',   '',                   'all',   50),
('main', 0, 'Community',   'onlinelist.php',  'fa-users',           'all',   60),
('main', 0, 'Highscores',  'highscores.php',  '',                   'all',   70),
('main', 0, 'Guilds',      'guilds.php',      '',                   'all',   80),
('main', 0, 'Forum',       'forum.php',       '',                   'all',   90),
('main', 0, 'Houses',      'houses.php',      '',                   'all',  100),
('main', 0, 'Latest deaths','deaths.php',     '',                   'all',  110),
('main', 0, 'Kill statistics','killers.php',  '',                   'all',  120),
('main', 0, 'Bans',        'bans.php',        '',                   'all',  125),
('main', 0, 'Creatures',   'creatures.php',   '',                   'all',  135),
('main', 0, 'Library',     'serverinfo.php',  'fa-book',            'all',  130),
('main', 0, 'Spells',      'spells.php',      '',                   'all',  140),
('main', 0, 'Support',     'support.php',     'fa-info-circle',     'all',  150),
('main', 0, 'Helpdesk',    'helpdesk.php',    '',                   'all',  160),
('main', 0, 'Shop',        'shop.php',        'fa-shopping-cart',   'all',  170),
('main', 0, 'Buy points',  'buypoints.php',   '',                   'all',  180),
('main', 0, 'Admin Panel', 'admin/index.php', 'fa-sliders',         'admin',190);


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
INSERT INTO `znote_forum` (`name`, `access`, `closed`, `hidden`, `guild_id`) VALUES
('Staff Board', '4', '0', '0', '0'),
('Tutors Board', '2', '0', '0', '0'),
('Discussion', '1', '0', '0', '0'),
('Feedback', '1', '0', '1', '0');

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
