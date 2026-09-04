<?php require_once 'engine/init.php';
theme_open();

/**
 * Creature library.
 *
 * The monster data is uploaded and parsed in the admin panel, under Server Info.
 * An install that still points $config['server_path'] at the server folder keeps
 * working: that is the fallback source when nothing was uploaded.
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

$creatureSource = serverdata_creature_source();
$creaturesPath  = $creatureSource['label'];

$loaded = serverdata_load('creatures');

if ($loaded === false) {
	$rebuildError = null;
	if (serverdata_rebuild('creatures', $rebuildError)) {
		$loaded = serverdata_load('creatures');
	} else {
		$creatureError = (string)$rebuildError;
	}
}

$creatures = is_array($loaded) ? $loaded : array();

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
