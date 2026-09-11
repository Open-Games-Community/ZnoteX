<?php require_once 'engine/init.php';
znote_csrf_protect_public_post();
protect_page();
theme_open();
// Convert a seconds integer value into days, hours, minutes and seconds string.
function toDuration($is) {
	$duration['day'] = $is / (24 * 60 * 60);
	if (($duration['day'] - (int)$duration['day']) > 0)
		$duration['hour'] = ($duration['day'] - (int)$duration['day']) * 24;
	if (isset($duration['hour'])) {
		if (($duration['hour'] - (int)$duration['hour']) > 0)
			$duration['minute'] = ($duration['hour'] - (int)$duration['hour']) * 60;
		if (isset($duration['minute'])) {
			if (($duration['minute'] - (int)$duration['minute']) > 0)
				$duration['second'] = ($duration['minute'] - (int)$duration['minute']) * 60;
		}
	}
	$tmp = array();
	foreach ($duration as $type => $value) {
		if ($value >= 1) {
			$pluralType = ((int)$value === 1) ? $type : $type . 's';
			if ($type !== 'second') $tmp[] = (int)$value . " $pluralType";
			else $tmp[] = (int)$value . " $pluralType";
		}
	}
	return implode(', ', $tmp);
}
?>
<h1><?= t('auc.page_title') ?></h1>
<?php
// Import from config:
$auction = $config['shop_auction'];
$loadOutfits = ($config['show_outfits']['highscores']) ? true : false;
$this_account_id = (int)$session_user_id;
$is_admin = is_admin($user_data);

