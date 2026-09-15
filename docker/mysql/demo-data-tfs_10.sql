-- Demo game data for the ZnoteX Docker environment.
-- Login: account "demo", password "demo123". The character "Admin" is
-- granted admin panel access by docker/entrypoint.sh (page_admin_access).

INSERT INTO `accounts` (`id`, `name`, `password`, `type`, `premdays`, `email`, `creation`) VALUES
	(1, 'demo', SHA1('demo123'), 1, 90, 'demo@znotex.local', UNIX_TIMESTAMP());

INSERT INTO `players` (`id`, `name`, `group_id`, `account_id`, `level`, `vocation`, `health`, `healthmax`, `mana`, `manamax`, `experience`, `looktype`, `lookhead`, `lookbody`, `looklegs`, `lookfeet`, `town_id`, `conditions`, `sex`, `lastlogin`, `onlinetime`) VALUES
	(1, 'Admin',      3, 1, 200, 4, 4585, 4585, 3155, 3155, 698737485, 268, 78, 68, 58, 76, 1, '', 1, UNIX_TIMESTAMP(), 3600),
	(2, 'Rookgaard',  1, 1,  15, 1,  185,  185,   35,   35,      4200, 128, 78, 68, 58, 76, 1, '', 1, UNIX_TIMESTAMP(), 1200),
	(3, 'Thais Mage', 2, 1,  80, 2, 1240, 1240,  920,  920,   1520000, 138, 78, 68, 58, 76, 1, '', 0, UNIX_TIMESTAMP(),  900);

-- The oncreate_guilds trigger auto-creates 3 guild_ranks rows for this guild,
-- so they are not inserted here.
INSERT INTO `guilds` (`id`, `name`, `ownerid`, `creationdata`, `motd`) VALUES
	(1, 'ZnoteX Guardians', 1, UNIX_TIMESTAMP(), 'Welcome to the demo guild.');

INSERT INTO `guild_membership` (`player_id`, `guild_id`, `rank_id`, `nick`) VALUES
	(1, 1, (SELECT `id` FROM `guild_ranks` WHERE `guild_id` = 1 AND `level` = 3 LIMIT 1), '');
