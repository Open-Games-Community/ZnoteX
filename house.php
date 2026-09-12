<?php require_once 'engine/init.php';
znote_csrf_protect_public_post();
theme_open();
if ($config['log_ip']) {
	znote_visitor_insert_detailed_data(3);
}

$house = (isset($_GET['id']) && (int)$_GET['id'] > 0) ? (int)$_GET['id'] : false;
$house_SQL = "";
$house_SQL_params = [];
if ($house !== false) {
	$house_SQL = "
		SELECT
			`h`.`id`, `h`.`owner`, `h`.`paid`, `h`.`name`, `h`.`rent`, `h`.`town_id`,
			`h`.`size`, `h`.`beds`, " . houseSelect(array('bid','bid_end','last_bid','highest_bidder'), 'h') . ",
			`p`.`name` AS `ownername`
		FROM `houses` AS `h`
		LEFT JOIN `players` AS `p`
			ON `h`.`owner` > 0
			AND `p`.`id` = `h`.`owner`
		WHERE `h`.`id` = ?;
	";
	$house_SQL_params = [$house];
	$house = db()->fetchOne($house_SQL, $house_SQL_params);
	if (!is_array($house)) {
		?>
		<h1><?= t('house.not_found') ?></h1>
		<p><?= t('house.go_back') ?> <a href="houses.php">house list</a> and select a house for further details.</p>
		<?php
		theme_close();
		exit;
	}
	$minbid = $config['houseConfig']['minimumBidSQM'] * $house['size'];
	if ($house['owner'] == 0) unset($house['ownername']);

	if ($config['houseConfig']['shopPoints']['enabled']) {
		$house['points'] = $house['size'];

		foreach ($config['houseConfig']['shopPoints']['cost'] AS $cost_sqm => $cost_points) {
			if ($cost_sqm < $house['size']) $house['points'] = $cost_points;
		}
	}

	//data_dump($house, false, "House data");

	//////////////////////
	// Bid on house logic
	$bid_char = &$_POST['char'];
	$bid_amount = &$_POST['amount'];
	if ($bid_amount && $bid_char) {
		$bid_char = (int)$bid_char;
		$bid_amount = (int)$bid_amount;
		
		$player = db()->fetchOne("
			SELECT `id`, `account_id`, `name`, `level`, `balance`
			FROM `players`
			WHERE `id` = ? LIMIT 1;
		", [$bid_char]);

		if (user_logged_in() === true && is_array($player) && $player['account_id'] == $session_user_id) {
			// Does player have or need premium?
			$premstatus = ($config['houseConfig']['requirePremium'] && $user_data['premdays']  == 0) ? false : true;
			if ($premstatus) {
				
				// Can player have or bid on more houses?
				$pHouseCount = db()->fetchOne("
					SELECT COUNT('id') AS `value`
					FROM `houses`
					WHERE (
						(`" . houseCol('highest_bidder') . "` = ? AND `owner` = ?)
						OR (`" . houseCol('highest_bidder') . "` = ?)
						OR (`owner` = ?)
					)
					AND `id` != ? LIMIT 1;
				", [$bid_char, $bid_char, $bid_char, $bid_char, $house['id']]);

				if ($pHouseCount['value'] < $config['houseConfig']['housesPerPlayer']) {
					// Is character level high enough?
					if ($player['level'] >= $config['houseConfig']['levelToBuyHouse']) {
						// Can player afford this bid?
						if ($player['balance'] > $bid_amount) {
							
							// Is bid higher than previous bid?
							if ($bid_amount > $house['bid']) {
								// Is bid higher than lowest bid?
								if ($bid_amount > $minbid) {
									// Should only apply to external players, allowing a player to up his pledge without
									// being forced to pay his full previous bid.
									if ($house['highest_bidder'] != $player['id']) $lastbid = $house['bid'] + 1;
									else {
										$lastbid = $house['last_bid'];
										echo "<b><font color='green'>You have raised the house pledge to ".$bid_amount."gp!</font></b><br>";
									}
									// Has bid already started?
									if ($house['bid_end'] > 0) {
										if ($house['bid_end'] > time()) {
											
											db()->execute("
												UPDATE `houses`
												SET
													`" . houseCol('highest_bidder') . "` = ?,
													`" . houseCol('bid') . "` = ?,
													`" . houseCol('last_bid') . "` = ?
												WHERE `id` = ? LIMIT 1;
											", [$player['id'], $bid_amount, $lastbid, $house['id']]);

											$house = db()->fetchOne("
												SELECT
													`id`, `owner`, `paid`, `name`, `rent`, `town_id`, `size`,
													`beds`, " . houseSelect(array('bid','bid_end','last_bid','highest_bidder')) . "
												FROM `houses`
												WHERE `id` = ?;
											", [$house['id']]);
										}

									} else {
										$lastbid = $minbid + 1;
										$bidend = time() + $config['houseConfig']['auctionPeriod'];

										db()->execute("
											UPDATE `houses`
											SET
												`" . houseCol('highest_bidder') . "` = ?,
												`" . houseCol('bid') . "` = ?,
												`" . houseCol('last_bid') . "` = ?,
												`" . houseCol('bid_end') . "` = ?
											WHERE `id` = ? LIMIT 1;
										", [$player['id'], $bid_amount, $lastbid, $bidend, $house['id']]);

										$house = db()->fetchOne("
											SELECT
												`id`, `owner`, `paid`, `name`, `rent`, `town_id`, `size`,
												`beds`, " . houseSelect(array('bid','bid_end','last_bid','highest_bidder')) . "
											FROM `houses`
											WHERE `id` = ?;
										", [$house['id']]);

									}
									echo "<b><font color='green'>". t('house.highest_bid') ."</font></b>";
								} else echo "<b><font color='red'>You need to place a bid that is higher or equal to {$minbid}gp.</font></b>";
							
							} else {
								// Check if current bid is higher than last_bid
								if ($bid_amount > $house['last_bid']) {
									// Should only apply to external players, allowing a player to up his pledge without
									// being forced to pay his full previous bid.
									if ($house['highest_bidder'] != $player['id']) {
										$lastbid = $bid_amount + 1;
										
										db()->execute("
											UPDATE `houses`
											SET `" . houseCol('last_bid') . "` = ?
											WHERE `id` = ? LIMIT 1;
										", [$lastbid, $house['id']]);

										$house = db()->fetchOne("
											SELECT
												`id`, `owner`, `paid`, `name`, `rent`, `town_id`, `size`,
												`beds`, " . houseSelect(array('bid','bid_end','last_bid','highest_bidder')) . "
											FROM `houses`
											WHERE `id` = ?;
										", [$house['id']]);

										echo "<b><font color='orange'>Unfortunately your bid was not higher than previous bidder.</font></b>";
									} else {
										echo "<b><font color='orange'>". t('house.already_higher') ."</font></b>";
									}
								} else {
									echo "<b><font color='red'>Too low bid amount, someone else has a higher bid active.</font></b>";
								}
							}
						} else echo "<b><font color='red'>'. t('house.not_enough2'). '</font></b>";
					} else echo "<b><font color='red'>Your character is to low level, must be higher level than ", $config['houseConfig']['levelToBuyHouse']-1 ," to buy a house.</font></b>";
				} else echo "<b><font color='red'>". t('house.too_many') ."</font></b>";
			} else echo "<b><font color='red'>'. t('house.need_premium2'). '</font></b>";
		} else echo "<b><font color='red'>You may only bid on houses for characters on your account.</font></b>";
	}

	////////////////////////////////////////
	// Instantly buy house with shop points
	if ($config['houseConfig']['shopPoints']['enabled']
		&& isset($_POST['instantbuy'])
		&& $bid_char
		&& $house['owner'] == 0
		&& isset($house['points'])) {

		$account_points = (int)$user_znote_data['points'];

		if ($account_points >= $house['points']) {

			$bid_char = (int)$bid_char;
			$player = db()->fetchOne("
				SELECT `id`, `account_id`, `name`, `level`
				FROM `players`
				WHERE `id` = ? LIMIT 1;
			", [$bid_char]);

			$pHouseCount = db()->fetchOne("
				SELECT COUNT('id') AS `value`
				FROM `houses`
				WHERE (
					(`" . houseCol('highest_bidder') . "` = ? AND `owner` = ?)
					OR (`" . houseCol('highest_bidder') . "` = ?)
					OR (`owner` = ?)
				)
				AND `id` != ? LIMIT 1;
			", [$bid_char, $bid_char, $bid_char, $bid_char, $house['id']]);

			if (user_logged_in() === true
				&& $player['account_id'] == $session_user_id
				&& $player['level'] >= $config['houseConfig']['levelToBuyHouse']
				&& $pHouseCount['value'] < $config['houseConfig']['housesPerPlayer']) {

				$house_points = (int)$house['points'];
				$house_id = $house['id'];
				$time = time();

				// Lock the account balance and the house row together, so two
				// concurrent purchases (or a purchase racing a bid) cannot both
				// succeed or spend points that were already spent.
				$purchased = db()->transaction(function ($db) use ($session_user_id, $bid_char, $house_id, $house_points, $time) {
					$account = $db->fetchOne("SELECT `points` FROM `znote_accounts` WHERE `account_id` = ? LIMIT 1 FOR UPDATE;", [$session_user_id]);
					if (!is_array($account) || (int)$account['points'] < $house_points) {
						return false;
					}

					$houseRow = $db->fetchOne("SELECT `owner` FROM `houses` WHERE `id` = ? LIMIT 1 FOR UPDATE;", [$house_id]);
					if (!is_array($houseRow) || (int)$houseRow['owner'] !== 0) {
						return false;
					}

					$db->execute("UPDATE `znote_accounts` SET `points` = `points` - ? WHERE `account_id` = ? LIMIT 1;", [$house_points, $session_user_id]);
					$db->execute("UPDATE `houses` SET `owner` = ? WHERE `id` = ? LIMIT 1;", [$bid_char, $house_id]);
					$db->execute("
						INSERT INTO `znote_shop_logs`
						(`account_id`, `player_id`, `type`, `itemid`, `count`, `points`, `time`) VALUES
						(?, ?, 7, ?, 1, ?, ?)
					", [$session_user_id, $bid_char, $house_id, $house_points, $time]);
					$db->execute("
						INSERT INTO `znote_shop_orders`
						(`account_id`, `type`, `itemid`, `count`, `time`) VALUES
						(?, 7, ?, ?, ?)
					", [$session_user_id, $house_id, $bid_char, $time]);

					return true;
				});

				if ($purchased) {
					// Reload house data
					$house = db()->fetchOne($house_SQL, $house_SQL_params);
					$minbid = $config['houseConfig']['minimumBidSQM'] * $house['size'];
					if ($house['owner'] > 0) $house['ownername'] = user_name($house['owner']);

					// Congratulate user and tell them they still has to pay rent (if rent > 0)
					?>
					<p><strong><?= t('house.congrats') ?></strong>
						<br>You now own this house!
						<br><?= t('house.remember_say') ?> <strong>!shop</strong> in-game to process your ownership!
						<?php if ($house['rent'] > 0): ?>
							<br>Keep in mind you still need to pay rent on this house, make sure you have enough bank balance to cover it!
						<?php endif; ?>
					</p>
					<?php
				} else {
					?>
					<p><strong>Error:</strong>
						<br>This house was already bought or your points balance changed. Please refresh and try again.
					</p>
					<?php
				}
			} else {
				?>
				<p><strong>Error:</strong>
					<br>Either your level is too low, or your player already have or is bidding on another house.
					<br><?= t('house.your_level') ?> <?php echo $player['level']; ?>. Minimum level to buy house: <?php echo $config['houseConfig']['levelToBuyHouse']; ?>
					<br><?= t('house.your_bids') ?> <?php echo $pHouseCount['value']; ?>. Maximum house per player: <?php echo $config['houseConfig']['housesPerPlayer']; ?>.
				</p>
				<?php
			}
		}
	}

	// HTML structure and logic
	?>
	<h1><?= t('house.label') ?> <?php echo $house['name']; ?></h1>
	<ul>
		<li><b>Town</b>:
		<?php
		$town_name = &$config['towns'][$house['town_id']];
		echo "<a href='houses.php?id={$house['town_id']}'>". ($town_name ? $town_name : 'Specify town id ' . $house['town_id'] . ' name in config.php first.') ."</a>";
		?></li>
		<li><b>Size</b>: <?php echo $house['size']; ?></li>
		<li><b>Beds</b>: <?php echo $house['beds']; ?></li>
		<li><b>Owner</b>: <?php
		if ($house['owner'] > 0) echo "<a href='characterprofile.php?name={$house['ownername']}' target='_BLANK'>{$house['ownername']}</a>";
		else echo "Available for auction.";
		?></li>
		<li><b>Rent</b>: <?php echo $house['rent']; ?></li>
		<?php if ($house['owner'] == 0 && isset($house['points'])): ?>
			<li><b><?= t('house.shop_points2') ?></b>: <?php echo $house['points']; ?></li>
		<?php endif; ?>
	</ul>
	<?php
	// AUCTION MARKUP INIT
	if ($house['owner'] == 0) {
		?>
		<h2><?= t('house.on_auction2') ?></h2>
		<?php
		if ($house['highest_bidder'] == 0) echo "<b>'. t('house.no_bidders2'). '</b>";
		else {
			$bidder = db()->fetchOne("SELECT `name` FROM `players` WHERE `id` = ? LIMIT 1;", [$house['highest_bidder']]);
			echo "<b>This house have bidders! If you want this house, now is your chance!</b>";
			echo "<br><b>'. t('house.active_bid'). '</b> {$house['last_bid']}gp";
			echo "<br><b>'. t('house.active_bid_by'). '</b> <a href='characterprofile.php?name={$bidder['name']}' target='_BLANK'>{$bidder['name']}</a>";
			echo "<br><b>'. t('house.bid_ends'). '</b> ". getClock($house['bid_end'], true);
		}

		if ($house['bid_end'] == 0 || $house['bid_end'] > time()) {
			if (user_logged_in()) {
				// Your characters, indexed by char_id
				$yourChars = db()->fetchAll("SELECT `id`, `name`, `balance` FROM `players` WHERE `account_id` = ?;", [$user_data['id']]);
				if ($yourChars !== false) {
					$charData = array();
					foreach ($yourChars as $char) {
						$charData[$char['id']] = $char;
					}
					?>
					<form class="house_form_bid" action="" method="post">
						<select name="char">
							<?php
							foreach ($charData as $id => $char) {
								echo "<option value='$id'>{$char['name']} [{$char['balance']}]</option>";
							}
							?>
						</select>
						<input type="text" name="amount" placeholder="Min bid: <?php echo $minbid + 1; ?>">
						<input type="submit" value="<?= t('house.bid_submit') ?>">
					</form>
					<?php if ($house['owner'] == 0 && isset($house['points'])): ?>
						<br>
						<?php if ((int)$user_znote_data['points'] >= $house['points']): ?>
							<form class="house_form_buy" action="" method="post">
								<p><?= t('house.your_account') ?> <strong><?php echo $user_znote_data['points']; ?></strong> available shop points.</p>
								<select name="char">
									<?php
									foreach ($charData as $id => $char) {
										echo "<option value='$id'>". $char['name'] ."</option>";
									}
									?>
								</select>
								<input type="submit" name="instantbuy" value="Buy now for <?php echo $house['points']; ?> shop points!">
							</form>
						<?php else: ?>
							<p><?= t('house.your_account') ?> <strong><?php echo $user_znote_data['points']; ?></strong> available shop points.
								<br>You don't have enough shop points to instantly buy this house.</p>
						<?php endif; ?>
					<?php endif; ?>
					<?php
				} else echo "<br>You need a character to bid on this house.";
			} else echo "<br>You need to login before you can bid on houses.";
		} else echo "<br><b>Bid has ended! House transaction will proceed next server restart assuming active bidder have sufficient balance.</b>";
	}
} else {
	?>
	<h1><?= t('house.none_selected') ?></h1>
	<p><?= t('house.go_back') ?> <a href="houses.php">house list</a> and select a house for further details.</p>
	<?php
}
theme_close(); ?>
