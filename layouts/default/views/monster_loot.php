<?php
/**
 * Monster loot checker.
 *
 * Prepared by monster_loot.php:
 *   $monsterLootError  message when an XML file could not be read, else ''
 *   $itemList          [item id => item name]
 *   $rarity            [label => minimum percent], highest first
 *   $monsterList       name / state ('loot','empty','failed') / file / loot rows
 *
 * Nothing is read from disk here.
 */

/** The rarity label for a drop chance. */
function znote_loot_rarity(float $chance, array $rarity): string {
	foreach ($rarity as $label => $percent) {
		if ($chance >= $percent) {
			return $label;
		}
	}
	return '';
}

$lootParam = isset($_GET['lootrate']) ? '&lootrate' : '';
?>
<script>
	function toggleVisibility(obj) {
		var el = document.getElementById('d' + obj.id);
		var name = obj.innerHTML.substring(4);

		if (el.style.display == 'none') {
			obj.innerHTML = '[ -]';
			el.style.display = 'block';
		} else {
			obj.innerHTML = '[+]';
			el.style.display = 'none';
		}
		obj.innerHTML += ' ' + name;
	}
</script>

<p>
	<a href="monster_loot.php<?= $lootParam ? '?lootrate' : '' ?>">Hide None</a> |
	<a href="?hidefail<?= $lootParam ?>">Hide Not Found</a> |
	<a href="?hideempty<?= $lootParam ?>">Hide Monsters Without Loot</a> |
	<a href="?hideempty&amp;hidefail<?= $lootParam ?>">Hide All</a> |
	<a href="monster_loot.php">Use Normal Loot Rate</a> |
	<a href="?lootrate">Use Server Loot Rate</a>
</p>

<?php if ($monsterLootError !== ''): ?>

	<b><?= htmlspecialchars($monsterLootError, ENT_QUOTES, 'UTF-8') ?></b>

<?php else: ?>

	<?php $i = 0; foreach ($monsterList as $monster): $i++; ?>

		<?php if ($monster['state'] === 'loot'): ?>

			<a id="<?= $i ?>" href="javascript:void(0);" onclick="toggleVisibility(this)"
			   style="text-decoration:none; font:bold 14px verdana; color:orange;">
				[+] <?= htmlspecialchars($monster['name'], ENT_QUOTES, 'UTF-8') ?>
			</a>
			<br>
			<div id="d<?= $i ?>" style="display:none;"><br>
				<?php foreach ($monster['loot'] as $drop): ?>
					<?= str_repeat('... ', $drop['level']) ?>
					<u><?= $drop['count'] ?: 1 ?></u>
					<span style="color:#7878FF; font-weight:bold;"><?= htmlspecialchars($itemList[$drop['id']] ?? '?', ENT_QUOTES, 'UTF-8') ?></span>
					- <span style="color:#C45; font-weight:bold;"><?= htmlspecialchars(znote_loot_rarity($drop['chance'], $rarity), ENT_QUOTES, 'UTF-8') ?></span>
					(<span style="color:#FF9A9A;"><?= $drop['chance'] ?>%</span>)<br>
				<?php endforeach; ?>
				<br>
			</div>

		<?php elseif ($monster['state'] === 'empty' && !isset($_GET['hideempty'])): ?>

			<span style="font:bold 14px verdana; color:red;">[x] <?= htmlspecialchars($monster['name'], ENT_QUOTES, 'UTF-8') ?></span><br>

		<?php elseif ($monster['state'] === 'failed' && !isset($_GET['hidefail'])): ?>

			<span>Failed to load monster <b><?= htmlspecialchars($monster['name'], ENT_QUOTES, 'UTF-8') ?></b>
			<i>(<?= htmlspecialchars($monster['file'], ENT_QUOTES, 'UTF-8') ?>)</i></span><br>

		<?php endif; ?>

	<?php endforeach; ?>

<?php endif; ?>
