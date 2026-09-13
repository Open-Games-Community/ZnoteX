<?php
?>
<style>
.auc2-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 15px; }
.auc2-filters .auc2-field { display: flex; flex-direction: column; gap: 3px; font-size: 12px; }
.auc2-filters input, .auc2-filters select { padding: 4px 6px; }
.auc2-results-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 13px; }
.auc2-pagination { display: flex; gap: 4px; flex-wrap: wrap; }
.auc2-pagination a, .auc2-pagination span { padding: 3px 8px; border: 1px solid #999; text-decoration: none; }
.auc2-pagination .is-current { font-weight: bold; }
.auc2-card { border: 1px solid #999; margin-bottom: 12px; padding: 8px 10px; }
.auc2-card-header { margin-bottom: 6px; }
.auc2-card-header .auc2-name { font-weight: bold; }
.auc2-card-row { display: flex; gap: 14px; align-items: flex-start; flex-wrap: wrap; }
.auc2-col-outfit { width: 64px; flex: 0 0 auto; text-align: center; }
.auc2-col-items { flex: 0 0 auto; display: grid; grid-template-columns: repeat(2, 34px); grid-template-rows: repeat(2, 34px); gap: 4px; }
.auc2-item-tile { position: relative; width: 34px; height: 34px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; }
.auc2-item-tile img { max-width: 28px; max-height: 28px; }
.auc2-item-count { position: absolute; bottom: 0; right: 1px; font-size: 9px; background: rgba(0,0,0,.6); color: #fff; padding: 0 2px; }
.auc2-col-data { flex: 1 1 220px; font-size: 12px; line-height: 1.5; }
.auc2-col-data .auc2-label { color: #666; }
.auc2-coin { width: 14px; height: 14px; vertical-align: middle; margin-left: 2px; }
.auc2-amount { white-space: nowrap; }
.auc2-col-bid { flex: 0 0 180px; border-left: 1px solid #ccc; padding-left: 12px; }
.auc2-col-bid form { display: flex; flex-direction: column; gap: 4px; }
</style>

<?php if ($pending !== false && $pending): ?>
	<h2><?= t('common.congrats') ?></h2>
	<p><?= t('common.you_have') ?> <?php echo (COUNT($pending) > 1) ? 'characters' : 'a character'; ?> ready to claim!</p>
	<?php foreach($pending as $character): ?>
	<table class="auction_char">
		<tr class="yellow">
			<td>Level</td>
			<td><?= t('common.vocation') ?></td>
			<td><?= t('auc.details') ?></td>
			<td>Price</td>
		</tr>
			<tr>
				<td><?php echo $character['level']; ?></td>
				<td><?php echo vocation_id_to_name($character['vocation']); ?></td>
				<td><a href="/auctionChar.php?action=view&zaid=<?php echo $character['zaid']; ?>">VIEW</a></td>
				<td><?php echo $character['price']; ?></td>
			</tr>
			<tr>
				<?php if ($loadOutfits): ?>
					<td class="outfitColumn">
						<img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $character['type']; ?>&addons=<?php echo $character['addons']; ?>&head=<?php echo $character['head']; ?>&body=<?php echo $character['body']; ?>&legs=<?php echo $character['legs']; ?>&feet=<?php echo $character['feet']; ?>" alt="img">
					</td>
				<?php endif; ?>
				<td colspan="3">
					<p><?= t('auc.claim_name') ?></p>
					<form action="/auctionChar.php" method="POST">
						<input type="hidden" name="action" value="claim">
						<input type="hidden" name="zaid" value="<?php echo $character['zaid']; ?>">
						<input type="text" name="name">
						<input type="submit" value="<?= t('auc.claim_btn') ?>">
					</form>
				</td>
			</tr>
	</table>
	<?php endforeach; ?>
	<h2><?= t('auc.ongoing') ?></h2>
<?php endif; ?>

<?php if ($is_admin): ?>
	<p>Admin: <a href="/admin/index.php?p=auction"><?= t('auc.history') ?></a></p>
<?php endif; ?>

<?php
if (!function_exists('auc2_url')) {
	function auc2_url(array $overrides = array()) {
		global $filterVoc, $filterLevelMin, $filterLevelMax, $search, $sort, $page;
		$params = array_merge(array(
			'voc' => $filterVoc ?: '',
			'level_min' => $filterLevelMin ?: '',
			'level_max' => $filterLevelMax ?: '',
			'q' => $search,
			'sort' => $sort,
			'page' => $page,
		), $overrides);
		$params = array_filter($params, static fn($v) => $v !== '' && $v !== 0 && $v !== null);
		return 'auctionChar.php?' . http_build_query($params);
	}
}
?>

<form action="auctionChar.php" method="get" class="auc2-filters">
	<div class="auc2-field">
		<label for="auc2_voc"><?= t('common.vocation') ?></label>
		<select id="auc2_voc" name="voc">
			<option value="0"><?= t('vocation.any') ?></option>
			<?php foreach ($config['vocations'] as $id => $vocation): if ((int)$id === 0) continue; ?>
				<option value="<?= (int)$id ?>" <?= $filterVoc === (int)$id ? 'selected' : '' ?>><?= h($vocation['name']) ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="auc2-field">
		<label for="auc2_level_min"><?= t('auc.min_level') ?></label>
		<input type="number" id="auc2_level_min" name="level_min" min="0" value="<?= $filterLevelMin ?: '' ?>" style="width:80px;">
	</div>
	<div class="auc2-field">
		<label for="auc2_level_max">&mdash;</label>
		<input type="number" id="auc2_level_max" name="level_max" min="0" value="<?= $filterLevelMax ?: '' ?>" style="width:80px;">
	</div>
	<div class="auc2-field">
		<label for="auc2_sort"><?= t_default('auc.sort_by', 'Sort by') ?></label>
		<select id="auc2_sort" name="sort">
			<option value="level_desc" <?= $sort === 'level_desc' ? 'selected' : '' ?>><?= t_default('auc.sort_level_desc', 'Level (high to low)') ?></option>
			<option value="level_asc" <?= $sort === 'level_asc' ? 'selected' : '' ?>><?= t_default('auc.sort_level_asc', 'Level (low to high)') ?></option>
			<option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= t_default('auc.sort_price_desc', 'Price (high to low)') ?></option>
			<option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= t_default('auc.sort_price_asc', 'Price (low to high)') ?></option>
			<option value="ending_soon" <?= $sort === 'ending_soon' ? 'selected' : '' ?>><?= t_default('auc.sort_ending_soon', 'Ending soon') ?></option>
		</select>
	</div>
	<div class="auc2-field" style="flex:1 1 200px;">
		<label for="auc2_q"><?= t('common.search') ?></label>
		<input type="text" id="auc2_q" name="q" value="<?= h($search) ?>" placeholder="<?= h(t_default('auc.search_placeholder', 'Character or item name')) ?>">
	</div>
	<div class="auc2-field">
		<input type="submit" value="<?= t('common.search') ?>">
	</div>
</form>

<div class="auc2-results-bar">
	<?php if ($pageCount > 1): ?>
		<div class="auc2-pagination">
			<?php for ($p = 1; $p <= $pageCount; $p++): ?>
				<?php if ($p === $page): ?>
					<span class="is-current"><?= $p ?></span>
				<?php else: ?>
					<a href="<?= h(auc2_url(['page' => $p])) ?>"><?= $p ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</div>

<?php if (is_array($characters) && !empty($characters)): ?>
	<?php foreach ($characters as $character): ?>
		<article class="auc2-card">
			<div class="auc2-card-header">
				<div class="auc2-name"><a href="/auctionChar.php?action=view&zaid=<?= (int)$character['zaid'] ?>"><?= h(t('common.level')) ?> <?= (int)$character['level'] ?> <?= h(vocation_id_to_name($character['vocation'])) ?></a></div>
				<div>
					<?= t('common.level') ?>: <?= (int)$character['level'] ?>
					| <?= t('common.vocation') ?>: <?= h(vocation_id_to_name($character['vocation'])) ?>
					| <?= ((int)$character['sex'] === 1) ? t('common.male') : t('common.female') ?>
					| <a href="/auctionChar.php?action=view&zaid=<?= (int)$character['zaid'] ?>"><?= t('auc.details') ?></a>
				</div>
			</div>
			<div class="auc2-card-row">
				<div class="auc2-col-outfit">
					<?php if ($loadOutfits): ?>
						<img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $character['type']; ?>&addons=<?php echo $character['addons']; ?>&head=<?php echo $character['head']; ?>&body=<?php echo $character['body']; ?>&legs=<?php echo $character['legs']; ?>&feet=<?php echo $character['feet']; ?>" alt="">
					<?php endif; ?>
				</div>
				<div class="auc2-col-items">
					<?php $highlights = $highlightItems[(int)$character['player_id']] ?? array(); ?>
					<?php foreach ($highlights as $hi): ?>
						<div class="auc2-item-tile" title="<?= h($items[$hi['itemtype']] ?? ('#' . $hi['itemtype'])) ?>">
							<img src="<?= h(znote_item_image_url((int)$hi['itemtype'])) ?>" alt="">
							<?php if ($hi['count'] > 1): ?><span class="auc2-item-count"><?= (int)$hi['count'] ?></span><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="auc2-col-data">
					<div><span class="auc2-label"><?= t_default('auc.start', 'Auction Start:') ?></span> <?= h(getClock($character['time_begin'], true)) ?></div>
					<div><span class="auc2-label"><?= t_default('auc.end', 'Auction End:') ?></span> <?= h(getClock($character['time_end'], true)) ?></div>
					<div>
						<?php $ended = (time() > $character['time_end']); ?>
						<span class="auc2-label"><?= t_default('auc.type_label', 'Type:') ?></span>
						<?php if ($ended): ?>
							<?= t_default('auc.instant', 'Instant') ?>
						<?php else: ?>
							<?= t_default('auc.bidding', 'Bidding') ?> (<span data-auc2-countdown="<?= (int)$character['time_end'] ?>" data-auc2-ended-text="<?= h(t_default('auc.instant', 'Instant')) ?>"><?= h(toDuration($character['time_end'] - time())) ?></span>)
						<?php endif; ?>
					</div>
					<div><span class="auc2-label"><?= t_default('auc.min_bid', 'Minimum Bid:') ?></span> <span class="auc2-amount"><?= (int)$character['price'] ?><img class="auc2-coin" src="<?= theme_asset('icon-tibiacoin.png') ?>" alt=""></span></div>
				</div>
				<div class="auc2-col-bid">
					<form action="/auctionChar.php" method="POST">
						<input type="hidden" name="action" value="bid">
						<input type="hidden" name="zaid" value="<?= (int)$character['zaid'] ?>">
						<label><?= t_default('auc.my_bid_limit', 'My Bid Limit') ?></label>
						<input type="number" name="price" min="<?= (int)$character['price'] ?>" step="1" value="<?= (int)$character['price'] ?>">
						<input type="submit" value="<?= t('auc.bid_btn') ?>">
					</form>
				</div>
			</div>
		</article>
	<?php endforeach; ?>

	<?php if ($pageCount > 1): ?>
		<div class="auc2-pagination">
			<?php for ($p = 1; $p <= $pageCount; $p++): ?>
				<?php if ($p === $page): ?>
					<span class="is-current"><?= $p ?></span>
				<?php else: ?>
					<a href="<?= h(auc2_url(['page' => $p])) ?>"><?= $p ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
<?php else: ?>
	<p><?= t_default('auc.no_auctions', 'No characters are currently up for auction.') ?></p>
<?php endif; ?>

<p><a href="/auctionChar.php?action=create"><?= t('auc.add') ?></a>.</p>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
