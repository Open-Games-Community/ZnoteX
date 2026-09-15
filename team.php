<?php require_once 'engine/init.php'; theme_open();


$staffChars = db()->fetchAll("
	SELECT `id`, `name`, `group_id`, `sex`, `looktype`, `lookhead`, `lookbody`, `looklegs`, `lookfeet`, `lookaddons`
	FROM `players`
	WHERE `group_id` > 1
	ORDER BY `group_id` DESC, `name` ASC;
");
$staffChars = is_array($staffChars) ? $staffChars : array();

$staffGroups = array();
foreach ($staffChars as $member) {
	$gid = (int)$member['group_id'];
	if (!isset($staffGroups[$gid])) {
		$staffGroups[$gid] = array(
			'label'   => group_id_to_name($gid) ?: t('team.unnamed_group', ['id' => $gid]),
			'members' => array(),
		);
	}
	$staffGroups[$gid]['members'][] = $member;
}
krsort($staffGroups);

$loadOutfits = !empty($config['show_outfits']['imageServer']);

view('team', [
	'staffGroups' => $staffGroups,
	'loadOutfits' => $loadOutfits,
]);

theme_close();
