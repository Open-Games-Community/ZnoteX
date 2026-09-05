<?php

echo '<CENTER><H2>'. t('toponline.title', ['site' => $config['site_title']]) .'</H2></CENTER>
<BR>
<table class="table table-striped">
		<td><center><b>#</b></center></td>
		<td width="10%"><b>'. t('common.name') .'</b></td>';
if($type == "sum")
	echo '<td ><center><b><center><a href="?subtopic=onlinetime&type=sum">'. t('common.total') .'</a></center></B></TD>';
else
	echo '<td ><center><b><center><a href="?subtopic=onlinetime&type=sum">'. t('common.total') .'</a></center></B></TD>';
for($i = 3; $i >= 2; $i--)
{
	if($type == $i)
		echo '<TD ><b><center><a href="?subtopic=onlinetime&type='.$i.'">'. t('toponline.days_ago', ['days' => $i]) .'</a></center></B></TD>';
	else
		echo '<TD ><b><center><a href="?subtopic=onlinetime&type='.$i.'">'. t('toponline.days_ago', ['days' => $i]) .'</a></center></B></TD>';
}
if($type == 1)
	echo '<TD ><b><center><a href="?subtopic=onlinetime&type=1">'. t('toponline.day_ago') .'</a></center></B></TD>';
else
	echo '<TD ><b><center><a href="?subtopic=onlinetime&type=1">'. t('toponline.day_ago') .'</a></center></B></TD>';
if(empty($type))
	echo '<TD><b><center><a href="?subtopic=onlinetime">'. t('common.today') .'</a></center></B></TD>';
else
	echo '<TD ><b><center><a href="?subtopic=onlinetime">'. t('common.today') .'</a></center></B></TD>';
echo '</TR>';
$number_of_rows = 1;
if($znotePlayers)
foreach($znotePlayers as $player)
{
	echo '<td><center>'. $number_of_rows . '.</center></td>';
	echo '<td><a href="characterprofile.php?name=' .$player['name']. '">' .$player['name']. '</a>';
	echo '<br> ' .$player['level']. ' '.htmlspecialchars(vocation_id_to_name($player['vocation'])).' ';
	echo '<td ><center>' .onlineTimeTotal($player['onlinetimeall']).'</td>';
	$number_of_rows++;
	echo '<td ><center>'.hours_and_minutes($player['onlinetime3']).'</center></td><td ><center>'.hours_and_minutes($player['onlinetime2']).'</center></td><td ><center>'.hours_and_minutes($player['onlinetime1']).'</center></td><td ><center>'.hours_and_minutes($player['onlinetimetoday']).'</center></td></tr>';
}
echo '</TABLE></div>';
?>
