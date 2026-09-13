<?php if ($emailRequired): ?>
	<h1><?= t('reg.email_required') ?></h1>
	<p>We have sent you an email with an activation link to your submitted email address.</p>
	<p>If you can't find the email within 5 minutes, check your <strong>junk/trash inbox (spam filter)</strong> as it may be mislocated there.</p>
<?php else: ?>
	<?= t('reg.created') ?>
<?php endif; ?>
