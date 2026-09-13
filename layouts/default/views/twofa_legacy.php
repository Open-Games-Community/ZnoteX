<h1><?= t('twofa.title') ?></h1>
<p><?= t('twofa.security') ?> <b><?php echo ($status) ? t('common.enabled') : t('common.disabled'); ?></b>.</p>

<?php if ($status === false): ?>
	<p><strong><?= t('twofa.login_to_activate') ?></strong></p>
<?php else: ?>
	<form method="post" data-confirm="<?= htmlspecialchars(t('twofa.disable_confirm'), ENT_QUOTES, 'UTF-8') ?>">
		<button type="submit" name="disable_2fa" value="1"><?= t('twofa.disable_btn') ?> <?= t('twofa.title') ?></button>
	</form>
<?php endif; ?>

<img
	src="<?php echo TokenAuth6238::getBarCodeUrl($user_data['name'], $_SERVER["HTTP_HOST"], $query['znote_secret'], preg_replace('/\s+/', '', $config['site_title'])); ?>"
	alt="<?= t('twofa.qr_alt', ['title' => t('twofa.title')]) ?>"
/>

<h2><?= t('twofa.howto') ?></h2>
<ol>
	<li><?= t('twofa.step_download', ['authy' => '<strong>Authy</strong> (<a target="_BLANK" href="https://play.google.com/store/apps/details?id=com.authy.authy">Android</a>), (<a target="_BLANK" href="https://itunes.apple.com/us/app/authy/id494168017">iPhone</a>)', 'google' => '<strong>' . t('twofa.google') . '</strong> (<a target="_BLANK" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2">Android</a>), (<a target="_BLANK" href="https://itunes.apple.com/us/app/google-authenticator/id388497605">iPhone</a>)']) ?></li>
	<li><?= t('twofa.step_scan') ?></li>
	<li><?= t('twofa.step_login', ['logout_link' => '<a href="logout.php">' . t('nav.logout') . '</a>', 'title' => t('twofa.title')]) ?></li>
</ol>