// If character auction is enabled in config.php
if ($auction['characterAuction']) {

	if ($config['ServerEngine'] != 'TFS_10') {
		echo "<p>Character shop auction system is currently only available for ServerEngine TFS_10.</p>";
		theme_close();
		die();
	}
	if ((int)$auction['storage_account_id'] === (int)$this_account_id) {
		echo "<p>The storage account cannot use the character auction.</p>";
		theme_close();
		die();
	}
	$step = $auction['step'];
	$step_duration = $auction['step_duration'];
	$actions = array(
		'list',		// list all available players in auction
		'view',		// view a specific player
		'create',	// select which character to add and initial price
		'add',		// add character to list
		'bid',		// Bid or buy a specific player
		'refund',	// Refund a player you added back to your account
		'claim'		// Claim a character you won through purchase or bid
	);

	// Default action is list, but $_GET or $_POST will override it.
	$action = 'list';
	// Load selected string from actions array based on input, strict whitelist validation
	if (isset( $_GET['action']) && in_array( $_GET['action'], $actions)) {
		$action = $actions[array_search( $_GET['action'], $actions, true)];
	}
	if (isset($_POST['action']) && in_array($_POST['action'], $actions)) {
		$action = $actions[array_search($_POST['action'], $actions, true)];
	}

	// Passive check to see if bid period has expired and someone won a deal
	$time = time();
	db()->transaction(function ($db) use ($time) {
		$expired_auctions = $db->fetchAll("
			SELECT
				`id`,
				`original_account_id`,
				(`bid`+`deposit`) as `points`
			FROM `znote_auction_player`
			WHERE `sold` = 0
			AND `time_end` < ?
			AND `bidder_account_id` > 0
			FOR UPDATE
		", [$time]);

		if ($expired_auctions !== false) {
			foreach ($expired_auctions as $a) {
				$db->execute("UPDATE `znote_auction_player` SET `sold` = 1 WHERE `id` = ?;", [$a['id']]);
				// Transfer points to seller account
				$db->execute("
					UPDATE `znote_accounts`
					SET `points` = `points` + ?
					WHERE `account_id` = ?;
				", [$a['points'], $a['original_account_id']]);
			}
		}

		return true;
	});
	// end passive check

	// If we bid or buy a character
	// silently continues to list if buy, back to view if bid
	if ($action === 'bid') {
		//data_dump($_POST, false, "Bid or buying:");
		$zaid = (isset($_POST['zaid']) && (int)$_POST['zaid'] > 0) ? (int)$_POST['zaid'] : false;
		$price = (isset($_POST['price']) && (int)$_POST['price'] > 0) ? (int)$_POST['price'] : false;

		$action = 'list';
		if ($zaid !== false && $price !== false) {
			// The whole read-check-write sequence is locked in one transaction,
			// so two concurrent bids on the same character (or on the same
			// buyer's balance) cannot both succeed off a stale points/bid read.
			$bidOutcome = db()->transaction(function ($db) use ($zaid, $price, $this_account_id, $step, $step_duration) {
				// The account of the buyer, if he can afford what he is trying to pay
				$account = $db->fetchOne("
					SELECT
						`a`.`id`,
						`za`.`points`
					FROM `accounts` a
					INNER JOIN `znote_accounts` za
						ON `a`.`id` = `za`.`account_id`
					WHERE `a`.`id` = ?
					AND `za`.`points` >= ?
					LIMIT 1 FOR UPDATE;
				", [$this_account_id, $price]);
				//data_dump($account, false, "Buyer account:");

				// The character to buy, presuming it isn't sold, buyer isn't the owner, buyer can afford it
				if ($account === false) {
					return false;
				}

				$character = $db->fetchOne("
					SELECT
						`za`.`id` AS `zaid`,
						`za`.`player_id`,
						`za`.`original_account_id`,
						`za`.`bidder_account_id`,
						`za`.`time_begin`,
						`za`.`time_end`,
						`za`.`price`,
						`za`.`bid`,
						`za`.`deposit`,
						`za`.`sold`
					FROM `znote_auction_player` za
					WHERE `za`.`id` = ?
					AND `za`.`sold` = 0
					AND `za`.`original_account_id` != ?
					AND `za`.`price` <= ?
					AND `za`.`bid` + ? <= ?
					LIMIT 1 FOR UPDATE
				", [$zaid, $this_account_id, $price, $step, $price]);
				//data_dump($character, false, "Character to buy:");

				if ($character === false) {
					return false;
				}

				// If auction already have a previous bidder, refund him his points
				if ($character['bid'] > 0 && $character['bidder_account_id'] > 0) {
					$db->execute("
						UPDATE `znote_accounts`
						SET `points` = `points` + ?
						WHERE `account_id` = ?
						LIMIT 1;
					", [$character['bid'], $character['bidder_account_id']]);
					// If previous bidder is not you, increase bidding period by 1 hour
					// (Extending bid war to give bidding competitor a chance to retaliate)
					if ((int)$character['bidder_account_id'] !== (int)$account['id']) {
						$db->execute("
							UPDATE `znote_auction_player`
							SET `time_end` = `time_end` + ?
							WHERE `id` = ?
							LIMIT 1;
						", [$step_duration, $character['zaid']]);
					}
				}
				// Remove points from buyer
				$db->execute("
					UPDATE `znote_accounts`
					SET `points` = `points` - ?
					WHERE `account_id` = ?
					LIMIT 1;
				", [$price, $account['id']]);
				// Update auction, and set new bidder data
				$now = time();
				$db->execute("
					UPDATE `znote_auction_player`
					SET
						`bidder_account_id` = ?,
						`bid` = ?,
						`sold` = CASE WHEN ? >= `time_end` THEN 1 ELSE 0 END
					WHERE `id` = ?
					LIMIT 1;
				", [$account['id'], $price, $now, $character['zaid']]);
				// If character is sold, give points to seller
				if ($now >= $character['time_end']) {
					$db->execute("
						UPDATE `znote_accounts`
						SET `points` = `points` + ?
						WHERE `account_id` = ?
						LIMIT 1;
					", [$character['deposit'] + $price, $character['original_account_id']]);
					return 'sold';
				}
				// If character is not sold, this is a bidding war, we want to send user back to view.
				return 'bidding';
				// Note: Transferring character to the new account etc happens later in $action = 'claim'
			});

			if ($bidOutcome === 'bidding') {
				$action = 'view';
			}
		}
	}

	// See a specific character in auction,
	// silently fallback to list if he doesn't exist or is already sold
	if ($action === 'view') { // View a character in the auction
		if (!isset($zaid)) {
			$zaid = (isset($_GET['zaid']) && (int)$_GET['zaid'] > 0) ? (int)$_GET['zaid'] : false;
		}
		if ($zaid !== false) {
			// Retrieve basic character information
			$character = db()->fetchOne("
				SELECT
					`za`.`id` AS `zaid`,
					`za`.`player_id`,
					`za`.`original_account_id`,
					`za`.`bidder_account_id`,
					`za`.`time_begin`,
					`za`.`time_end`,
					CASE WHEN `za`.`price` > `za`.`bid`
						THEN `za`.`price`
						ELSE `za`.`bid` + ?
					END AS `price`,
					CASE WHEN `za`.`original_account_id` = ?
						THEN 1
						ELSE 0
					END AS `own`,
					CASE WHEN `za`.`original_account_id` = ?
						THEN `p`.`name`
						ELSE ''
					END AS `name`,
					CASE WHEN `za`.`original_account_id` = ?
						THEN `za`.`bid`
						ELSE 0
					END AS `bid`,
					CASE WHEN `za`.`original_account_id` = ?
						THEN `za`.`deposit`
						ELSE 0
					END AS `deposit`,
					`p`.`vocation`,
					`p`.`level`,
					`p`.`balance`,
					`p`.`lookbody` AS `body`,
					`p`.`lookfeet` AS `feet`,
					`p`.`lookhead` AS `head`,
					`p`.`looklegs` AS `legs`,
					`p`.`looktype` AS `type`,
					`p`.`lookaddons` AS `addons`,
					`p`.`maglevel` AS `magic`,
					`p`.`skill_fist` AS `fist`,
					`p`.`skill_club` AS `club`,
					`p`.`skill_sword` AS `sword`,
					`p`.`skill_axe` AS `axe`,
					`p`.`skill_dist` AS `dist`,
					`p`.`skill_shielding` AS `shielding`,
					`p`.`skill_fishing` AS `fishing`
				FROM `znote_auction_player` za
				INNER JOIN `players` p
					ON `za`.`player_id` = `p`.`id`
				WHERE `za`.`id` = ?
				AND `za`.`sold` = 0
				LIMIT 1;
			", [$step, $this_account_id, $this_account_id, $this_account_id, $this_account_id, $zaid]);
			//data_dump($character, false, "Character info");

			if (is_array($character) && !empty($character)) {
				// If the end of the bid is in the future, the bid is currently ongoing
				$bidding_period = ((int)$character['time_end']+1 > time()) ? true : false;
				$player_items = db()->fetchAll("
					SELECT `itemtype`, SUM(`count`) AS `count`
					FROM `player_items`
					WHERE `player_id` = ?
					GROUP BY `itemtype`
					ORDER BY MIN(`pid`) ASC
				", [$character['player_id']]);
				$depot_items = db()->fetchAll("
					SELECT `itemtype`, SUM(`count`) AS `count`
					FROM `player_depotitems`
					WHERE `player_id` = ?
					GROUP BY `itemtype`
					ORDER BY MIN(`pid`) ASC
				", [$character['player_id']]);
				$account = db()->fetchOne("
					SELECT `points`
					FROM `znote_accounts`
					WHERE `account_id` = ?
					AND `points` >= ?
					LIMIT 1;
				", [$this_account_id, $character['price']]);
				?>
				<p><?= t('auc.detailed_info') ?> <a href="/auctionChar.php?action=list"><?= t('auc.go_back_list') ?></a></p>
				<!-- Basic info -->
				<table class="auction_char">
					<tr class="yellow">
						<td>Level</td>
						<td><?= t('common.vocation') ?></td>
						<?php if ($loadOutfits): ?>
							<td>Image</td>
						<?php endif; ?>
						<td>Bank</td>
						<td>Price</td>
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
						<td><?php echo $character['price']; ?> points</td>
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
						<p><?= t('common.you_have') ?> <strong><?php echo $account['points']; ?></strong> shop points remaining.</p>

						<?php if ((int)$character['bidder_account_id'] === $this_account_id): ?>
							<p><strong><?= t('auc.so_far_good') ?></strong>
								<br><?= t('auc.highest_bid') ?> <?php echo (int)$character['price']-$step; ?>
							</p>
							<p>If nobody bids higher than you, this character will be yours in:
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
							<input type="submit" value="<?php echo ($bidding_period) ? 'Bid' : 'Buy'; ?>">
						</form>
					<?php else: ?>
						<?php if ((int)$character['bidder_account_id'] === $this_account_id): ?>
							<p><strong><?= t('auc.so_far_good') ?></strong>
								<br><?= t('auc.highest_bid') ?> <?php echo (int)$character['price']-$step; ?>
							</p>
							<p>If nobody bids higher than you, this character will be yours in:
								<br><?php echo toDuration((int)$character['time_end']-time()); ?>.
							</p>
						<?php else: ?>
							<p><?= t('auc.cannot_afford') ?></p>
						<?php endif; ?>
					<?php endif;
				} else {
					?>
					<p><strong><?= t('auc.is_seller') ?></strong>
						<br><strong>Name:</strong> <a href="/characterprofile.php?name=<?php echo $character['name']; ?>"><?php echo $character['name']; ?></a>
						<br><strong>Price:</strong> <?php echo $character['price']; ?>
						<br><strong>Bid:</strong> <?php echo $character['bid']; ?>
						<br><strong><?= t('auc.deposit') ?></strong> <?php echo $character['deposit']; ?>
						<?php if (!$bidding_period): ?>
							<p>The bidding period has ended, you can wait until someone decides to instantly buy it, or you can reclaim your character to your account.</p>
							<form action="/auctionChar.php" method="POST">
								<input type="hidden" name="action" value="refund">
								<input type="hidden" name="zaid" value="<?php echo $character['zaid']; ?>">
								<input type="submit" value="Reclaim character back to your account">
							</form>
						<?php else: ?>
							<p><?= t('auc.bid_period') ?> <?php echo toDuration($character['time_end']-time()); ?>. After this period, you can reclaim your character if nobody has bid on it.</p>
						<?php endif; ?>
					</p>
					<?php
				}
				?>
				<!-- SKILLS -->
				<table class="auction_skills">
					<tr class="yellow"><td colspan="4"><?= t('auc.skills') ?></td></tr>
					<tr><td>magic</td><td><?php echo $character['magic']; ?></td></tr>
					<tr><td>fist</td><td><?php echo $character['fist']; ?></td></tr>
					<tr><td>club</td><td><?php echo $character['club']; ?></td></tr>
					<tr><td>sword</td><td><?php echo $character['sword']; ?></td></tr>
					<tr><td>axe</td><td><?php echo $character['axe']; ?></td></tr>
					<tr><td>dist</td><td><?php echo $character['dist']; ?></td></tr>
					<tr><td>shielding</td><td><?php echo $character['shielding']; ?></td></tr>
					<tr><td>fishing</td><td><?php echo $character['fishing']; ?></td></tr>
				</table>
				<?php
				$server = $config['shop']['imageServer'];
				$imageType = $config['shop']['imageType'];
				$items = getItemList();
				?>
				<!-- Player items -->
				<?php if (is_array($player_items) && !empty($player_items)): ?>
					<table>
						<tr class="yellow">
							<td colspan="3"><?= t('auc.player_items') ?></td>
						</tr>
						<tr class="yellow">
							<td>Image</td>
							<td>Item</td>
							<td>Count</td>
						</tr>
						<?php foreach($player_items as $item): ?>
							<tr>
								<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$item["itemtype"]), ENT_QUOTES); ?>" alt="Item Image"></td>
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
							<td>Image</td>
							<td>Item</td>
							<td>Count</td>
						</tr>
						<?php foreach($depot_items as $item): ?>
							<tr>
								<td><img src="<?php echo htmlspecialchars(znote_item_image_url((int)$item["itemtype"]), ENT_QUOTES); ?>" alt="Item Image"></td>
								<td><a href="/market.php?compare=<?php echo $item['itemtype']; ?>" target="_BLANK"><?php echo (isset($items[$item['itemtype']])) ? $items[$item['itemtype']] : $item['itemtype']; ?></a></td>
								<td><?php echo $item['count']; ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif;
			} else {
				$action = 'list';
			}
		}
	}

	// If we are adding a character to the list
	// silently continues to list
	if ($action === 'add') {
		$pid = (isset($_POST['pid']) && (int)$_POST['pid'] > 0) ? (int)$_POST['pid'] : false;
		$cost = (isset($_POST['cost']) && (int)$_POST['cost'] > 0) ? (int)$_POST['cost'] : false;
		$deposit = (int)$cost * ($auction['deposit'] / 100);
		$password = SHA1($_POST['password']);

		// Verify values
		$status = false;
		$account = false;
		if ($pid > 0 && $cost >= $auction['lowestPrice']) {
			$account = db()->fetchOne("
				SELECT `a`.`id`, `a`.`password`, `za`.`points`
				FROM `accounts` a
				INNER JOIN `znote_accounts` za
					ON `a`.`id` = `za`.`account_id`
				WHERE `a`.`id` = ?
				AND `a`.`password` = ?
				AND `za`.`points` >= ?
				LIMIT 1
			;", [$this_account_id, $password, $deposit]);
			if (isset($account['password']) && $account['password'] === $password) {
				// Check if player exist, is offline and not already in auction
				// And is not a tutor or a GM+.
				$player = db()->fetchOne("
					SELECT `p`.`id`, `p`.`name`,
					CASE
						WHEN `po`.`player_id` IS NULL
						THEN 0
						ELSE 1
					END AS `online`,
					CASE
						WHEN `za`.`player_id` IS NULL
						THEN 0
						ELSE 1
					END AS `alreadyInAuction`
					FROM `players` p
					LEFT JOIN `players_online` po
						ON `p`.`id` = `po`.`player_id`
					LEFT JOIN `znote_auction_player` za
						ON `p`.`id` = `za`.`player_id`
						AND `p`.`account_id` = `za`.`original_account_id`
						AND `za`.`claimed` = 0
					WHERE `p`.`id` = ?
					AND `p`.`account_id` = ?
					AND `p`.`group_id` = 1
					LIMIT 1
				;", [$pid, $this_account_id]);
				// Verify storage account ID exist
				$storage_account = db()->fetchOne("
					SELECT `id`
					FROM `accounts`
					WHERE `id` = ?
					LIMIT 1;
				", [$auction['storage_account_id']]);
				if ($storage_account === false) {
					data_dump($auction, false, "Configured storage_account_id in config.php does not exist!");
				} else {
					if (isset($player['online']) && $player['online'] == 0) {
						if (isset($player['alreadyInAuction']) && $player['alreadyInAuction'] == 0) {
							$status = true;
						}
					}
				}
			}
		}
		if ($status) {
			$time_begin = time();
			$time_end = $time_begin + ($auction['biddingDuration']);
			// Re-check the balance under lock and spend it with a relative
			// update, so two concurrent listings from the same account cannot
			// both go through on a stale points read.
			db()->transaction(function ($db) use ($pid, $this_account_id, $time_begin, $time_end, $cost, $deposit, $auction, $account) {
				$current = $db->fetchOne("SELECT `points` FROM `znote_accounts` WHERE `account_id` = ? LIMIT 1 FOR UPDATE;", [$account['id']]);
				if (!is_array($current) || (int)$current['points'] < $deposit) {
					return false;
				}

				// Insert row to znote_auction_player
				$db->execute("
					INSERT INTO `znote_auction_player` (
						`player_id`,
						`original_account_id`,
						`bidder_account_id`,
						`time_begin`,
						`time_end`,
						`price`,
						`bid`,
						`deposit`,
						`sold`,
						`claimed`
					) VALUES (?, ?, 0, ?, ?, ?, 0, ?, 0, 0);
				", [$pid, $this_account_id, $time_begin, $time_end, $cost, $deposit]);
				// Move player to storage account
				$db->execute("
					UPDATE `players`
					SET `account_id` = ?
					WHERE `id` = ?
					LIMIT 1;
				", [$auction['storage_account_id'], $pid]);
				// Hide character from public character list (in pidprofile.php)
				$db->execute("
					UPDATE `znote_players`
					SET `hide_char` = 1
					WHERE `player_id` = ?
					LIMIT 1;
				", [$pid]);
				// Remove deposit from account
				$db->execute("
					UPDATE `znote_accounts`
					SET `points` = `points` - ?
					WHERE `account_id` = ?
					LIMIT 1;
				", [$deposit, $account['id']]);

				return true;
			});
		}
		$action = 'list';
	}

	// If we are refunding a player back to its original owner
	// silently continues to list
	if ($action === 'refund') {
		$zaid = (isset($_POST['zaid']) && (int)$_POST['zaid'] > 0) ? (int)$_POST['zaid'] : false;
		//data_dump($_POST, false, "POST");
		if ($zaid !== false) {
			$time = time();
			// Re-verify the same conditions under lock right before writing,
			// so two concurrent refund/claim/bid requests for the same
			// character cannot both act on it.
			db()->transaction(function ($db) use ($zaid, $this_account_id, $time) {
				// If original account is the one trying to get it back,
				// and bidding period is over,
				// and its not labeled as sold
				// and nobody has bid on it
				$character = $db->fetchOne("
					SELECT `player_id`
					FROM `znote_auction_player`
					WHERE `id` = ?
					AND `original_account_id` = ?
					AND `time_end` <= ?
					AND `bidder_account_id` = 0
					AND `bid` = 0
					AND `sold` = 0
					LIMIT 1
					FOR UPDATE
				", [$zaid, $this_account_id, $time]);
				//data_dump($character, false, "Character");
				if ($character === false) {
					return false;
				}

				// Move character to buyer account and give it a new name
				$db->execute("
					UPDATE `players`
					SET `account_id` = ?
					WHERE `id` = ?
					LIMIT 1;
				", [$this_account_id, $character['player_id']]);
				// Set label to sold
				$db->execute("
					UPDATE `znote_auction_player`
					SET `sold` = 1
					WHERE `id` = ?
					LIMIT 1;
				", [$zaid]);
				// Show character in public character list (in characterprofile.php)
				$db->execute("
					UPDATE `znote_players`
					SET `hide_char` = 0
					WHERE `player_id` = ?
					LIMIT 1;
				", [$character['player_id']]);

				return true;
			});
		}
		$action = 'list';
	}

	// If we are claiming a character
	// If validation fails then explain why, but then head over to list regardless of status
	if ($action === 'claim') {
		$zaid = (isset($_POST['zaid']) && (int)$_POST['zaid'] > 0) ? (int)$_POST['zaid'] : false;
		$name = (isset($_POST['name']) && !empty($_POST['name'])) ? getValue($_POST['name'] ?? null) : false;
		$errors = array();
		//data_dump($_POST, $name, "Post data:");
		if ($zaid === false) {
			$errors[] = t('auc.not_found');
		}
		if ((int)$auction['storage_account_id'] === $this_account_id) {
			$errors[] = t('auc.storage_account2');
			if ($is_admin) {
				$errors[] = "ADMIN: The storage account in config.php should not be the same as the admin account.";
			}
		}
		if ($name === false) {
			$errors[] = t('auc.name_required');
		} else {
			// begin name validation
			$name = validate_name($name);
			if (user_character_exist($name) !== false) {
				$errors[] = t('acc.name_taken');
			}
			if (!preg_match("/^[a-zA-Z_ ]+$/", $name)) {
				$errors[] = t('acc.name_letters');
			}
			if (strlen($name) < $config['minL'] || strlen($name) > $config['maxL']) {
				$errors[] = t('acc.name_length', ['min' => $config['minL'], 'max' => $config['maxL']]);
			}
			// name restriction
			$resname = explode(" ", $name);
			foreach($resname as $res) {
				if(in_array(strtolower($res), $config['invalidNameTags'])) {
					$errors[] = t('reg.restricted_word2');
				}
				else if(strlen($res) == 1) {
					$errors[] = t('reg.words_too_short2');
				}
			}
			$name = format_character_name($name);
			// end name validation
			if (empty($errors)) {
				// Make sure you have access to claim this zaid character.
				// And that you haven't already claimed it.
				// And that the character isn't online...
				// Re-verified under lock right before writing, so a concurrent
				// claim/bid on the same auction row cannot race this one.
				$claimed = db()->transaction(function ($db) use ($zaid, $this_account_id, $name) {
					$character = $db->fetchOne("
						SELECT
							`za`.`id` AS `zaid`,
							`za`.`player_id`,
							`p`.`account_id`
						FROM `znote_auction_player` za
						INNER JOIN `players` p
							ON `za`.`player_id` = `p`.`id`
						LEFT JOIN `players_online` po
							ON `p`.`id` = `po`.`player_id`
						WHERE `za`.`id` = ?
						AND `za`.`sold` = 1
						AND `p`.`account_id` != ?
						AND `za`.`bidder_account_id` = ?
						AND `po`.`player_id` IS NULL
						FOR UPDATE
					", [$zaid, $this_account_id, $this_account_id]);
					//data_dump($character, false, "Character");
					if ($character === false) {
						return false;
					}

					// Set character to claimed
					$db->execute("
						UPDATE `znote_auction_player`
						SET `claimed` = 1
						WHERE `id` = ?
					", [$character['zaid']]);
					// Move character to buyer account and give it a new name
					$db->execute("
						UPDATE `players`
						SET `name` = ?,
							`account_id` = ?
						WHERE `id` = ?
						LIMIT 1;
					", [$name, $this_account_id, $character['player_id']]);
					// Show character in public character list (in characterprofile.php)
					$db->execute("
						UPDATE `znote_players`
						SET `hide_char` = 0
						WHERE `player_id` = ?
						LIMIT 1;
					", [$character['player_id']]);
					// Remove character from other players VIP lists
					$db->execute("
						DELETE FROM `account_viplist`
						WHERE `player_id` = ?
					", [$character['player_id']]);
					// Remove the character deathlist
					$db->execute("
						DELETE FROM `player_deaths`
						WHERE `player_id` = ?
					", [$character['player_id']]);

					return true;
				});

				if (!$claimed) {
					$errors[] = "You either don't have access to claim this character, or you have already claimed it, or this character isn't sold yet, or we were unable to find this auction order.";
					if ($is_admin) {
						$errors[] = "ADMIN: ... Or character is online.";
					}
				}
			}
		}
		if (!empty($errors)) {
			//data_dump($errors, false, "Errors:");
			?>
			<table class="auction_error">
				<tr class="yellow">
					<td>#</td>
					<td><?= t('auc.claim_issues') ?></td>
				</tr>
				<?php foreach($errors as $i => $error): ?>
					<tr>
						<td><?php echo $i+1; ?></td>
						<td><?php echo $error; ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php
		}
		$action = 'list';
	}

	// List characters currently in the auction
	if ($action === 'list') {
		// If this account have successfully bought or won an auction
		// Intercept the list action and let the user do claim actions
		$pending = db()->fetchAll("
			SELECT
				`za`.`id` AS `zaid`,
				CASE WHEN `za`.`price` > `za`.`bid`
					THEN `za`.`price`
					ELSE `za`.`bid`
				END AS `price`,
				`za`.`time_begin`,
				`za`.`time_end`,
				`p`.`vocation`,
				`p`.`level`,
				`p`.`lookbody` AS `body`,
				`p`.`lookfeet` AS `feet`,
				`p`.`lookhead` AS `head`,
				`p`.`looklegs` AS `legs`,
				`p`.`looktype` AS `type`,
				`p`.`lookaddons` AS `addons`
			FROM `znote_auction_player` za
			INNER JOIN `players` p
				ON `za`.`player_id` = `p`.`id`
			WHERE `p`.`account_id` = ?
			AND `za`.`claimed` = 0
			AND `za`.`sold` = 1
			AND `za`.`bidder_account_id` = ?
			ORDER BY `p`.`level` desc
		", [$auction['storage_account_id'], $this_account_id]);
		//data_dump($pending, false, "Pending characters:");
		if ($pending !== false) {
			?>
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
			<?php
		}

		// Show the list
		$characters = db()->fetchAll("
			SELECT
				`za`.`id` AS `zaid`,
				CASE WHEN `za`.`price` > `za`.`bid`
					THEN `za`.`price`
					ELSE `za`.`bid` + ?
				END AS `price`,
				`za`.`time_begin`,
				`za`.`time_end`,
				`p`.`vocation`,
				`p`.`level`,
				`p`.`lookbody` AS `body`,
				`p`.`lookfeet` AS `feet`,
				`p`.`lookhead` AS `head`,
				`p`.`looklegs` AS `legs`,
				`p`.`looktype` AS `type`,
				`p`.`lookaddons` AS `addons`
			FROM `znote_auction_player` za
			INNER JOIN `players` p
				ON `za`.`player_id` = `p`.`id`
			WHERE `p`.`account_id` = ?
			AND `za`.`sold` = 0
			ORDER BY `p`.`level` desc;
		", [$step, $auction['storage_account_id']]);
		//data_dump($characters, false, "List characters");
		if ($is_admin) {
			?>
			<p>Admin: <a href="/admin/index.php?p=auction"><?= t('auc.history') ?></a></p>
			<?php
		}
		if (is_array($characters) && !empty($characters)):
			?>
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
			<?php
		endif;
		?>
		<p><a href="/auctionChar.php?action=create"><?= t('auc.add') ?></a>.</p>
		<?php

	} elseif ($action === 'create') { // Add player to auction view
		$minToCreate = (int)ceil(($auction['lowestPrice'] / 100) * $auction['deposit']);
		$own_characters = db()->fetchAll("
			SELECT
				`p`.`id`,
				`p`.`name`,
				`p`.`level`,
				`p`.`vocation`,
				`a`.`points`
			FROM `players` p
			INNER JOIN `znote_accounts` a
				ON `p`.`account_id` = `a`.`account_id`
			LEFT JOIN `znote_auction_player` za
				ON `p`.`id` = `za`.`player_id`
				AND `p`.`account_id` = `za`.`original_account_id`
				AND `za`.`claimed` = 0
			LEFT JOIN `players_online` po
				ON `p`.`id` = `po`.`player_id`
			WHERE `p`.`account_id` = ?
			AND `za`.`player_id` IS NULL
			AND `po`.`player_id` IS NULL
			AND `p`.`level` >= ?
			AND `a`.`points` >= ?
		;", [$this_account_id, $auction['lowestLevel'], $minToCreate]);
		//data_dump($own_characters, false, "own_chars");

		if (is_array($own_characters) && !empty($own_characters)) {
			$max = ($own_characters[0]['points'] / $auction['deposit']) * 100;
			?>
			<p><a href="/auctionChar.php?action=list"><?= t('auc.go_back_list') ?></a></p>
			<form action="/auctionChar.php" method="POST">
				<input type="hidden" name="action" value="add">
				<p><?= t('auc.char_offline') ?></p>
				<select name="pid">
					<?php if(is_array($own_characters) && !empty($own_characters))
					foreach($own_characters as $char): ?>
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
			<?php
		} else {
			?>
			<p><a href="/auctionChar.php?action=list"><?= t('auc.go_back_list') ?></a></p>
			<p>Your account does not follow the required rules to sell characters.
				<br>1. Minimum level: <?php echo $auction['lowestLevel']; ?>
				<br>2. Minimum already earned shop points: <?php echo $minToCreate; ?>
				<br>3. Eligible characters must be offline.
			</p>
			<?php
		}
	}
} else echo "<p>". t('auc.disabled2'). "</p>";
theme_close(); ?>
