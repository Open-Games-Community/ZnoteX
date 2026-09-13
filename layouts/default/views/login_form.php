<?php
?>
<?php if (empty($errors) === false): ?>
	<h2><?= t('login.failed_title') ?></h2>
	<?= output_errors($errors) ?>
<?php endif; ?>
<form class="loginForm" action="login.php" method="post">
	<ul>
		<li>
			<?= t('widget.login.username') ?><br>
			<input type="text" name="username" id="login_username">
		</li>
		<li>
			<?= t('widget.login.password') ?><br>
			<input type="password" name="password" id="login_password">
		</li>
		<?php if ($config['twoFactorAuthenticator']): ?>
			<li>
				<?= t('widget.login.token') ?><br>
				<input type="password" name="authcode">
			</li>
		<?php endif; ?>
		<?php Token::create(); ?>
		<li>
			<input type="submit" value="<?= t('widget.login.submit') ?>">
		</li>
	</ul>
</form>
