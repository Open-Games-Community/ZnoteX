<?php ?>

<h1><?= t('contact.title') ?></h1>
<?php $contactInfo = trim((string)($config['contact_info'] ?? '')); ?>
<?php if ($contactInfo !== ''): ?>
	<p><?= nl2br(htmlspecialchars($contactInfo, ENT_QUOTES, 'UTF-8')) ?></p>
<?php else: ?>
	<p><?= t('contact.text') ?></p>
<?php endif; ?>
