<?php if ($ok): ?>
	<h1><?= t('common.congrats') ?></h1> <p><?= t('reg.created') ?></p>
<?php else: ?>
	<h1><?= t('acc.auth_failed') ?></h1> <p><?= t('acc.auth_failed_text') ?></p>
<?php endif; ?>
