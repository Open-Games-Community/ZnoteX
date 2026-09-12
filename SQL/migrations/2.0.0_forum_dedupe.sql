-- Merge duplicate forum boards.
--
-- Older schema seeds (and the OT -> ZnoteX converter) could INSERT the default
-- boards more than once, leaving two "Discussion", two "Staff Board", etc. This
-- keeps the lowest id for each (name, guild_id), moves every thread onto it and
-- deletes the emptied duplicates. Safe to run more than once.

UPDATE `znote_forum_threads` `t`
JOIN `znote_forum` `dup` ON `dup`.`id` = `t`.`forum_id`
JOIN (
	SELECT `name`, `guild_id`, MIN(`id`) AS `keep_id`
	FROM `znote_forum`
	GROUP BY `name`, `guild_id`
) `k` ON `k`.`name` = `dup`.`name` AND `k`.`guild_id` = `dup`.`guild_id`
SET `t`.`forum_id` = `k`.`keep_id`
WHERE `t`.`forum_id` <> `k`.`keep_id`;

DELETE `f` FROM `znote_forum` `f`
JOIN (
	SELECT `name`, `guild_id`, MIN(`id`) AS `keep_id`
	FROM `znote_forum`
	GROUP BY `name`, `guild_id`
) `k` ON `k`.`name` = `f`.`name` AND `k`.`guild_id` = `f`.`guild_id`
WHERE `f`.`id` <> `k`.`keep_id`;
