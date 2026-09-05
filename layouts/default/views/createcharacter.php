<?php
/**
 * Create character.
 *
 * Prepared by createcharacter.php:
 *   $formState  'success' | 'errors' | 'form'
 *   $errors     array of messages, when $formState is 'errors'
 *
 * The character creation happens in createcharacter.php, never here.
 */
?>
<h1><?= t('createchar.title') ?></h1>

<?php if ($formState === 'success'): ?>

	<?= t('createchar.success') ?>

<?php else: ?>

	<?php if ($formState === 'errors'): ?>
		<font color="red"><b><?= output_errors($errors) ?></b></font>
	<?php endif; ?>

	<form action="" method="post">
		<ul>
			<li>
				<?= t('createchar.name') ?><br>
				<input type="text" name="name">
			</li>
			<li>
				<!-- Available vocations to select from when creating character -->
				<?= t('createchar.vocation') ?><br>
				<select name="selected_vocation">
				<?php foreach ($config['available_vocations'] as $id) { ?>
				<option value="<?php echo $id; ?>"><?php echo vocation_id_to_name($id); ?></option>
				<?php } ?>
				</select>
			</li>
			<li>
				<!-- Available genders to select from when creating character -->
				<?= t('createchar.gender') ?><br>
				<select name="selected_gender">
				<option value="1"><?= t('createchar.male') ?></option>
				<option value="0"><?= t('createchar.female') ?></option>
				</select>
			</li>
			<?php
			$available_towns = $config['available_towns'];
			if (count($available_towns) > 1):
				?>
				<li>
					<!-- Available towns to select from when creating character -->
					<?= t('createchar.town') ?><br>
					<select name="selected_town">
						<?php
						foreach ($available_towns as $tid):
							?>
							<option value="<?php echo $tid; ?>"><?php echo town_id_to_name($tid); ?></option>
							<?php
						endforeach;
						?>
					</select>
				</li>
				<?php
			else:
				?>
				<input type="hidden" name="selected_town" value="<?php echo end($available_towns); ?>">
				<?php
			endif;

			/* Form file */
			Token::create();
			?>
			<li>
				<input type="submit" value="<?= t('createchar.title') ?>">
			</li>
		</ul>
	</form>
<?php endif; ?>
