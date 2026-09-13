<?php if ($pending !== false): ?>
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
<?php if (is_array($characters) && !empty($characters)): ?>
	<table class="auction_char">
		<tr class="yellow">
			<td>Level</td>
			<td><?= t('common.vocation') ?></td>
			<?php if ($loadOutfits): ?>
				<td>Image</td>
			<?php endif; ?>
			<td><?= t('auc.details') ?></td>
			<td>Price</td>
			<td>Added</td>
			<td>Type</td>
		</tr>
		<?php foreach($characters as $character): ?>
			<tr>
				<td><?php echo $character['level']; ?></td>
				<td><?php echo vocation_id_to_name($character['vocation']); ?></td>
				<?php if ($loadOutfits): ?>
					<td class="outfitColumn">
						<img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $character['type']; ?>&addons=<?php echo $character['addons']; ?>&head=<?php echo $character['head']; ?>&body=<?php echo $character['body']; ?>&legs=<?php echo $character['legs']; ?>&feet=<?php echo $character['feet']; ?>" alt="img">
					</td>
				<?php endif; ?>
				<td><a href="/auctionChar.php?action=view&zaid=<?php echo $character['zaid']; ?>">VIEW</a></td>
				<td><?php echo $character['price']; ?></td>
				<td><?php
					$ended = (time() > $character['time_end']) ? true : false;
					echo getClock($character['time_begin'], true);
					?>
				</td>
				<td><?php echo ($ended) ? 'Instant' : 'Bidding<br>('.toDuration(($character['time_end'] - time())).')'; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
<p><a href="/auctionChar.php?action=create"><?= t('auc.add') ?></a>.</p>
