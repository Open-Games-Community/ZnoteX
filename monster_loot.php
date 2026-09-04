<?php require_once 'engine/init.php'; theme_open();

/**
 * Monster loot checker.
 *
 * Reads the same data the admin panel collects under Server Info: items.xml for
 * the item names, and the monster folder for the loot trees. Flattens every
 * monster's nested loot into plain rows, so the view only has to print them:
 *
 *   $monsterLootError  message when the data could not be read, else ''
 *   $itemList          [item id => item name]
 *   $rarity            [label => minimum percent]
 *   $monsterList       one entry per monster:
 *                        name   monster name
 *                        state  'loot' | 'empty' | 'failed'
 *                        file   the monster file, for the 'failed' message
 *                        loot   rows of [level, id, count, chance]
 */

// In percent (highest first).
$rarity = array(
	'Not Rare'  => 7,
	'Semi Rare' => 2,
	'Rare'      => 0.5,
	'Very Rare' => 0
);

$monsterLootError = '';
$itemList         = array();
$monsterList      = array();

/** Flatten one monster's nested <item> tree into rows. */
function znote_flatten_loot($loot, int $level, array &$rows): void {
	if (empty($loot)) {
		return;
	}

	foreach ($loot as $entry) {
		$chance = (float)$entry['chance'];
		if (!$chance) {
			$chance = (float)$entry['chance1'];
		}

		$rows[] = array(
			'level'  => $level,
			'id'     => (int)$entry['id'],
			'count'  => (int)$entry['countmax'],
			'chance' => $chance / 1000,
		);

		// The nested <item> children of this entry - a bag's contents. Recursing
		// per child and then reading that child's own ->item skipped a level,
		// so bag contents never reached the page.
		if (isset($entry->item)) {
			znote_flatten_loot($entry->item, $level + 1, $rows);
		}
	}
}

// Parsing every monster file is far too slow to repeat per visitor, so the
// flattened result is cached at rate 1 and the loot rate applied on the way
// out. The admin panel drops this cache whenever items or monsters change.
$cache = new Cache('engine/cache/monster_loot');
$cache->useMemory(false);
$cache->setExpiration(PHP_INT_MAX);

$loaded = $cache->load();

if (is_array($loaded) && isset($loaded['items'], $loaded['monsters'])) {

	$itemList    = $loaded['items'];
	$monsterList = $loaded['monsters'];

} else {

	$itemsFile = serverdata_file('items.xml');
	$items     = is_file($itemsFile) ? @simplexml_load_file($itemsFile) : false;

	if ($items === false) {
		$monsterLootError = 'No items.xml yet. Upload one in the admin panel, under Server Info.';
	} else {

		foreach ($items->item as $item) {
			$itemList[(int)$item['id']] = (string)$item['name'];
		}

		$source   = serverdata_creature_source();
		$monsters = is_file($source['index']) ? @simplexml_load_file($source['index']) : false;

		if ($monsters === false) {
			$monsterLootError = 'No monsters.xml at ' . $source['label'] . '. Upload your data/monster/ folder as a .zip in the admin panel, under Server Info.';
		} else {

			foreach ($monsters->monster as $monster) {
				$file = (string)$monster['file'];
				$loot = ($file !== '' && strpos($file, '..') === false)
					? @simplexml_load_file($source['dir'] . '/' . $file)
					: false;

				if ($loot === false) {
					$monsterList[] = array(
						'name'  => (string)$monster['name'],
						'state' => 'failed',
						'file'  => $file,
						'loot'  => array(),
					);
					continue;
				}

				$rows = array();
				if (isset($loot->loot->item)) {
					znote_flatten_loot($loot->loot->item, 1, $rows);
				}

				$monsterList[] = array(
					'name'  => (string)$monster['name'],
					'state' => $rows ? 'loot' : 'empty',
					'file'  => $file,
					'loot'  => $rows,
				);
			}

			$cache->setContent(array('items' => $itemList, 'monsters' => $monsterList));
			$cache->save();
		}
	}
}

// The server's own loot rate, from the imported config.lua.
if (isset($_GET['lootrate'])) {
	$luaConfig = serverdata_load('config');
	$lootRate  = (is_array($luaConfig) && isset($luaConfig['rateLoot'])) ? (float)$luaConfig['rateLoot'] : 1;

	if ($lootRate > 0 && $lootRate != 1) {
		foreach ($monsterList as &$monster) {
			foreach ($monster['loot'] as &$drop) {
				$drop['chance'] *= $lootRate;
			}
			unset($drop);
		}
		unset($monster);
	}
}

view('monster_loot');

theme_close();
