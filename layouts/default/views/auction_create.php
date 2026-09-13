<?php if (is_array($own_characters) && !empty($own_characters)): ?>
	<p><a href="/auctionChar.php?action=list"><?= t('auc.go_back_list') ?></a></p>
	<form action="/auctionChar.php" method="POST">
		<input type="hidden" name="action" value="add">
		<p><?= t('auc.char_offline') ?></p>
		<select name="pid">
			<?php foreach($own_characters as $char): ?>
				<option value="<?php echo $char['id']; ?>">
					<?php echo "Level: ", $char['level'], " ", vocation_id_to_name($char['vocation']), ": ", $char['name']; ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p><strong><?= t('auc.shop_points') ?></strong>
			<br><?= t('auc.your_points') ?> <?php echo $own_characters[0]['points']; ?>
			<br><?= t('auc.minimum') ?> <?php echo $auction['lowestPrice']; ?>
			<br>deposit: <?php echo $auction['deposit']; ?>%
			<br><?= t('auc.your_max') ?> <?php echo $max; ?>
		</p>
		<p><strong><?= t('auc.deposit_info') ?></strong>
			<br>To ensure you as the seller is a legitimate account, and to encourage fair prices you have to temporarily invest <?php echo $auction['deposit']; ?>% of the selling price as a deposit.
		</p>
		<p>Once the auction has completed, the deposit fee will be refunded back to your account.</p>
		<p>If you wish to reclaim your character, you can do it after the bidding period if nobody has placed an offer on it. But if you do this you will not get the deposit back. It is therefore advisable that you create a good and appealing offer to our community.</p>
		<p><?= t('auc.sell_price') ?></p>
		<input type="number" name="cost" min="<?php echo $auction['lowestPrice']; ?>" max="<?php echo $max; ?>" step="5" placeholder="<?php echo $auction['lowestPrice']; ?> - <?php echo $max; ?>">
		<br>
		<p><?= t('auc.verify_pw') ?></p>
		<input type="password" name="password">
		<br>
		<input type="submit" value="<?= t('auc.sell_btn') ?>">
	</form>
<?php else: ?>
	<p><a href="/auctionChar.php?action=list"><?= t('auc.go_back_list') ?></a></p>
	<p>Your account does not follow the required rules to sell characters.
		<br>1. Minimum level: <?php echo $auction['lowestLevel']; ?>
		<br>2. Minimum already earned shop points: <?php echo $minToCreate; ?>
		<br>3. Eligible characters must be offline.
	</p>
<?php endif; ?>
