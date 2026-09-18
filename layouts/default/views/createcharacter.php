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
<div class="znx-acct">

<div class="znx-acct-head"><?= t('createchar.title') ?></div>

<?php if ($formState === 'success'): ?>

	<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--ok"><?= t('createchar.success') ?></div>

<?php else: ?>

	<?php if ($formState === 'errors'): ?>
		<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--error"><?= output_errors($errors) ?></div>
	<?php endif; ?>

	<form action="" method="post" class="znx-editchar-box">
		<div class="znx-settings-field">
			<label><?= t('createchar.name') ?></label>
			<input type="text" name="name" class="znx-acct-select">
		</div>
		<div class="znx-settings-field">
			<!-- Available vocations to select from when creating character -->
			<label><?= t('createchar.vocation') ?></label>
			<select name="selected_vocation" class="znx-acct-select">
			<?php foreach ($config['available_vocations'] as $id) { ?>
			<option value="<?php echo $id; ?>"><?php echo vocation_id_to_name($id); ?></option>
			<?php } ?>
			</select>
		</div>
		<div class="znx-settings-field">
			<!-- Available genders to select from when creating character -->
			<label><?= t('createchar.gender') ?></label>
			<select name="selected_gender" class="znx-acct-select">
			<option value="1"><?= t('createchar.male') ?></option>
			<option value="0"><?= t('createchar.female') ?></option>
			</select>
		</div>
		<?php
		$available_towns = $config['available_towns'];
		if (count($available_towns) > 1):
			?>
			<div class="znx-settings-field">
				<!-- Available towns to select from when creating character -->
				<label><?= t('createchar.town') ?></label>
				<select name="selected_town" class="znx-acct-select">
					<?php
					foreach ($available_towns as $tid):
						?>
						<option value="<?php echo $tid; ?>"><?php echo town_id_to_name($tid); ?></option>
						<?php
					endforeach;
					?>
				</select>
			</div>
			<?php
		else:
			?>
			<input type="hidden" name="selected_town" value="<?php echo end($available_towns); ?>">
			<?php
		endif;

		/* Form file */
		Token::create();
		?>
		<button type="submit" class="znx-acct-btn"><?= t('createchar.title') ?></button>
	</form>
<?php endif; ?>

</div><!-- .znx-acct -->
