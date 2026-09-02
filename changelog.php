<?php require_once 'engine/init.php'; theme_open();

/**
 * Public changelog.
 *
 * Creating, editing and deleting entries moved to the admin panel
 * (Content > Changelog). This page only reads now - it used to carry the whole
 * editor inline, which meant a public page held database writes.
 */

$cache = new Cache('engine/cache/changelog');
$cache->useMemory(false);
$changelogs = $cache->load();

// The cache is written by the admin panel. If it has never been built - a
// fresh install, or someone cleared engine/cache/ - fall back to the table.
if ($changelogs === false || $changelogs === null) {
	$changelogs = mysql_select_multi("
		SELECT `id`, `text`, `time`, `report_id`, `status`
		FROM `znote_changelog`
		ORDER BY `id` DESC;
	");
	if (is_array($changelogs)) {
		$cache->setContent($changelogs);
		$cache->save();
	}
}

if (!is_array($changelogs)) {
	$changelogs = array();
}

view('changelog');

theme_close();
