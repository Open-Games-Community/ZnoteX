<?php
/**
 * Account settings.
 *
 * Prepared by settings.php:
 *   $formState  'success' | 'errors' | 'form'
 *   $errors     array of messages, when $formState is 'errors'
 *
 * The account write happens in settings.php, never here.
 */
?>
<div class="znx-acct">

<div class="znx-acct-head"><?= t('settings.title') ?></div>

<?php if ($formState === 'success'): ?>

	<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--ok"><?= t('settings.success') ?></div>

<?php else: ?>

	<?php if ($formState === 'errors'): ?>
		<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--error"><?= output_errors($errors) ?></div>
	<?php endif; ?>

	<form action="" method="post" class="znx-editchar-box">
		<div class="znx-settings-field">
			<label><?= t('settings.email') ?></label>
			<input type="text" name="new_email" value="<?php echo $user_data['email']; ?>" class="znx-acct-select">
		</div>
		<div class="znx-settings-field">
			<label><?= t('settings.country') ?></label>
			<select name="new_flag" id="flag_select" class="znx-acct-select">
				<option value=""><?= t('settings.choose') ?></option>
				<?php
				foreach(array('pl', 'se', 'br', 'us', 'gb', ) as $c)
					echo '<option value="' . $c . '">' . $config['countries'][$c] . '</option>';

					echo '<option value="">----------</option>';
					foreach($config['countries'] as $code => $c)
						echo '<option value="' . $code . '"' . (isset($user_znote_data['flag']) && $user_znote_data['flag'] == $code ? ' selected' : '') . '>' . $c . '</option>';
				?>
			</select>
		</div>
		<?php Token::create(); ?>
		<button type="submit" class="znx-acct-btn"><?= t('settings.submit') ?></button>
	</form>

<?php endif; ?>

</div><!-- .znx-acct -->
