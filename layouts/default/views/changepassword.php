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
<h1>Change Password:</h1>

<?php if ($formState === 'success'): ?>

	Your password has been changed.<br>
	You will need to login again with the new password.

<?php else: ?>

	<?php if ($formState === 'errors'): ?>
		<font color="red"><b><?= output_errors($errors) ?></b></font>
	<?php endif; ?>

	<form action="" method="post">
		<ul>
			<li>
				Current password:<br>
				<input type="password" name="current_password">
			</li>
			<li>
				New password:<br>
				<input type="password" name="new_password">
			</li>
			<li>
				New password again:<br>
				<input type="password" name="new_password_again">
			</li>
			<?php Token::create(); ?>
			<li>
				<input type="submit" value="Change password">
			</li>
		</ul>
	</form>

<?php endif; ?>
