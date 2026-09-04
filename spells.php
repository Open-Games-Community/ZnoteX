<?php require_once 'engine/init.php'; theme_open();

/**
 * Spell list.
 *
 * spells.xml is uploaded and parsed in the admin panel, under Server Info. This
 * page only reads the result:
 *   $spells  the spell list, or false when nothing was published
 *   $showSpellsForm, $spellsUpdated, $spellsFailed  kept at false for older themes
 */

$showSpellsForm = false;
$spellsUpdated  = false;
$spellsFailed   = false;

$spells = serverdata_load('spells');

if ($spells === false && is_file(serverdata_file('spells.xml'))) {
	serverdata_rebuild('spells');
	$spells = serverdata_load('spells');
}

view('spells');

theme_close();
