<?php
require_once '../../module.php';

// Module version
$response['version']['module'] = 1;

if (znote_server_adapter()->normalizedEngine() !== 'TFS_10') {
	$players = db()->fetchAll("
		SELECT `name`, `level`, `vocation`
		FROM `players`
		WHERE `online` = 1
		ORDER BY `level` DESC
	");
} else {
	$players = db()->fetchAll("
		SELECT `p`.`name`, `p`.`level`, `p`.`vocation`
		FROM `players_online` `o`
		INNER JOIN `players` `p` ON `o`.`player_id` = `p`.`id`
		ORDER BY `p`.`level` DESC
	");
}

if (!is_array($players)) {
	$players = [];
}

foreach ($players as &$player) {
	$vocId = (int)($player['vocation'] ?? -1);
	$player['vocation_name'] = $config['vocations'][$vocId]['name'] ?? 'Unknown';
}
unset($player);

$response['data']['count'] = count($players);
$response['data']['players'] = $players;

SendResponse($response);
