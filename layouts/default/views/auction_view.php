<?php
?>
<style>
.auc2-detail-header { display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; margin-bottom: 10px; }
.auc2-detail-outfit { flex: 0 0 auto; }
.auc2-detail-data { flex: 1 1 220px; font-size: 12px; line-height: 1.6; }
.auc2-detail-data .auc2-label { color: #666; }
.auc2-detail-bid { flex: 0 0 200px; border-left: 1px solid #ccc; padding-left: 14px; }
.auc2-coin { width: 14px; height: 14px; vertical-align: middle; margin-left: 2px; }
.auc2-amount { white-space: nowrap; }
.auc2-section { margin-top: 18px; }
.auc2-section h3 { font-size: 13px; margin: 0 0 8px; }
.auc2-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 12px; max-width: 520px; }
.auc2-stats-grid > div { display: flex; justify-content: space-between; align-items: center; gap: 8px; border-bottom: 1px dotted #ccc; padding: 3px 0; }
.auc2-skill-value { flex: 0 0 auto; white-space: nowrap; font-variant-numeric: tabular-nums; }
.auc2-item-search { margin: 10px 0; }
.auc2-item-grid { display: grid; grid-template-columns: repeat(auto-fill, 56px); gap: 8px; }
.auc2-item-grid-tile { position: relative; width: 56px; height: 56px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; }
.auc2-item-grid-tile img { max-width: 40px; max-height: 40px; }
.auc2-item-grid-tile .auc2-item-count { position: absolute; bottom: 2px; right: 2px; font-size: 10px; background: rgba(0,0,0,.6); color: #fff; padding: 0 3px; }
.auc2-item-grid-tile[hidden] { display: none; }
</style>

<div class="auc2-detail-header">
	<div class="auc2-detail-outfit">
		<?php if ($loadOutfits): ?>
			<img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $character['type']; ?>&addons=<?php echo $character['addons']; ?>&head=<?php echo $character['head']; ?>&body=<?php echo $character['body']; ?>&legs=<?php echo $character['legs']; ?>&feet=<?php echo $character['feet']; ?>" alt="">
		<?php endif; ?>
	</div>
	<div class="auc2-detail-data">
		<div><?= t('common.level') ?>: <?= (int)$character['level'] ?> | <?= t('common.vocation') ?>: <?= h(vocation_id_to_name($character['vocation'])) ?> | <?= ((int)$character['sex'] === 1) ? t('common.male') : t('common.female') ?></div>
		<?php if ((int)$character['own'] === 1): ?>
			<div><span class="auc2-label"><?= t('common.name_label') ?></span> <a href="/characterprofile.php?name=<?php echo $character['name']; ?>"><?php echo $character['name']; ?></a></div>
			<div><span class="auc2-label"><?= t('auc.price') ?>:</span> <span class="auc2-amount"><?= (int)$character['price'] ?><img class="auc2-coin" src="<?= theme_asset('icon-tibiacoin.png') ?>" alt=""></span></div>
			<div><span class="auc2-label"><?= t('common.bid_label') ?></span> <?= (int)$character['bid'] ?></div>
			<div><span class="auc2-label"><?= t('auc.deposit') ?></span> <?= (int)$character['deposit'] ?></div>
		<?php else: ?>
			<div><span class="auc2-label"><?= t_default('auc.start', 'Auction Start:') ?></span> <?= h(getClock($character['time_begin'], true)) ?></div>
			<div><span class="auc2-label"><?= t_default('auc.end', 'Auction End:') ?></span> <?= h(getClock($character['time_end'], true)) ?></div>
			<div>
				<?php if ($bidding_period): ?>
					<span class="auc2-label"><?= t_default('auc.remaining_short', 'Remaining:') ?></span>
					<span data-auc2-countdown="<?= (int)$character['time_end'] ?>" data-auc2-ended-text="<?= h(t_default('auc.instant', 'Instant')) ?>"><?= h(toDuration((int)$character['time_end'] - time())) ?></span>
				<?php else: ?>
					<span class="auc2-label"><?= t_default('auc.type_label', 'Type:') ?></span> <?= t_default('auc.instant', 'Instant') ?>
				<?php endif; ?>
			</div>
			<div><span class="auc2-label"><?= t_default('auc.min_bid', 'Minimum Bid:') ?></span> <span class="auc2-amount"><?= (int)$character['price'] ?><img class="auc2-coin" src="<?= theme_asset('icon-tibiacoin.png') ?>" alt=""></span></div>
		<?php endif; ?>
	</div>
	<div class="auc2-detail-bid">
		<?php if ((int)$character['own'] === 1): ?>
			<?php if (!$bidding_period): ?>
				<p><?= t('auc.bidding_ended') ?></p>
				<form action="/auctionChar.php" method="POST">
					<input type="hidden" name="action" value="refund">
					<input type="hidden" name="zaid" value="<?php echo $character['zaid']; ?>">
					<input type="submit" value="<?= t('auc.reclaim_btn') ?>">
				</form>
			<?php else: ?>
				<p><?= t('auc.bid_period') ?> <?php echo toDuration($character['time_end']-time()); ?>. <?= t('auc.reclaim_after_period') ?></p>
			<?php endif; ?>
		<?php else: ?>
			<?php if (is_array($account) && !empty($account)): ?>
				<p><?= t('common.you_have') ?> <strong><?php echo $account['points']; ?></strong> <?= t('auc.points_remaining') ?></p>
				<?php if ((int)$character['bidder_account_id'] === $this_account_id): ?>
					<p><strong><?= t('auc.so_far_good') ?></strong>
						<br><?= t('auc.highest_bid') ?> <?php echo (int)$character['price']-$step; ?>
					</p>
					<p><?= t('auc.yours_in') ?>
						<br><?php echo toDuration((int)$character['time_end']-time()); ?>.
					</p>
				<?php endif; ?>
				<form action="/auctionChar.php" method="POST">
					<input type="hidden" name="action" value="bid">
					<input type="hidden" name="zaid" value="<?php echo $character['zaid']; ?>">
					<input type="number" name="price" min="<?php echo $character['price']; ?>" max="<?php echo $account['points']; ?>" step="5" value="<?php echo $character['price']; ?>" <?php if (!$bidding_period) echo 'disabled'; ?>>
					<?php if (!$bidding_period): /* Because above input is disabled */ ?>
						<input type="hidden" name="price" value="<?php echo $character['price']; ?>">
					<?php endif; ?>
					<input type="submit" value="<?php echo ($bidding_period) ? t('auc.bid_btn') : t('auc.buy_btn'); ?>">
				</form>
			<?php else: ?>
				<?php if ((int)$character['bidder_account_id'] === $this_account_id): ?>
					<p><strong><?= t('auc.so_far_good') ?></strong>
						<br><?= t('auc.highest_bid') ?> <?php echo (int)$character['price']-$step; ?>
					</p>
					<p><?= t('auc.yours_in') ?>
						<br><?php echo toDuration((int)$character['time_end']-time()); ?>.
					</p>
				<?php else: ?>
					<p><?= t('auc.cannot_afford') ?></p>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php
$auc2Skills = array(
	array(t('char.magic_level'), (int)$character['magic']),
	array(t('skill.fist'), (int)$character['fist']),
	array(t('skill.club'), (int)$character['club']),
	array(t('skill.sword'), (int)$character['sword']),
	array(t('skill.axe'), (int)$character['axe']),
	array(t('skill.distance'), (int)$character['dist']),
	array(t('skill.shielding'), (int)$character['shielding']),
	array(t('skill.fishing'), (int)$character['fishing']),
);
?>
<div class="auc2-section">
	<h3><?= t_default('auc.tab_general', 'General') ?></h3>
	<div class="auc2-stats-grid">
		<div><span><?= t('common.level') ?></span><span class="auc2-skill-value"><?= (int)$character['level'] ?></span></div>
		<?php foreach ($auc2Skills as $auc2Skill): [$auc2SkillLabel, $auc2SkillValue] = $auc2Skill; ?>
			<div>
				<span><?= h($auc2SkillLabel) ?></span>
				<span class="auc2-skill-value"><?= $auc2SkillValue ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<?php
$auc2AllItems = array();
foreach (($player_items ?: array()) as $item) { $auc2AllItems[] = $item; }
foreach (($depot_items ?: array()) as $item) { $auc2AllItems[] = $item; }
?>
<?php if (!empty($auc2AllItems)): ?>
	<div class="auc2-section">
		<h3><?= t('auc.player_items') ?> &amp; <?= t('auc.depot_items') ?></h3>
		<input type="text" class="auc2-item-search" id="auc2ItemSearch" placeholder="<?= h(t_default('auc.item_search_placeholder', 'Search item...')) ?>">
		<div class="auc2-item-grid">
			<?php foreach ($auc2AllItems as $item): ?>
				<div class="auc2-item-grid-tile" data-auc2-item-name="<?= h(strtolower((string)($items[$item['itemtype']] ?? ''))) ?>" title="<?= h(($items[$item['itemtype']] ?? ('#' . $item['itemtype'])) . ' (' . (int)$item['count'] . ')') ?>">
					<img src="<?= h(znote_item_image_url((int)$item['itemtype'])) ?>" alt="">
					<?php if ((int)$item['count'] > 1): ?><span class="auc2-item-count"><?= (int)$item['count'] ?></span><?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>

<p><a href="/auctionChar.php"><?= t('auc.go_back_list') ?></a></p>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var search = document.getElementById('auc2ItemSearch');
	if (search) {
		search.addEventListener('keyup', function () {
			var needle = search.value.trim().toLowerCase();
			document.querySelectorAll('.auc2-item-grid-tile').forEach(function (tile) {
				var name = tile.getAttribute('data-auc2-item-name') || '';
				tile.hidden = needle !== '' && name.indexOf(needle) === -1;
			});
		});
	}

	// Auction timers tick down live client-side instead of only updating on
	// the next full page reload.
	document.querySelectorAll('[data-auc2-countdown]').forEach(function (el) {
		var end = parseInt(el.getAttribute('data-auc2-countdown'), 10);
		function tick() {
			var remaining = end - Math.floor(Date.now() / 1000);
			if (remaining <= 0) {
				el.textContent = el.getAttribute('data-auc2-ended-text') || '';
				return;
			}
			var d = Math.floor(remaining / 86400);
			var h = Math.floor((remaining % 86400) / 3600);
			var m = Math.floor((remaining % 3600) / 60);
			var s = remaining % 60;
			var parts = [];
			if (d > 0) parts.push(d + (d === 1 ? ' day' : ' days'));
			if (d > 0 || h > 0) parts.push(h + (h === 1 ? ' hour' : ' hours'));
			if (m > 0) parts.push(m + (m === 1 ? ' minute' : ' minutes'));
			if (s > 0) parts.push(s + (s === 1 ? ' second' : ' seconds'));
			el.textContent = parts.join(', ');
			setTimeout(tick, 1000);
		}
		tick();
	});
});
</script>
