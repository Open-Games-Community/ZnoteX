<?php if ($emailRequired): ?>
	<h1><?= t('reg.email_required') ?></h1>
	<p><?= t('reg.activation_sent') ?></p>
	<p><?= t('acc.check_junk_spam') ?></p>
<?php else: ?>
	<?= t('reg.created') ?>
<?php endif; ?>
