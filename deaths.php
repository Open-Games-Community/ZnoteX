<?php require_once 'engine/init.php'; theme_open();
$cache = new Cache('engine/cache/deaths');
if ($cache->hasExpired()) {

	if (in_array(znote_server_adapter()->normalizedEngine(), array('TFS_02', 'TFS_10'), true)) {
		$deaths = fetchLatestDeaths();
	} else {
		$deaths = fetchLatestDeaths_03(30);
	}
	$cache->setContent($deaths);
	$cache->save();
} else {
	$deaths = $cache->load();
}

view('deaths');

theme_close();
