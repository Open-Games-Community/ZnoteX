<div class="well loginContainer widget" id="loginContainer">
	<div class="header">
		<?= t('widget.login.title') ?>
	</div>
	<div class="body">
		<form class="loginForm" action="login.php" method="post">
			<div class="well">
				<label for="login_username"><?= t('widget.login.username') ?></label> <input type="text" name="username" id="login_username">
			</div>
			<div class="well">
				<label for="login_password"><?= t('widget.login.password') ?></label> <input type="password" name="password" id="login_password">
			</div>
			<?php if ($config['twoFactorAuthenticator']): ?>
				<div class="well">
					<label for="login_authcode"><?= t('widget.login.token') ?></label> <input type="password" name="authcode">
				</div>
			<?php endif; ?>
			<div class="well">
				<input type="submit" value="<?= t('widget.login.submit') ?>" class="submitButton">
			</div>
			<?php
				/* Form file */
				Token::create();
			?>
			<center>
				<h3><a href="register.php"><?= t('widget.login.new_account') ?></a></h3>
				<p><?= t('widget.login.lost') ?></p>
			</center>
		</form>
	</div>
</div>
