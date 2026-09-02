<?php
require_once 'engine/init.php';
theme_open();

/**
 * Kill statistics.
 *
 * TFS 0.3 exposes a different set of tables from the 1.x line, so the two
 * engines produce different data. Which one applies is decided here and the
 * view only has to read $killersMode - it never touches the database.
 */

$engine = $config['ServerEngine'];

if (in_array($engine, array('TFS_02', 'TFS_10', 'OTHIRE'), true)) {
	$killersMode = 'modern';
} elseif ($engine === 'TFS_03') {
	$killersMode = 'legacy';
} else {
	$killersMode = 'unsupported';
}

$killers = false;
$victims = false;
$latests = false;
$deaths  = false;

if ($killersMode === 'modern') {

	$cache = new Cache('engine/cache/killers');
	if ($cache->hasExpired()) {
		$killers = fetchMurders();
		$cache->setContent($killers);
		$cache->save();
	} else {
		$killers = $cache->load();
	}

	$cache = new Cache('engine/cache/victims');
	if ($cache->hasExpired()) {
		$victims = fetchLoosers();
		$cache->setContent($victims);
		$cache->save();
	} else {
		$victims = $cache->load();
	}

	$cache = new Cache('engine/cache/lastkillers');
	if ($cache->hasExpired()) {
		$latests = mysql_select_multi("SELECT `p`.`name` AS `victim`, `d`.`killed_by` as `killed_by`, `d`.`time` as `time` FROM `player_deaths` as `d` INNER JOIN `players` as `p` ON d.player_id = p.id WHERE d.`is_player`='1' ORDER BY `time` DESC LIMIT 20;");
		if ($latests !== false) {
			$cache->setContent($latests);
			$cache->save();
		}
	} else {
		$latests = $cache->load();
	}

} elseif ($killersMode === 'legacy') {

	$cache = new Cache('engine/cache/killers');
	if ($cache->hasExpired()) {
		$deaths = fetchLatestDeaths_03(30, true);
		$cache->setContent($deaths);
		$cache->save();
	} else {
		$deaths = $cache->load();
	}
}

view('killers');

theme_close();
