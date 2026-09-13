<?php
?>
<h2><?= t('twofa.title') ?></h2>
<?php if (empty($errors) === false): ?>
	<?= output_errors($errors) ?>
<?php endif; ?>
<p>
	<?php if ($pendingStatus['totp_enabled']): ?>
		<?= t_default('twofa2.enter_app_code', 'Enter the code from your authenticator app, or a recovery code.') ?>
	<?php else: ?>
		<?= t_default('twofa2.enter_email_code', 'We emailed you a verification code. Enter it below, or use a recovery code.') ?>
	<?php endif; ?>
</p>
<form class="loginForm" method="post" action="login.php">
	<ul>
		<li>
			<input type="text" name="tfa2_code" autocomplete="one-time-code" autofocus>
		</li>
		<?php if ((int)znote2fa_v2_config()['trusted_device_days'] > 0): ?>
			<li>
				<label><input type="checkbox" name="tfa2_trust" value="1"> <?= t_default('twofa2.trust_device', 'Remember this device') ?></label>
			</li>
		<?php endif; ?>
		<input type="hidden" name="tfa2_email_sent" value="1">
		<?php Token::create(); ?>
		<li>
			<input type="submit" value="<?= t('widget.login.submit') ?>">
		</li>
	</ul>
</form>
