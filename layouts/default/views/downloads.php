<?php ?>

<h1><?= t('downloads.title') ?></h1>
<p><?= t('downloads.intro') ?></p>

<p><?= t('downloads.ipchanger') ?> <a href="https://github.com/jo3bingham/tibia-ip-changer/releases/latest"><?= t('downloads.here') ?></a>.</p>
<p><?= t('downloads.win', ['version' => ($config['client'] / 100)]) ?> <a href="<?php echo $config['client_download']; ?>"><?= t('downloads.here') ?></a>.</p>
<p><?= t('downloads.linux', ['version' => ($config['client'] / 100)]) ?> <a href="<?php echo $config['client_download_linux']; ?>"><?= t('downloads.here') ?></a>.</p>

<h2><?= t('downloads.howto') ?></h2>
<ol>
	<li>
		<a href="<?php echo $config['client_download']; ?>"><?= t('downloads.download') ?></a> <?= t('downloads.step1') ?>
	</li>
	<li>
		<a href="https://github.com/jo3bingham/tibia-ip-changer/releases/latest"><?= t('downloads.download') ?></a> <?= t('downloads.step2') ?>
	</li>
	<li>
		<?= t('downloads.step3') ?>
	</li>
	<li>
		<?= t('downloads.step4') ?> <?php echo $_SERVER['SERVER_NAME']; ?>
	</li>
	<li>
		<?= t('downloads.step5') ?> <strong>Apply</strong>.<br>
		<?= t('downloads.step5b') ?> <a href="register.php"><?= t('downloads.here') ?></a>.
	</li>
</ol>
