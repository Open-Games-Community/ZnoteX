<?php require_once 'engine/init.php'; theme_open();

// Staff list, grouped by position. Cached; the view only renders $srtGrp.
$cache = new Cache('engine/cache/support');
if ($cache->hasExpired()) {
	// Fetch all staffs in-game.
	if ($config['ServerEngine'] == 'TFS_03') {
		$staffs = support_list03();
	} else $staffs = support_list();
	// Fetch group ids and names from config.php
	$groups = $config['ingame_positions'];
	// Loops through groups, separating each group element into an ID variable and name variable
	foreach ($groups as $group_id => $group_name) {
		// Loops through list of staffs
		if (!empty($staffs))
		foreach ($staffs as $staff) {
			if ($staff['group_id'] == $group_id) $srtGrp[$group_name][] = $staff;
		}
	}
	if (!empty($srtGrp)) {
		$cache->setContent($srtGrp);
		$cache->save();
	}
} else {
	$srtGrp = $cache->load();
}

view('support');

theme_close();
