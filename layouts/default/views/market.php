<?php
if ($marketLoadError !== ''): ?>

	<div class="znx-acct">
	<div class="znx-acct-head"><?= t('market.title') ?></div>
	<div class="znx-empty-box">
		<p><?= t('market.load_failed2') ?></p>
		<p><?= t('market.tried_file') ?> <?php echo $marketLoadError; ?></p>
		<p><?= t('market.fix_path') ?></p>
		<p><?= t('market.check_permissions') ?></p>
	</div>
	</div>

<?php elseif ($marketMode === 'list'): ?>

	<div class="znx-acct">
	<div class="znx-acct-head"><?= t('market.title') ?></div>
	<div class="znx-acct-info"><?= t('market.hint') ?> <a target="_BLANK" href="http://znote.eu/images/depotmarket.jpg"><?= t('market.depot_link_text') ?></a> <br><?= t('market.sell_instructions') ?></div>
	<form action="" class="znx-acct-toolbar market_item_search">
		<div class="znx-acct-toolbar__field">
		<label for="compareSearch"><?= t('market.search') ?></label>
		<input type="text" id="compareSearch" name="compare" class="znx-acct-select">
		</div>
		<div class="znx-acct-toolbar__submit"><button type="submit" class="znx-acct-btn"><?= t('common.search') ?></button></div>
	</form>
	<div class="znx-acct-head"><?= t('market.wts') ?></div>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tr class="yellow">
			<td><?= t('market.item_name') ?></td>
			<td><?= t('common.item') ?></td>
			<td><?= t('common.count') ?></td>
			<td><?= t('market.price_for_1') ?></td>
			<td><?= t('common.added') ?></td>
			<td><?= t('common.by') ?></td>
			<td><?= t('market.compare') ?></td>
		</tr>
		<?php foreach (($offers['wts'] ?: array()) as $o): ?>
		<tr>
			<td><?php echo (isset($items[$o['item_id']])) ? $items[$o['item_id']] : $o['item_id']; ?></td>
			<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$o["item_id"]), ENT_QUOTES); ?>" alt="<?= t('market.item_image_alt') ?>"></td>
			<td><?php echo $o['amount']; ?></td>
			<td><?php echo number_format($o['price'], 0, "", " "); ?></td>
			<td><?php echo getClock($o['created'], true, true); ?></td>
			<td><?php echo ($o['anonymous'] == 1) ? t('market.anonymous') : "<a target='_BLANK' href='characterprofile.php?name=".$o['player_name']."'>".$o['player_name']."</a>"; ?></td>
			<td><a href="?compare=<?php echo $o['item_id']; ?>" class="znx-acct-edit-btn"><?= t('market.compare') ?></a></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
	<div class="znx-acct-head"><?= t('market.wtb') ?></div>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tr class="yellow">
			<td><?= t('market.item_name') ?></td>
			<td><?= t('common.item') ?></td>
			<td><?= t('common.count') ?></td>
			<td><?= t('market.price_for_1') ?></td>
			<td><?= t('common.added') ?></td>
			<td><?= t('common.by') ?></td>
			<td><?= t('market.compare') ?></td>
		</tr>
		<?php foreach (($offers['wtb'] ?: array()) as $o): ?>
		<tr>
			<td><?php echo (isset($items[$o['item_id']])) ? $items[$o['item_id']] : $o['item_id']; ?></td>
			<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$o["item_id"]), ENT_QUOTES); ?>" alt="<?= t('market.item_image_alt') ?>"></td>
			<td><?php echo $o['amount']; ?></td>
			<td><?php echo number_format($o['price'], 0, "", " "); ?></td>
			<td><?php echo getClock($o['created'], true, true); ?></td>
			<td><?php echo ($o['anonymous'] == 1) ? t('market.anonymous') : "<a target='_BLANK' href='characterprofile.php?name=".$o['player_name']."'>".$o['player_name']."</a>"; ?></td>
			<td><a href="?compare=<?php echo $o['item_id']; ?>" class="znx-acct-edit-btn"><?= t('market.compare') ?></a></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
	</div>

