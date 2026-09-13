<p><?= t('auc.detailed_info') ?> <a href="/auctionChar.php?action=list"><?= t('auc.go_back_list') ?></a></p>
<!-- Basic info -->
<table class="auction_char">
	<tr class="yellow">
		<td><?= t('common.level') ?></td>
		<td><?= t('common.vocation') ?></td>
		<?php if ($loadOutfits): ?>
			<td><?= t('common.image') ?></td>
		<?php endif; ?>
		<td><?= t('auc.bank') ?></td>
		<td><?= t('auc.price') ?></td>
	</tr>
	<tr>
		<td><?php echo $character['level']; ?></td>
		<td><?php echo vocation_id_to_name($character['vocation']); ?></td>
		<?php if ($loadOutfits): ?>
			<td class="outfitColumn">
				<img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $character['type']; ?>&addons=<?php echo $character['addons']; ?>&head=<?php echo $character['head']; ?>&body=<?php echo $character['body']; ?>&legs=<?php echo $character['legs']; ?>&feet=<?php echo $character['feet']; ?>" alt="img">
			</td>
		<?php endif; ?>
		<td><?php echo $character['balance']; ?></td>
		<td><?= t('auc.price_points', ['price' => $character['price']]) ?></td>
	</tr>
	<?php if ($bidding_period): ?>
		<tr>
			<td colspan="<?php echo ($loadOutfits) ? 5 : 4; ?>">
				<p><strong><?= t('auc.remaining') ?></strong> <?php echo toDuration((int)$character['time_end']-time()); ?>.</p>
			</td>
		</tr>
	<?php endif; ?>
</table>
<!-- Bid on character -->
<?php
if ($character['own'] == 0) {
	if (is_array($account) && !empty($account)): ?>
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
	<?php endif;
} else {
	?>
	<p><strong><?= t('auc.is_seller') ?></strong>
		<br><strong><?= t('common.name_label') ?></strong> <a href="/characterprofile.php?name=<?php echo $character['name']; ?>"><?php echo $character['name']; ?></a>
		<br><strong><?= t('auc.price') ?>:</strong> <?php echo $character['price']; ?>
		<br><strong><?= t('common.bid_label') ?></strong> <?php echo $character['bid']; ?>
		<br><strong><?= t('auc.deposit') ?></strong> <?php echo $character['deposit']; ?>
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
	</p>
	<?php
}
?>
<!-- SKILLS -->
<table class="auction_skills">
	<tr class="yellow"><td colspan="4"><?= t('auc.skills') ?></td></tr>
	<tr><td><?= t('skill.magic') ?></td><td><?php echo $character['magic']; ?></td></tr>
	<tr><td><?= t('skill.fist') ?></td><td><?php echo $character['fist']; ?></td></tr>
	<tr><td><?= t('skill.club') ?></td><td><?php echo $character['club']; ?></td></tr>
	<tr><td><?= t('skill.sword') ?></td><td><?php echo $character['sword']; ?></td></tr>
	<tr><td><?= t('skill.axe') ?></td><td><?php echo $character['axe']; ?></td></tr>
	<tr><td><?= t('skill.distance') ?></td><td><?php echo $character['dist']; ?></td></tr>
	<tr><td><?= t('skill.shielding') ?></td><td><?php echo $character['shielding']; ?></td></tr>
	<tr><td><?= t('skill.fishing') ?></td><td><?php echo $character['fishing']; ?></td></tr>
</table>
<!-- Player items -->
<?php if (is_array($player_items) && !empty($player_items)): ?>
	<table>
		<tr class="yellow">
			<td colspan="3"><?= t('auc.player_items') ?></td>
		</tr>
		<tr class="yellow">
			<td><?= t('common.image') ?></td>
			<td><?= t('common.item') ?></td>
			<td><?= t('common.count') ?></td>
		</tr>
		<?php foreach($player_items as $item): ?>
			<tr>
				<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$item["itemtype"]), ENT_QUOTES); ?>" alt="<?= t('market.item_image_alt') ?>"></td>
				<td><a href="/market.php?compare=<?php echo $item['itemtype']; ?>" target="_BLANK"><?php echo (isset($items[$item['itemtype']])) ? $items[$item['itemtype']] : $item['itemtype']; ?></a></td>
				<td><?php echo $item['count']; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
<!-- Depot items -->
<?php if (is_array($depot_items) && !empty($depot_items)): ?>
	<table>
		<tr class="yellow">
			<td colspan="3"><?= t('auc.depot_items') ?></td>
		</tr>
		<tr class="yellow">
			<td><?= t('common.image') ?></td>
			<td><?= t('common.item') ?></td>
			<td><?= t('common.count') ?></td>
		</tr>
		<?php foreach($depot_items as $item): ?>
			<tr>
				<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$item["itemtype"]), ENT_QUOTES); ?>" alt="<?= t('market.item_image_alt') ?>"></td>
				<td><a href="/market.php?compare=<?php echo $item['itemtype']; ?>" target="_BLANK"><?php echo (isset($items[$item['itemtype']])) ? $items[$item['itemtype']] : $item['itemtype']; ?></a></td>
				<td><?php echo $item['count']; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
