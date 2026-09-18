<?php

if ($vocGroups) {
	$vocGroup = (is_array($vocGroups[$vocation])) ? $vocGroups[$vocation] : $vocGroups[$vocGroups[$vocation]];
	?>

	<div class="znx-acct">

	<div class="znx-acct-head"><?= t('highscores.heading', ['what' => skillName($type) .", ". (($vocation === 'all') ? t('vocation.any_lower') : vocation_id_to_name($vocation))]) ?></div>

	<form action="" method="GET" class="znx-acct-toolbar">

		<div class="znx-acct-toolbar__field">
		<select name="type" class="znx-acct-select">
			<option value="7" <?php if ($type == 7) echo "selected"; ?>><?= t('skill.experience') ?></option>
			<option value="8" <?php if ($type == 8) echo "selected"; ?>><?= t('skill.magic') ?></option>
			<option value="5" <?php if ($type == 5) echo "selected"; ?>><?= t('skill.shield') ?></option>
			<option value="2" <?php if ($type == 2) echo "selected"; ?>><?= t('skill.sword') ?></option>
			<option value="1" <?php if ($type == 1) echo "selected"; ?>><?= t('skill.club') ?></option>
			<option value="3" <?php if ($type == 3) echo "selected"; ?>><?= t('skill.axe') ?></option>
			<option value="4" <?php if ($type == 4) echo "selected"; ?>><?= t('skill.distance') ?></option>
			<option value="6" <?php if ($type == 6) echo "selected"; ?>><?= t('skill.fishing') ?></option>
			<option value="9" <?php if ($type == 9) echo "selected"; ?>><?= t('skill.fist') ?></option>
		</select>
		</div>

		<div class="znx-acct-toolbar__field">
		<select name="vocation" class="znx-acct-select">
			<option value="all" <?php if (!is_int($vocation)) echo "selected"; ?>><?= t('vocation.any') ?></option>
			<?php
			foreach ($configVocations as $v_id => $v_data) {
				if ($v_data['fromVoc'] === false) {
					$selected = (is_int($vocation) && $vocation == $v_id) ? " selected $vocation = $v_id" : "";
					echo '<option value="'. $v_id .'"'. $selected .'>'. $v_data['name'] .'</option>';
				}
			}
			?>
		</select>
		</div>

		<div class="znx-acct-toolbar__field">
		<select name="page" class="znx-acct-select">
			<?php
			$pages = ($vocGroup[$type] !== false) ? ceil(min(($highscore['rows'] / $highscore['rowsPerPage']), (count($vocGroup[$type]) / $highscore['rowsPerPage']))) : 1;
			for ($i = 0; $i < $pages; $i++) {
				$x = $i + 1;
				if ($x == $page) echo "<option value='".$x."' selected>". t('highscores.page', ['n' => $x]) ."</option>";
				else echo "<option value='".$x."'>". t('highscores.page', ['n' => $x]) ."</option>";
			}
			?>
		</select>
		</div>

		<div class="znx-acct-toolbar__submit"><button type="submit" class="znx-acct-btn"><?= t('common.view') ?></button></div>
	</form>

	<div class="znx-acct-table-wrap">
	<table id="highscoresTable" class="znx-acct-table">

		<tr class="yellow">
			<?php if ($loadOutfits) echo "<td>". t('common.outfit') ."</td>"; ?>
			<td><?= t('common.rank') ?></td>
			<td><?= t('common.name') ?></td>
			<td><?= t('common.vocation') ?></td>
			<td><?= t('common.level') ?></td>
			<?php if ($type === 7) echo "<td>". t('common.points') ."</td>"; ?>
		</tr>

		<?php
		if ($vocGroup[$type] === false) {
			?>
			<tr>
				<td colspan="5"><?= t('common.nothing') ?></td>
			</tr>
			<?php
		} else {
			for ($i = 0; $i < count($vocGroup[$type]); $i++) {
				if (pageCheck($i, $page, $rowsPerPage)) {
					$flag = ($loadFlags === true && strlen($vocGroup[$type][$i]['flag']) > 1) ? '<img src="' . $config['country_flags']['server'] . '/' . $vocGroup[$type][$i]['flag'] . '.png">  ' : '';
					?>
					<tr>
						<?php if ($loadOutfits): ?>
							<td class="outfitColumn"><img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $vocGroup[$type][$i]['type']; ?>&addons=<?php echo $vocGroup[$type][$i]['addons']; ?>&head=<?php echo $vocGroup[$type][$i]['head']; ?>&body=<?php echo $vocGroup[$type][$i]['body']; ?>&legs=<?php echo $vocGroup[$type][$i]['legs']; ?>&feet=<?php echo $vocGroup[$type][$i]['feet']; ?>" alt="img"></td>
						<?php endif; ?>
						<td><?php echo $i+1; ?></td>
						<td><?php echo $flag; ?><a href="characterprofile.php?name=<?php echo $vocGroup[$type][$i]['name']; ?>"><?php echo $vocGroup[$type][$i]['name']; ?></a></td>
						<td><?php echo vocation_id_to_name($vocGroup[$type][$i]['vocation']); ?></td>
						<td><?php echo $vocGroup[$type][$i]['value']; ?></td>
						<?php if ($type === 7) echo "<td>". $vocGroup[$type][$i]['experience'] ."</td>"; ?>
					</tr>
					<?php
				}
			}
		}
		?>
	</table>
	</div>

	</div><!-- .znx-acct -->
	<?php
}
