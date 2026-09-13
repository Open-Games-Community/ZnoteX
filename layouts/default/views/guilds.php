<?php
?>
<style type="text/css">
	#guildsTable tr {
		cursor: pointer;
	}
</style>
<table id="guildsTable" class="table table-striped">
	<tr class="yellow">
		<th>Logo</th>
		<th><?= t('common.description') ?></th>
		<th><?= t('guild.data') ?></th>
	</tr>
		<?php
		foreach ($guilds as $guild) {
			if ($guild['total'] >= 1) {
				$url = url("guilds.php?name=". $guild['name']);
				?>
				<tr class="special" onclick="javascript:window.location.href='<?php echo $url; ?>'">
					<td style="width: 100px;">
						<img style="max-height: 100px; margin: auto; display: block;"
							src="<?php logo_exists($guild['name']); ?>">
					</td>
					<td>
						<b><?php echo $guild['name']; ?></b>
						<?php if (strlen($guild['motd']) > 0) echo '<br>'.$guild['motd']; ?>
					</td>
					<td>
						<?php echo "Total members: ".$guild['level']['players']; ?>
						<br><?php echo "Average level: ".$guild['level']['avg'].""; ?>
						<br><?php echo "Guild level: ".$guild['level']['total']; ?>
					</td>
				</tr>
				<?php
			}
		}
		?>
</table>
