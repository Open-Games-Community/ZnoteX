<div class="well widget">
	<div class="header">
		<?= t('widget.houses.title') ?>
	</div>
	<div class="body">
		<form action="houses.php" method="get">
			<select name="id">
				<?php
				foreach ($config['towns'] as $id => $name)
					echo '<option value="'. $id .'">'. $name .'</option>';
				?>
			</select>
			<input type="submit" value="<?= t('widget.houses.submit') ?>">
		</form>
	</div>
</div>
