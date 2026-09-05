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
<h1><?= t('changepw.title') ?></h1>

<?php if ($formState === 'success'): ?>

	<?= t('changepw.success') ?><br>
	<?= t('changepw.relogin') ?>

<?php else: ?>

	<?php if ($formState === 'errors'): ?>
		<font color="red"><b><?= output_errors($errors) ?></b></font>
	<?php endif; ?>

	<form action="" method="post">
		<ul>
			<li>
				<?= t('changepw.current') ?><br>
				<input type="password" name="current_password">
			</li>
			<li>
				<?= t('changepw.new') ?><br>
				<input type="password" name="new_password">
			</li>
			<li>
				<?= t('changepw.new_again') ?><br>
				<input type="password" name="new_password_again">
			</li>
			<?php Token::create(); ?>
			<li>
				<input type="submit" value="<?= t('changepw.title') ?>">
			</li>
		</ul>
	</form>

<?php endif; ?>
