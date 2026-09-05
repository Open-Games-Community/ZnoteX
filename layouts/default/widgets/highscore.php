<div class="well widget">
	<div class="header">
		<?= t('widget.highscores.title') ?>
	</div>
	<div class="body">
		<form action="highscores.php" method="get">
			<select name="type">
				<option value="7"><?= t('skill.experience') ?></option>
				<option value="5"><?= t('skill.shielding') ?></option>
				<option value="3"><?= t('skill.axe') ?></option>
				<option value="2"><?= t('skill.sword') ?></option>
				<option value="1"><?= t('skill.club') ?></option>
				<option value="4"><?= t('skill.distance') ?></option>
				<option value="9"><?= t('skill.fist') ?></option>
				<option value="6"><?= t('skill.fishing') ?></option>
				<option value="8"><?= t('skill.magic') ?></option>
			</select>
			<input type="submit" value="<?= t('widget.highscores.submit') ?>">
		</form>
	</div>
</div>