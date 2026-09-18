<?php ?>

<div class="znx-acct">

<div class="znx-acct-head"><?= t('downloads.title') ?></div>

<?php foreach (znote_download_entries_by_section() as $section => $entries): ?>
	<div class="znx-acct-head"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $section)), ENT_QUOTES, 'UTF-8') ?></div>
	<div class="znx-dl-grid">
		<?php foreach ($entries as $entry): ?>
			<div class="znx-dl-card">
				<?php if ($entry['image'] !== ''): ?>
					<img src="<?= htmlspecialchars($entry['image'], ENT_QUOTES, 'UTF-8') ?>" alt="">
				<?php endif; ?>
				<span class="znx-dl-card__label"><?= htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') ?></span>
				<?php if ($entry['description'] !== ''): ?>
					<span class="znx-dl-card__desc"><?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?></span>
				<?php endif; ?>
				<a href="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') ?>" class="znx-dl-card__btn"><?= t('downloads.download') ?></a>
			</div>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>

<div class="znx-acct-head"><?= t('downloads.howto') ?></div>
<div class="znx-editchar-box">
	<ol class="znx-dl-steps">
		<li>
			<a href="<?php echo $config['client_download']; ?>"><?= t('downloads.download') ?></a> <?= t('downloads.step1') ?>
		</li>
		<li>
			<?= t('downloads.step5') ?> <strong>Apply</strong>.
		</li>
	</ol>
	<div class="znx-dl-register">
		<span><?= t('downloads.step5b') ?></span>
		<a href="register.php" class="znx-dl-card__btn"><?= t_default('downloads.register_cta', 'Register Account') ?></a>
	</div>
</div>

</div><!-- .znx-acct -->
