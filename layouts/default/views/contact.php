<?php ?>

<div class="znx-acct">

<div class="znx-acct-head"><?= t('contact.title') ?></div>
<?php $contactInfo = trim((string)($config['contact_info'] ?? '')); ?>
<?php if ($contactInfo !== ''): ?>
	<div class="znx-acct-info"><?= nl2br(htmlspecialchars($contactInfo, ENT_QUOTES, 'UTF-8')) ?></div>
<?php else: ?>
	<div class="znx-empty-box"><?= t('contact.text') ?></div>
<?php endif; ?>

</div><!-- .znx-acct -->
