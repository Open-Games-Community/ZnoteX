<?php
/**
 * Change password.
 *
 * Prepared by changepassword.php:
 *   $formState  'success' | 'errors' | 'form'
 *   $errors     array of messages, when $formState is 'errors'
 *
 * The password write happens in changepassword.php, never here.
 */
?>
<div class="znx-acct">

<div class="znx-acct-head"><?= t('changepw.title') ?></div>

<?php if ($formState === 'success'): ?>

	<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--ok"><?= t('changepw.success') ?><br><?= t('changepw.relogin') ?></div>

<?php else: ?>

	<?php if ($formState === 'errors'): ?>
		<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--error"><?= output_errors($errors) ?></div>
	<?php endif; ?>

	<form action="" method="post" class="znx-editchar-box">
		<div class="znx-settings-field">
			<label><?= t('changepw.current') ?></label>
			<input type="password" name="current_password" class="znx-acct-select">
		</div>
		<div class="znx-settings-field">
			<label><?= t('changepw.new') ?></label>
			<input type="password" name="new_password" class="znx-acct-select">
		</div>
		<div class="znx-settings-field">
			<label><?= t('changepw.new_again') ?></label>
			<input type="password" name="new_password_again" class="znx-acct-select">
		</div>
		<?php Token::create(); ?>
		<button type="submit" class="znx-acct-btn"><?= t('changepw.title') ?></button>
	</form>

<?php endif; ?>

</div><!-- .znx-acct -->
