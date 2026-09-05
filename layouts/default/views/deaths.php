<?php
if ($deaths) {
?>
<h1><?= t('deaths.latest') ?></h1>
<table id="deathsTable" class="table table-striped">
	<tr class="yellow">
		<th><?= t('killers.victim') ?></th>
		<th><?= t('common.time') ?></th>
		<th><?= t('killers.killer') ?></th>
	</tr>
	<?php foreach ($deaths as $death) {
		echo '<tr>';
		echo "<td>". t('deaths.at_level', ['level' => $death['level']]) ." <a href='characterprofile.php?name=". $death['victim'] ."'>". $death['victim'] ."</a></td>";
		echo "<td>". getClock($death['time'], true) ."</td>";
		if ($death['is_player'] == 1) echo "<td>". t('deaths.player') ." <a href='characterprofile.php?name=". $death['killed_by'] ."'>". $death['killed_by'] ."</a></td>";
		else if ($death['is_player'] == 0) {
			if ($config['ServerEngine'] == 'TFS_03') echo "<td>". t('deaths.monster') ." ". ucfirst(str_replace("a ", "", $death['killed_by'])) ."</td>";
			else echo "<td>". t('deaths.monster') ." ". ucfirst($death['killed_by']) ."</td>";
		}
		else echo "<td>". $death['killed_by'] ."</td>";
		echo '</tr>';
	} ?>
</table>
<?php
} else echo t('deaths.none');
