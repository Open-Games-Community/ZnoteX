<?php
// End cache

?>
<h1>Who is online?</h1>

<?php
/**
 * $onlineNow, $onlineRecord and $onlineBroken come from onlinelist.php.
 * The record lives in znote_config, not in a table of its own.
 */
if (!empty($onlineRecord['players'])): ?>
	<p class="txt">
		<?php if (!empty($onlineBroken)): ?>
			<strong>New record!</strong>
			<?= (int)$onlineNow ?> players online right now &mdash; the most this server has ever had.
		<?php else: ?>
			Record: <strong><?= (int)$onlineRecord['players'] ?></strong> players online
			on <?= htmlspecialchars(getClock((int)$onlineRecord['time'], true), ENT_QUOTES, 'UTF-8') ?>.
		<?php endif; ?>
	</p>
<?php endif; ?>
<?php

// Players currently logged in
if (!empty($array) && $array !== false): ?>
	<h2>Currently online:</h2>
	<table id="onlinelistTable" class="table table-striped table-hover">
		<tr class="yellow">
			<?php if ($loadOutfits) echo "<th>Outfit</th>"; ?>
			<th>Name:</th>
			<th>Guild:</th>
			<th>Level:</th>
			<th>Vocation:</th>
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
	<?php
else:
	?>
	<p>Nobody is online.</p>
	<?php
endif;

// Players online logout history
if ($history["enabled"]) {
	$time = time();
	if (!empty($recents) && $recents !== false): ?>
		<h2>Online past <?php echo $history['days']; ?> days:</h2>
		<table id="recentlistTable" class="table table-striped table-hover">
			<tr class="yellow">
				<?php if ($loadOutfits) echo "<th>Outfit</th>"; ?>
				<th>Name:</th>
				<th>Guild:</th>
				<th>Level:</th>
				<th>Logout [days] - date</th>
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
		<?php
	else:
		?>
		<p>Nobody has logged in past <?php echo $history['days']; ?> days.</p>
		<?php
	endif;
}

