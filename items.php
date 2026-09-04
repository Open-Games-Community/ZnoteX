<?php require_once 'engine/init.php'; theme_open();

/**
 * Equipable items browser.
 *
 * items.xml is uploaded and parsed in the admin panel, under Server Info. This
 * page only reads the result:
 *   $itemsEnabled  the page is turned on in config.php
 *   $items         the item list, or false when nothing was published
 *   $itemsAdmin, $itemsUpdated, $itemsFailed  kept at false for older themes
 */

$itemsEnabled = ($config['items'] == true);
$itemsAdmin   = false;
$itemsUpdated = false;
$itemsFailed  = false;
$items        = false;

if ($itemsEnabled) {
	$items = serverdata_load('items');

	if ($items === false && is_file(serverdata_file('items.xml'))) {
		serverdata_rebuild('items');
		$items = serverdata_load('items');
	}
}

view('items');

theme_close();
