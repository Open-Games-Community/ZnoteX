<?php
$cache = new Cache('engine/cache/asideServerInfo');
if ($cache->hasExpired()) {
	$asideServerInfo = mysql_select_single("
		SELECT 
			(SELECT COUNT(`id`) FROM `accounts`) as `accounts`,
			(SELECT COUNT(`id`) FROM `players`) as `players`,
			(SELECT COUNT(`player_id`) FROM `players_online`) as `online`
	");
	$cache->setContent($asideServerInfo);
	$cache->save();
} else {
	$asideServerInfo = $cache->load();
}
?>
<div class="well widget">
	<div class="header">
		<?= t('widget.serverinfo.title') ?>
	</div>
	<div class="body">
		<ul>
			<li><a href="onlinelist.php"><?= t('widget.serverinfo.online', ['count' => $asideServerInfo['online']]) ?></a></li>
			<li><?= t('widget.serverinfo.accounts', ['count' => $asideServerInfo['accounts']]) ?></li>
			<li><?= t('widget.serverinfo.players', ['count' => $asideServerInfo['players']]) ?></li>
		</ul>
	</div>
</div>
