<?php
?>
<table id="tbl_powergamers">
	<thead>
		<tr>
			<th colspan="9"><h1><?= t('powergamers.title') ?></h1></th>
		</tr>
		<tr>
			<th><?= t('common.name') ?></th>
			<th><?= t('powergamers.diff') ?></th>
			<th><?php echo $dates['d0ago']; ?></th>
			<th><?php echo $dates['d1ago']; ?></th>
			<th><?php echo $dates['d2ago']; ?></th>
			<th><?php echo $dates['d3ago']; ?></th>
			<th><?php echo $dates['d4ago']; ?></th>
			<th><?php echo $dates['d5ago']; ?></th>
			<th><?php echo $dates['d6ago']; ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($players AS $i => $player): ?>
			<tr>
				<td><?php echo $i+1 .". "; ?><a href="/characterprofile.php?name=<?php echo $player['name']; ?>"><?php echo $player['name']; ?></a></td>
				<td><?php echo number_format($player['diff_exp'] / 1000,0,'',' '); ?></td>
				<td><?php echo number_format($player['diff_0'] / 1000,0,'',' '); ?></td>
				<td><?php echo number_format($player['diff_1'] / 1000,0,'',' '); ?></td>
				<td><?php echo number_format($player['diff_2'] / 1000,0,'',' '); ?></td>
				<td><?php echo number_format($player['diff_3'] / 1000,0,'',' '); ?></td>
				<td><?php echo number_format($player['diff_4'] / 1000,0,'',' '); ?></td>
				<td><?php echo number_format($player['diff_5'] / 1000,0,'',' '); ?></td>
				<td><?php echo number_format($player['diff_6'] / 1000,0,'',' '); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<style type="text/css">
	#tbl_powergamers {
		padding:  0;
	}
</style>
