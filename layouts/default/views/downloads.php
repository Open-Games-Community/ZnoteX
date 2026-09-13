<?php ?>

<h1><?= t('downloads.title') ?></h1>
<p><?= t('downloads.intro') ?></p>

<p><?= t('downloads.ipchanger') ?> <a href="https://github.com/jo3bingham/tibia-ip-changer/releases/latest"><?= t('downloads.here') ?></a>.</p>

<?php foreach (znote_download_entries_by_section() as $section => $entries): ?>
	<h2><?= htmlspecialchars(ucwords(str_replace('_', ' ', $section)), ENT_QUOTES, 'UTF-8') ?></h2>
	<ul>
		<?php foreach ($entries as $entry): ?>
			<li>
				<?php if ($entry['image'] !== ''): ?>
					<img src="<?= htmlspecialchars($entry['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-width:96px;max-height:96px;display:block;">
				<?php endif; ?>
				<a href="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') ?></a>
				<?php if ($entry['description'] !== ''): ?>
					<br><span><?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endforeach; ?>

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
