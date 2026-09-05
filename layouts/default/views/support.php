<?php
/**
 * Support list.
 *
 * Prepared by support.php: $srtGrp - staff grouped by in-game position.
 */
?>
<h1><?= t('support.title') ?></h1>
<?php
$writeHeader = true;
if (!empty($srtGrp)) {
	foreach (array_reverse($srtGrp) as $grpName => $grpList) {
		?>
		<table id="supportTable" class="table table-striped">
			<?php if ($writeHeader) {
			$writeHeader = false; ?>
			<tr class="yellow">
				<th width="30%"><?= t('common.group') ?></th>
				<th width="40%"><?= t('common.name') ?></th>
				<th width="30%"><?= t('common.status') ?></th>
			</tr>
			<?php
			}
			foreach ($grpList as $char) {
				if ($char['name'] != $config['website_char']) {
					echo '<tr>';
					echo "<td width='30%'>". $grpName ."</td>";
					echo '<td width="40%"><a href="characterprofile.php?name='. $char['name'] .'">'. $char['name'] .'</a></td>';
					echo "<td width='30%'>". online_id_to_name($char['online']) ."</td>";
					echo '</tr>';
				}
			}
			?>
		</table>
		<?php
	}
}
echo'</table>'; 