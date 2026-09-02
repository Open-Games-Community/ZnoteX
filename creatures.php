<?php require_once 'engine/init.php';
theme_open();

/**
 * Creature library.
 *
 * Reads the server's own monsters.xml and the per-monster files it points at.
 * That is 870+ file reads on a full data set, so the result is cached: without
 * it every visitor would re-parse the whole monster folder.
 *
 * Prepared for the view:
 *   $creaturesPath   the data folder used, for the "not configured" message
 *   $creatures       [name, health, experience, speed, race, looktype]
 *   $creatureSearch  the current filter
 *   $creatureRaces   races present in the data, for the filter buttons
 *   $creatureError   message when the files could not be read, else ''
 */

$creatureError  = '';
$creatures      = array();
$creatureRaces  = array();
$creatureSearch = trim((string)($_GET['search'] ?? ''));
$creatureRace   = trim((string)($_GET['race'] ?? ''));

// The server folder, from config.php. Fall back to the layout monster_loot
// uses so both pages agree on where the data lives.
$creaturesPath = rtrim((string)($config['server_path'] ?? ''), '/\\');
if ($creaturesPath === '') {
	$creaturesPath = 'misc';
}

$cache = new Cache('engine/cache/creatures');
$cache->useMemory(false);

// Parsing 870 monster files takes seconds, so this must not follow the site's
// general cache lifespan - that is tuned for data which changes constantly and
// can be as low as 5 seconds. Monster files change when the server is updated,
// so once a day is generous. Delete engine/cache/creatures.cache to force a
// rebuild after editing the server's data.
$cache->setExpiration(86400);

if ($cache->hasExpired()) {

	$index = @simplexml_load_file($creaturesPath . '/data/monster/monsters.xml');

	if ($index === false) {
		$creatureError = 'Could not read data/monster/monsters.xml. Check $config[\'server_path\'].';
	} else {
		foreach ($index->monster as $entry) {
			$file = (string)$entry['file'];
			if ($file === '') {
				continue;
			}

			$monster = @simplexml_load_file($creaturesPath . '/data/monster/' . $file);
			if ($monster === false) {
				// A missing file is the server's problem, not ours: skip it
				// rather than abort the whole page.
				continue;
			}

			$health = isset($monster->health) ? (int)$monster->health['max'] : 0;
			$look   = isset($monster->look) ? (int)$monster->look['type'] : 0;

			$creatures[] = array(
				'name'       => (string)($entry['name'] ?? $monster['name']),
				'health'     => $health,
				'experience' => (int)$monster['experience'],
				'speed'      => (int)$monster['speed'],
				'race'       => (string)$monster['race'],
				'looktype'   => $look,
			);
		}

		usort($creatures, static function (array $a, array $b): int {
			return strcasecmp($a['name'], $b['name']);
		});

		$cache->setContent($creatures);
		$cache->save();
	}

} else {
	$loaded    = $cache->load();
	$creatures = is_array($loaded) ? $loaded : array();
}

// Races present, for the filter row.
foreach ($creatures as $creature) {
	if ($creature['race'] !== '') {
		$creatureRaces[$creature['race']] = true;
	}
}
$creatureRaces = array_keys($creatureRaces);
sort($creatureRaces);

// Filtering happens on the cached array: no second pass over the files.
if ($creatureSearch !== '' || $creatureRace !== '') {
	$needle    = strtolower($creatureSearch);
	$creatures = array_values(array_filter($creatures, static function (array $c) use ($needle, $creatureRace): bool {
		if ($creatureRace !== '' && $c['race'] !== $creatureRace) {
			return false;
		}
		return $needle === '' || str_contains(strtolower($c['name']), $needle);
	}));
}

view('creatures');

theme_close();
