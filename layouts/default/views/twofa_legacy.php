<h1><?= t('twofa.title') ?></h1>
<p><?= t('twofa.security') ?> <b><?php echo ($status) ? 'Enabled' : 'Disabled'; ?></b>.</p>

<?php if ($status === false): ?>
	<p><strong>Login with a token generated from this QR code to activate:</strong></p>
<?php else: ?>
	<form method="post" data-confirm="Disable two-factor authentication?">
		<button type="submit" name="disable_2fa" value="1">Disable <?= t('twofa.title') ?></button>
	</form>
<?php endif; ?>

<img
	src="<?php echo TokenAuth6238::getBarCodeUrl($user_data['name'], $_SERVER["HTTP_HOST"], $query['znote_secret'], preg_replace('/\s+/', '', $config['site_title'])); ?>"
	alt="<?= t('twofa.title') ?> QR code image for this account."
/>

<h2><?= t('twofa.howto') ?></h2>
<ol>
	<li>Download an authenticator app for free on your mobile phone like <strong>Authy</strong> (<a target="_BLANK" href="https://play.google.com/store/apps/details?id=com.authy.authy">Android</a>), (<a target="_BLANK" href="https://itunes.apple.com/us/app/authy/id494168017">iPhone</a>) or <strong><?= t('twofa.google') ?></strong> (<a target="_BLANK" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2">Android</a>), (<a target="_BLANK" href="https://itunes.apple.com/us/app/google-authenticator/id388497605">iPhone</a>).</li>
	<li>Scan the QR image with the app on your phone to create a Two-Factor account for this server.</li>
	<li><a href="logout.php">Logout</a>, then login with username, password and token generated from your phone to enable <?= t('twofa.title') ?>.</li>
</ol>
