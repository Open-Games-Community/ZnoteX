<?php require_once 'engine/init.php'; theme_open();

/**
 * Monster loot checker.
 *
 * Reads the server's own XML files and flattens every monster's loot tree into
 * plain rows, so the view only has to print them:
 *
 *   $monsterLootError  message when an XML file could not be read, else ''
 *   $itemList          [item id => item name]
 *   $rarity            [label => minimum percent]
 *   $monsterList       one entry per monster:
 *                        name   monster name
 *                        state  'loot' | 'empty' | 'failed'
 *                        file   the monster file, for the 'failed' message
 *                        loot   rows of [level, id, count, chance]
 */

$otdir = 'misc/';

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
$lootRate         = 1;

/** Flatten one monster's nested <item> tree into rows. */
function znote_flatten_loot($loot, int $level, array &$rows, float $lootRate): void {
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
			'chance' => ($chance / 1000) * $lootRate,
		);

		foreach ($entry as $child) {
			znote_flatten_loot($child->item, $level + 1, $rows, $lootRate);
		}
	}
}

$items = @simplexml_load_file($otdir . '/data/items/items.xml');

if ($items === false) {
	$monsterLootError = 'Could not load items!';
} else {

	foreach ($items->item as $item) {
		$itemList[(int)$item['id']] = (string)$item['name'];
	}

	if (isset($_GET['lootrate'])) {
		// Not $config: that would overwrite the whole site configuration.
		$serverLua = @parse_ini_file($otdir . '/config.lua');
		if (is_array($serverLua) && isset($serverLua['rate_loot'])) {
			$lootRate = (float)$serverLua['rate_loot'];
		}
	}

	$monsters = @simplexml_load_file($otdir . '/data/monster/monsters.xml');

	if ($monsters === false) {
		$monsterLootError = 'Could not load monsters!';
	} else {

		foreach ($monsters->monster as $monster) {
			$loot = @simplexml_load_file($otdir . '/data/monster/' . $monster['file']);

			if ($loot === false) {
				$monsterList[] = array(
					'name'  => (string)$monster['name'],
					'state' => 'failed',
					'file'  => (string)$monster['file'],
					'loot'  => array(),
				);
				continue;
			}

			$rows = array();
			if (isset($loot->loot->item)) {
				znote_flatten_loot($loot->loot->item, 1, $rows, $lootRate);
			}

			$monsterList[] = array(
				'name'  => (string)$monster['name'],
				'state' => $rows ? 'loot' : 'empty',
				'file'  => (string)$monster['file'],
				'loot'  => $rows,
			);
		}
	}
}

view('monster_loot');

theme_close();
