<?php require_once 'engine/init.php'; theme_open();

if ($config['log_ip']) {
	znote_visitor_insert_detailed_data(3);
}

// Fetch highscore type
$type = (isset($_GET['type'])) ? (int)getValue($_GET['type'] ?? null) : 7;
if ($type > 9) $type = 7;

// Fetch highscore vocation
$configVocations = $config['vocations'];
//$debug['configVocations'] = $configVocations;

$vocationIds = array_keys($configVocations);

$vocation = 'all';
if (isset($_GET['vocation']) && is_numeric($_GET['vocation'])) {
	$vocation = (int)$_GET['vocation'];
	if (!in_array($vocation, $vocationIds)) {
		$vocation = "all";
	}
}

// Fetch highscore page
$page = getValue($_GET['page'] ?? null);
if (!$page || $page == 0) $page = 1;
else $page = (int)$page;

$highscore = $config['highscore'];
$loadFlags = ($config['country_flags']['enabled'] && $config['country_flags']['highscores']) ? true : false;
$loadOutfits = ($config['show_outfits']['highscores']) ? true : false;

$rows = $highscore['rows'];
$rowsPerPage = $highscore['rowsPerPage'];

function skillName($type) {
	$types = array(
		1 => "Club",
		2 => "Sword",
		3 => "Axe",
		4 => "Distance",
		5 => "Shield",
		6 => "Fish",
		7 => "Experience", // Hardcoded
		8 => "Magic Level", // Hardcoded
		9 => "Fist", // Since 0 returns false I will make 9 = 0. :)
	);
	return $types[(int)$type];
}

function pageCheck($index, $page, $rowPerPage) {
	return ($index < ($page * $rowPerPage) && $index >= ($page * $rowPerPage) - $rowPerPage) ? true : false;
}

$cache = new Cache('engine/cache/highscores');
if ($cache->hasExpired()) {
	$vocGroups = fetchAllScores($rows, $config['ServerEngine'], $highscore['ignoreGroupId'], $configVocations, $vocation, $loadFlags, $loadOutfits);
	$cache->setContent($vocGroups);
	$cache->save();
} else {
	$vocGroups = $cache->load();
}

view('highscores');

theme_close();