<?php else: ?>

	<div class="znx-acct">
	<?php if (!is_string($compare)): ?>
		<div class="znx-acct-head"><?= t('market.comparing_item', ['name' => $itemname]) ?></div>
	<?php else: ?>
		<div class="znx-acct-head"><?= t('market.search_result', ['query' => stripslashes($compare)]) ?></div>
	<?php endif; ?>
	<div class="znx-jump-top"><a href="market.php" class="znx-acct-edit-btn"><?= t('market.go_back') ?></a></div>
	<div class="znx-acct-head"><?= t('market.active') ?></div>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tr class="yellow">
			<td><?= t('market.item_name') ?></td>
			<td><?= t('common.item') ?></td>
			<td><?= t('common.count') ?></td>
			<td><?= t('market.price_for_1') ?></td>
			<td><?= t('common.added') ?></td>
			<td><?= t('common.by') ?></td>
		</tr>
		<?php foreach ($activeSellOffers as $o): ?>
		<tr>
			<td><?php echo (isset($items[$o['item_id']])) ? $items[$o['item_id']] : $o['item_id']; ?></td>
			<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$o["item_id"]), ENT_QUOTES); ?>" alt="<?= t('market.item_image_alt') ?>"></td>
			<td><?php echo $o['amount']; ?></td>
			<td><?php echo number_format($o['price'], 0, "", " "); ?></td>
			<td><?php echo getClock($o['created'], true, true); ?></td>
			<td><?php echo ($o['anonymous'] == 1) ? t('market.anonymous') : "<a target='_BLANK' href='characterprofile.php?name=".$o['player_name']."'>".$o['player_name']."</a>"; ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>

	<?php if ($buylist !== false): ?>
		<div class="znx-acct-head"><?= t('market.want_to_buy') ?></div>
		<div class="znx-acct-table-wrap">
		<table class="znx-acct-table">
			<tr class="yellow">
				<td><?= t('market.item_name') ?></td>
				<td><?= t('common.item') ?></td>
				<td><?= t('common.count') ?></td>
				<td><?= t('market.price_for_1') ?></td>
				<td><?= t('common.added') ?></td>
				<td><?= t('common.by') ?></td>
			</tr>
			<?php foreach ($buylist as $o): ?>
			<tr>
				<td><?php echo (isset($items[$o['item_id']])) ? $items[$o['item_id']] : $o['item_id']; ?></td>
				<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$o["item_id"]), ENT_QUOTES); ?>" alt="<?= t('market.item_image_alt') ?>"></td>
				<td><?php echo $o['amount']; ?></td>
				<td><?php echo number_format($o['price'], 0, "", " "); ?></td>
				<td><?php echo getClock($o['created'], true, true); ?></td>
				<td><?php echo ($o['anonymous'] == 1) ? t('market.anonymous') : "<a target='_BLANK' href='characterprofile.php?name=".$o['player_name']."'>".$o['player_name']."</a>"; ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
		</div>
	<?php endif; ?>

	<div class="znx-acct-head"><?= t('market.old') ?></div>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tr class="yellow">
			<td><?= t('market.item_name') ?></td>
			<td><?= t('common.item') ?></td>
			<td><?= t('common.count') ?></td>
			<td><?= t('market.price_for_1') ?></td>
			<td><?= t('market.sold') ?></td>
		</tr>
		<?php foreach (($historyOffers ?: array()) as $o): ?>
		<tr>
			<td><?php echo (isset($items[$o['item_id']])) ? $items[$o['item_id']] : $o['item_id']; ?></td>
			<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$o["item_id"]), ENT_QUOTES); ?>" alt="<?= t('market.item_image_alt') ?>"></td>
			<td><?php echo $o['amount']; ?></td>
			<td><?php echo number_format($o['price'], 0, "", " "); ?></td>
			<td><?php echo getClock($o['inserted'], true, true); ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
	</div>

<?php endif; ?>
