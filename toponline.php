<?php
require_once 'engine/init.php';
theme_open();
if (!$config['toponline']['enabled']) {
echo 'This page has been disabled at config.php.';
theme_close();
	exit();
}
$limit = $config['toponline']['limit'];
$type = (isset($_GET['type'])) ? getValue($_GET['type'] ?? null) : false;

function onlineTimeTotal($value)
{
	$hours = floor($value / 3600);
	$value = $value - $hours * 3600;
	$minutes = floor($value / 60);
		return '<font color="black">'.$hours.'h '.$minutes.'m</font>';
}
function hours_and_minutes($value, $color = 1)
{
	$hours = floor($value / 3600);
	$value = $value - $hours * 3600;
	$minutes = floor($value / 60);
	if($color != 1)
		return '<font color="black">'.$hours.'h '.$minutes.'m</font>';
	else
		if($hours >= 12)
			return '<font color="red">'.$hours.'h '.$minutes.'m</font>';
		elseif($hours >= 6)
			return '<font color="black">'.$hours.'h '.$minutes.'m</font>';
		else
			return '<font color="green">'.$hours.'h '.$minutes.'m</font>';
}
if(empty($type))
	$znotePlayers = mysql_select_multi('SELECT * FROM `znote_players` AS `z` JOIN `players` AS `p` WHERE `p`.`id`=`z`.`player_id` and `p`.`group_id` < 3 ORDER BY `onlinetimetoday` DESC LIMIT '.$limit);
elseif($type == "sum")
	$znotePlayers = mysql_select_multi('SELECT * FROM `znote_players` AS `z` JOIN `players` AS `p` WHERE `p`.`id`=`z`.`player_id` and `p`.`group_id` < 3 ORDER BY `z`.`onlinetimeall` DESC LIMIT '. $limit);
elseif($type >= 1 && $type <= 4)
	$znotePlayers = mysql_select_multi('SELECT * FROM `znote_players` AS `z` JOIN `players` AS `p` WHERE `p`.`id`=`z`.`player_id` and `p`.`group_id` < 3 ORDER BY `onlinetime' . (int) $type . '` DESC LIMIT '.$limit);

view('toponline');

theme_close();
