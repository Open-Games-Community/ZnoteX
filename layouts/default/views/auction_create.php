<?php if (is_array($own_characters) && !empty($own_characters)): ?>
	<p><a href="/auctionChar.php?action=list"><?= t('auc.go_back_list') ?></a></p>
	<form action="/auctionChar.php" method="POST">
		<input type="hidden" name="action" value="add">
		<p><?= t('auc.char_offline') ?></p>
		<select name="pid">
			<?php foreach($own_characters as $char): ?>
				<option value="<?php echo $char['id']; ?>">
					<?= t('auc.char_option', ['level' => $char['level'], 'vocation' => vocation_id_to_name($char['vocation']), 'name' => $char['name']]) ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p><strong><?= t('auc.shop_points') ?></strong>
			<br><?= t('auc.your_points') ?> <?php echo $own_characters[0]['points']; ?>
			<br><?= t('auc.minimum') ?> <?php echo $auction['lowestPrice']; ?>
			<br><?= t('auc.deposit') ?> <?php echo $auction['deposit']; ?>%
			<br><?= t('auc.your_max') ?> <?php echo $max; ?>
		</p>
		<p><strong><?= t('auc.deposit_info') ?></strong>
			<br><?= t('auc.deposit_reason', ['percent' => $auction['deposit']]) ?>
		</p>
		<p><?= t('auc.deposit_refund') ?></p>
		<p><?= t('auc.reclaim_info') ?></p>
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
	<p><?= t('auc.rules_intro') ?>
		<br><?= t('auc.rule_min_level', ['level' => $auction['lowestLevel']]) ?>
		<br><?= t('auc.rule_min_points', ['points' => $minToCreate]) ?>
		<br><?= t('auc.rule_offline') ?>
	</p>
<?php endif; ?>
