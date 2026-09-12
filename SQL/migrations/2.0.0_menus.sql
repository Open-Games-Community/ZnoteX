-- ---------------------------------------------------------------------------
-- ZnoteX 2.0.0 - navigation menus
--
-- Only needed for databases created BEFORE this feature. A fresh import of
-- SQL/znote_schema.sql already contains the table and its default entries.
--
-- Menu links used to be hardcoded in every theme, which meant editing PHP to
-- add a link and duplicating the whole menu in each theme. They live here now
-- and are edited from Admin Panel > Menus.
--
-- Run once:
--   mysql -u <user> -p <database> < SQL/migrations/2.0.0_menus.sql
-- ---------------------------------------------------------------------------

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
 AND `existing`.`parent_id` = 0
 AND `existing`.`label` = `seed`.`label`
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

