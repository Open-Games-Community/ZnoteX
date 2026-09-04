<?php
/**
 * Public changelog.
 *
 * Prepared by changelog.php: $changelogs - newest first, or an empty array.
 */
?>
<h1>Changelog</h1>

<?php if ($changelogs): ?>
	<table id="changelogTable">
		<tr class="yellow">
			<td>Changelogs</td>
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
	<h2>Currently no change logs submitted.</h2>
<?php endif; ?>
