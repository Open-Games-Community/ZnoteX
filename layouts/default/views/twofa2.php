<h1><?= t_default('twofa2.title', 'Two-factor authentication') ?></h1>

<?php if (empty($errors) === false): ?>
	<?= output_errors($errors) ?>
<?php endif; ?>

<h2><?= t_default('twofa2.app_title', 'Authenticator app') ?></h2>
<?php if ($status['totp_enabled']): ?>
	<p><?= t_default('twofa2.app_enabled', 'Enabled.') ?></p>
	<form method="post" data-confirm="Disable the authenticator app?">
		<?php Token::create(); ?>
		<button type="submit" name="tfa2_totp_disable" value="1"><?= t_default('twofa2.disable', 'Disable') ?></button>
	</form>
<?php else:
	$pendingRow = znote2fa_row($accountId);
	if (empty($pendingRow['totp_secret'])) {
		?>
		<form method="post">
			<?php Token::create(); ?>
			<button type="submit" name="tfa2_totp_start" value="1"><?= t_default('twofa2.app_start', 'Set up an authenticator app') ?></button>
		</form>
		<?php
	} else {
		?>
		<img
			src="<?php echo TokenAuth6238::getBarCodeUrl($user_data['name'], $_SERVER["HTTP_HOST"], $pendingRow['totp_secret'], preg_replace('/\s+/', '', $config['site_title'])); ?>"
			alt="Two-factor authentication QR code image for this account."
		/>
		<form method="post">
			<?php Token::create(); ?>
			<input type="text" name="tfa2_totp_code" placeholder="000000" autocomplete="one-time-code">
			<button type="submit" name="tfa2_totp_confirm" value="1"><?= t_default('twofa2.app_confirm', 'Confirm') ?></button>
		</form>
		<?php
	}
endif; ?>

<h2><?= t_default('twofa2.email_title', 'E-mail codes') ?></h2>
<?php if (znote2fa_v2_config()['email_otp_enabled']): ?>
	<p><?= t_default('twofa2.email_help', 'Receive a one-time code by e-mail whenever you log in and have no authenticator app configured.') ?></p>
	<form method="post">
		<?php Token::create(); ?>
		<label>
			<input type="checkbox" name="tfa2_email_enabled" value="1" <?= $status['email_otp_enabled'] ? 'checked' : '' ?>>
			<?= t_default('twofa2.email_enable', 'Enable e-mail codes') ?>
		</label>
		<button type="submit" name="tfa2_email_toggle" value="1"><?= t_default('twofa2.save', 'Save') ?></button>
	</form>
<?php else: ?>
	<p><?= t_default('twofa2.email_unavailable', 'E-mail codes are disabled by the site administrator.') ?></p>
<?php endif; ?>

<h2><?= t_default('twofa2.recovery_title', 'Recovery codes') ?></h2>
<?php if ($revealedRecoveryCodes): ?>
	<p><strong><?= t_default('twofa2.recovery_reveal', 'Save these somewhere safe. Each one works once and this is the only time they are shown.') ?></strong></p>
	<ul>
		<?php foreach ($revealedRecoveryCodes as $code): ?>
			<li><code><?= h($code) ?></code></li>
		<?php endforeach; ?>
	</ul>
<?php else: ?>
	<p><?= t_default('twofa2.recovery_remaining', '{n} unused recovery codes.', ['n' => $status['recovery_remaining']]) ?></p>
<?php endif; ?>
<form method="post" <?= $status['recovery_remaining'] > 0 ? 'data-confirm="Generating new codes invalidates the old ones. Continue?"' : '' ?>>
	<?php Token::create(); ?>
	<button type="submit" name="tfa2_recovery_generate" value="1"><?= t_default('twofa2.recovery_generate', 'Generate new recovery codes') ?></button>
</form>

<h2><?= t_default('twofa2.devices_title', 'Trusted devices') ?></h2>
<?php if ($devices): ?>
	<ul>
		<?php foreach ($devices as $device): ?>
			<li>
				<?= h($device['label']) ?> - <?= h($device['ip']) ?>
				(<?= t_default('twofa2.last_used', 'last used {when}', ['when' => getClock((int)$device['last_used_at'], true)]) ?>)
				<form method="post" style="display:inline;">
					<?php Token::create(); ?>
					<button type="submit" name="tfa2_device_revoke" value="<?= (int)$device['id'] ?>"><?= t_default('twofa2.revoke', 'Revoke') ?></button>
				</form>
			</li>
		<?php endforeach; ?>
	</ul>
<?php else: ?>
	<p><?= t_default('twofa2.no_devices', 'No trusted devices.') ?></p>
<?php endif; ?>

<h2><?= t_default('twofa2.logout_all_title', 'Log out everywhere') ?></h2>
<p><?= t_default('twofa2.logout_all_help', 'Forgets every trusted device and requires the two-factor code again on every session, including this one\'s next login.') ?></p>
<form method="post" data-confirm="Log out all devices and forget trusted devices?">
	<?php Token::create(); ?>
	<button type="submit" name="tfa2_logout_all" value="1"><?= t_default('twofa2.logout_all', 'Log out all devices') ?></button>
</form>
