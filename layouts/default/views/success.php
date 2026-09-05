<?php ?>
<h1><?= t('success.title') ?></h1>
<?php if (in_array(($_GET['provider'] ?? ''), array('stripe', 'mercadopago'), true)): ?>
	<p>Your payment is being verified. Shop points are added automatically when the secure payment webhook confirms it.</p>
<?php endif; ?>
<?= t('success.go') ?> <script> document.write('<a href="' + document.referrer + '"><?= t('success.back') ?></a>'); </script>
