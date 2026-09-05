<?php
/**
 * Public changelog.
 *
 * Prepared by changelog.php: $changelogs - newest first, or an empty array.
 */
?>
<h1><?= t('changelog.title') ?></h1>

<?php if ($changelogs): ?>
	<table id="changelogTable">
		<tr class="yellow">
			<td><?= t('changelog.header') ?></td>
		</tr>
		<?php foreach ($changelogs as $changelog): ?>
			<tr>
				<td>
					<b><?= htmlspecialchars(getClock((int)($changelog['time'] ?? 0), true, true), ENT_QUOTES, 'UTF-8') ?></b><br>
					<?= znote_bbcode_raw($changelog['text']) ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php else: ?>
	<h2><?= t('changelog.none') ?></h2>
<?php endif; ?>
