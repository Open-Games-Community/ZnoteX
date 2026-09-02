<?php require_once 'engine/init.php'; theme_open(); 

$history = array(
	"enabled" => true,
	"days" => 14,
	"cache" => 300
);

// Returns a list of players online
$array = false;
$loadFlags = ($config['country_flags']['enabled'] && $config['country_flags']['onlinelist']) ? true : false;
$loadOutfits = ($config['show_outfits']['onlinelist']) ? true : false;
if ($config['client'] < 780) {
	$outfitQuery = ($loadOutfits) ? ", `p`.`lookbody` AS `body`, `p`.`lookfeet` AS `feet`, `p`.`lookhead` AS `head`, `p`.`looklegs` AS `legs`, `p`.`looktype` AS `type`" : "";
} else {
	$outfitQuery = ($loadOutfits) ? ", `p`.`lookbody` AS `body`, `p`.`lookfeet` AS `feet`, `p`.`lookhead` AS `head`, `p`.`looklegs` AS `legs`, `p`.`looktype` AS `type`, `p`.`lookaddons` AS `addons`" : "";
}

// Small 30 seconds players_online cache.
$cache = new Cache('engine/cache/onlinelist');
$cache->setExpiration(30);
if ($cache->hasExpired()) {
	// Load online list data from SQL
	$array = ($loadFlags === true) ? mysql_select_multi("SELECT `p`.`name` AS `name`, `p`.`level` AS `level`, `p`.`vocation` AS `vocation`, `g`.`name` AS `gname`, `za`.`flag` AS `flag` $outfitQuery FROM `players_online` AS `o` INNER JOIN `players` AS `p` ON `o`.`player_id` = `p`.`id` INNER JOIN `znote_accounts` AS `za` ON `p`.`account_id` = `za`.`account_id` LEFT JOIN `guild_membership` AS `gm` ON `o`.`player_id` = `gm`.`player_id` LEFT JOIN `guilds` AS `g` ON `gm`.`guild_id` = `g`.`id`;") : mysql_select_multi("SELECT `p`.`name` AS `name`, `p`.`level` AS `level`, `p`.`vocation` AS `vocation`, `g`.`name` AS `gname` $outfitQuery FROM `players_online` AS `o` INNER JOIN `players` AS `p` ON `o`.`player_id` = `p`.`id` LEFT JOIN `guild_membership` AS `gm` ON `o`.`player_id` = `gm`.`player_id` LEFT JOIN `guilds` AS `g` ON `gm`.`guild_id` = `g`.`id`;");
	// End loading data from SQL
	$cache->setContent($array);
	$cache->save();
} else {
	$array = $cache->load();
}
// End cache

/**
 * Players-online record.
 *
 * Updated here because this page already knows the current count - checking it
 * anywhere else would mean an extra query on every page load. Stored in
 * znote_config, see znote_record_update().
 */
$onlineNow    = is_array($array) ? count($array) : 0;
$onlineBroken = znote_record_update($onlineNow);
$onlineRecord = znote_record_get();

// 5 minute logout history cache
if ($history["enabled"]) {
	$time = time();
	$cache = new Cache('engine/cache/onlinelist_rec');
	$cache->setExpiration($history['cache']);
	if ($cache->hasExpired()) {
		// Load online list data from SQL
		$recents = ($loadFlags === true) ? mysql_select_multi("
			SELECT 
				`p`.`name` AS `name`, 
				`p`.`level` AS `level`, 
				`p`.`vocation` AS `vocation`, 
				`p`.`lastlogout`,
				`g`.`name` AS `gname`, 
				`za`.`flag` AS `flag` 
				$outfitQuery 
			FROM `players` AS `p` 
			INNER JOIN `znote_accounts` AS `za` 
				ON `p`.`account_id` = `za`.`account_id` 
			LEFT JOIN `guild_membership` AS `gm` 
				ON `p`.`id` = `gm`.`player_id` 
			LEFT JOIN `guilds` AS `g` 
				ON `gm`.`guild_id` = `g`.`id`
			WHERE `p`.`lastlogout` >= $time - ({$history['days']} * 24 * 60 * 60)
			ORDER BY `p`.`lastlogout` DESC;
		") : mysql_select_multi("
			SELECT 
				`p`.`name` AS `name`, 
				`p`.`level` AS `level`, 
				`p`.`vocation` AS `vocation`, 
				`p`.`lastlogout`,
				`g`.`name` AS `gname` 
				$outfitQuery 
			FROM `players` AS `p` 
			LEFT JOIN `guild_membership` AS `gm` 
				ON `p`.`id` = `gm`.`player_id` 
			LEFT JOIN `guilds` AS `g` 
				ON `gm`.`guild_id` = `g`.`id`
			WHERE `p`.`lastlogout` >= $time - ({$history['days']} * 24 * 60 * 60)
			ORDER BY `p`.`lastlogout` DESC;
		");
		// End loading data from SQL
		$cache->setContent($recents);
		$cache->save();
	} else {
		$recents = $cache->load();
	}
}

view('onlinelist');

theme_close();
