<?php
$filepath = '../';
require_once 'module.php';

// Autofetch API modules
$directory = 'modules';
$plugins = [];

// Load base
$plugins['base'] = [
	'player' => 'test.php'
];

$baseIterator = new DirectoryIterator($directory);

foreach ($baseIterator as $dir) {
	if ($dir->isDot() || !$dir->isDir()) {
		continue;
	}

	$moduleName = $dir->getFilename();
	$moduleIterator = new DirectoryIterator($dir->getPathname());

	foreach ($moduleIterator as $file) {
		if (!$file->isFile()) {
			continue;
		}

		if ($file->getExtension() !== 'php') {
			continue;
		}

		$plugins[$moduleName][] = $file->getFilename();
	}
}

// Global data
$response['modules'] = $plugins;
$response['data']['title'] = $config['site_title'] ?? '';
$response['data']['slogan'] = $config['site_title_context'] ?? '';
$response['data']['time'] = getClock(time(), false, true);
$response['data']['time_formatted'] = getClock(time(), true, true);

// Account count
$accounts = mysql_select_single("SELECT COUNT(*) AS `count` FROM `accounts`");
$response['data']['accounts'] = (int)($accounts['count'] ?? 0);

// Player count
$players = mysql_select_single("SELECT COUNT(*) AS `count` FROM `players`");
$response['data']['players'] = (int)($players['count'] ?? 0);

// Online players
if ($config['ServerEngine'] !== 'TFS_10') {
	$online = mysql_select_single("
		SELECT COUNT(*) AS `count`, COUNT(DISTINCT `lastip`) AS `unique`
		FROM `players`
		WHERE `online` = 1
	");
} else {
	$online = mysql_select_single("
		SELECT COUNT(o.player_id) AS `count`, COUNT(DISTINCT p.lastip) AS `unique`
		FROM `players_online` o
		INNER JOIN `players` p ON o.player_id = p.id
	");
}

$response['data']['online'] = (int)($online['count'] ?? 0);
$response['data']['online_unique_ip'] = (int)($online['unique'] ?? 0);

// Server info
$response['data']['client'] = $config['client'] ?? null;
$response['data']['port'] = $config['port'] ?? null;
$response['data']['guildwar'] = $config['guildwar_enabled'] ?? false;
$response['data']['forum'] = $config['forum']['enabled'] ?? false;

SendResponse($response);
?>