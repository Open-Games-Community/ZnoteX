<?php
// End cache

?>
<div class="znx-acct">

<div class="znx-acct-head"><?= t('online.heading') ?></div>

<?php
/**
 * $onlineNow, $onlineRecord and $onlineBroken come from onlinelist.php.
 * The record lives in znote_config, not in a table of its own.
 */
if (!empty($onlineRecord['players'])): ?>
	<div class="znx-acct-info">
		<?php if (!empty($onlineBroken)): ?>
			<strong><?= t('online.new_record') ?></strong>
			<?= t('online.new_record_line', ['count' => (int)$onlineNow]) ?>
		<?php else: ?>
			<?= t('online.record_line', ['count' => (int)$onlineRecord['players'], 'date' => htmlspecialchars(getClock((int)$onlineRecord['time'], true), ENT_QUOTES, 'UTF-8')]) ?>
		<?php endif; ?>
	</div>
<?php endif; ?>
<?php

// Players currently logged in
if (!empty($array) && $array !== false): ?>
	<div class="znx-acct-head"><?= t('online.currently') ?></div>
	<div class="znx-acct-table-wrap">
	<table id="onlinelistTable" class="znx-acct-table">
		<tr class="yellow">
			<?php if ($loadOutfits) echo "<th>". t('common.outfit') ."</th>"; ?>
			<th><?= t('online.label_name') ?></th>
			<th><?= t('online.label_guild') ?></th>
			<th><?= t('online.label_level') ?></th>
			<th><?= t('online.label_vocation') ?></th>
		</tr>
		<?php
		foreach ($array as $value):
			$url = url("characterprofile.php?name=". $value['name']);
			$flag = ($loadFlags === true && strlen($value['flag']) > 1) ? '<img src="' . $config['country_flags']['server'] . '/' . $value['flag'] . '.png">  ' : '';
			$guildname = (!empty($value['gname'])) ? '<a href="guilds.php?name='. $value['gname'] .'">'. $value['gname'] .'</a>' : '';
			?>
			<tr class="special">
				<?php if ($loadOutfits): ?>
					<td class="outfitColumn"><img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $value['type']; ?>&addons=<?php echo $value['addons']; ?>&head=<?php echo $value['head']; ?>&body=<?php echo $value['body']; ?>&legs=<?php echo $value['legs']; ?>&feet=<?php echo $value['feet']; ?>" alt="img"></td>
				<?php endif; ?>
				<td><?php echo $flag; ?><a href="characterprofile.php?name=<?php echo $value['name']; ?>"><?php echo $value['name']; ?></a></td>
				<td><?php echo $guildname; ?></td>
				<td><?php echo $value['level']; ?></td>
				<td><?php echo vocation_id_to_name($value['vocation']); ?></td>
			</tr>
			<?php
		endforeach; ?>
	</table>
	</div>
	<?php
else:
	?>
	<div class="znx-empty-box"><?= t('online.nobody') ?></div>
	<?php
endif;

// Players online logout history
if ($history["enabled"]) {
	$time = time();
	if (!empty($recents) && $recents !== false): ?>
		<div class="znx-acct-head"><?= t('online.past_days', ['days' => $history['days']]) ?></div>
		<div class="znx-acct-table-wrap">
		<table id="recentlistTable" class="znx-acct-table">
			<tr class="yellow">
				<?php if ($loadOutfits) echo "<th>". t('common.outfit') ."</th>"; ?>
				<th><?= t('online.label_name') ?></th>
				<th><?= t('online.label_guild') ?></th>
				<th><?= t('online.label_level') ?></th>
				<th><?= t('online.logout_days') ?></th>
			</tr>
			<?php
			foreach ($recents as $value):
				$days = floor(($time - $value['lastlogout']) / 86400);
				$url = url("characterprofile.php?name=". $value['name']);
				$flag = ($loadFlags === true && strlen($value['flag']) > 1) ? '<img src="' . $config['country_flags']['server'] . '/' . $value['flag'] . '.png">  ' : '';
				$guildname = (!empty($value['gname'])) ? '<a href="guilds.php?name='. $value['gname'] .'">'. $value['gname'] .'</a>' : '';
				?>
				<tr class="special">
					<?php if ($loadOutfits): ?>
						<td class="outfitColumn"><img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $value['type']; ?>&addons=<?php echo $value['addons']; ?>&head=<?php echo $value['head']; ?>&body=<?php echo $value['body']; ?>&legs=<?php echo $value['legs']; ?>&feet=<?php echo $value['feet']; ?>" alt="img"></td>
					<?php endif; ?>
					<td><?php echo $flag; ?><a href="characterprofile.php?name=<?php echo $value['name']; ?>"><?php echo $value['name']; ?></a></td>
					<td><?php echo $guildname; ?></td>
					<td><?php echo $value['level']; ?></td>
					<td><?php echo "{$days}D: " . getClock($value['lastlogout'], true); ?></td>
				</tr>
				<?php
			endforeach; ?>
		</table>
		</div>
		<?php
	else:
		?>
		<div class="znx-empty-box"><?= t('online.nobody_past_days', ['days' => $history['days']]) ?></div>
		<?php
	endif;
}
?>

</div><!-- .znx-acct -->
