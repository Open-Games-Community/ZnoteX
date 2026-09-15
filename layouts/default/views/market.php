<?php
if ($marketLoadError !== ''): ?>

	<h1><?= t('market.title') ?></h1>
	<p><?= t('market.load_failed2') ?></p>
	<p><?= t('market.tried_file') ?> <?php echo $marketLoadError; ?></p>
	<p><?= t('market.fix_path') ?></p>
	<p><?= t('market.check_permissions') ?></p>

<?php elseif ($marketMode === 'list'): ?>

	<h1><?= t('market.title') ?></h1>
	<p><?= t('market.hint') ?> <a target="_BLANK" href="http://znote.eu/images/depotmarket.jpg"><?= t('market.depot_link_text') ?></a> <br><?= t('market.sell_instructions') ?></p>
	<form action="" class="market_item_search">
		<label for="compareSearch"><?= t('market.search') ?></label>
		<input type="text" id="compareSearch" name="compare">
		<input type="submit" value="<?= t('common.search') ?>">
	</form>
	<h2><?= t('market.wts') ?></h2>
	<table class="table tbl-hover">
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
			<td><a href="?compare=<?php echo $o['item_id']; ?>"><button><?= t('market.compare') ?></button></a></td>
		</tr>
		<?php endforeach; ?>
	</table>
	<h2><?= t('market.wtb') ?></h2>
	<table class="table tbl-hover">
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
			<td><a href="?compare=<?php echo $o['item_id']; ?>"><button><?= t('market.compare') ?></button></a></td>
		</tr>
		<?php endforeach; ?>
	</table>

<?php else: ?>

	<?php if (!is_string($compare)): ?>
		<h1><?= t('market.comparing_item', ['name' => $itemname]) ?></h1>
	<?php else: ?>
		<h1><?= t('market.search_result', ['query' => stripslashes($compare)]) ?></h1>
	<?php endif; ?>
	<a href="market.php"><button><?= t('market.go_back') ?></button></a>
	<h2><?= t('market.active') ?></h2>
	<table class="table tbl-hover">
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

	<?php if ($buylist !== false): ?>
		<h2><?= t('market.want_to_buy') ?></h2>
		<table class="table tbl-hover">
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
	<?php endif; ?>

	<h2><?= t('market.old') ?></h2>
	<table class="table tbl-hover">
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

<?php endif; ?>
