<?php require_once 'engine/init.php';
if ($config['require_login']['guildwars']) protect_page();
if ($config['log_ip']) znote_visitor_insert_detailed_data(3);
if ($config['guildwar_enabled'] === false) {
	header('Location: guilds.php');
	exit();
}
$isOtx = ($config['CustomVersion'] == 'OTX') ? true : false;
theme_open();

view('guildwar');

theme_close();
