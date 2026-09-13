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
<?php view('auction_header'); ?>
<?php
// Import from config:
$auction = $config['shop_auction'];
$loadOutfits = ($config['show_outfits']['highscores']) ? true : false;
$this_account_id = (int)$session_user_id;
$is_admin = is_admin($user_data);

// If character auction is enabled in config.php
if ($auction['characterAuction']) {

	if (znote_server_adapter()->normalizedEngine() !== 'TFS_10') {
		view('auction_wrong_engine');
		theme_close();
		die();
	}
	if ((int)$auction['storage_account_id'] === (int)$this_account_id) {
		view('auction_storage_error');
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

				$items = getItemList();

				view('auction_view', [
					'character' => $character,
					'account' => $account,
					'bidding_period' => $bidding_period,
					'loadOutfits' => $loadOutfits,
					'step' => $step,
					'this_account_id' => $this_account_id,
					'player_items' => $player_items,
					'depot_items' => $depot_items,
					'items' => $items,
				]);
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
			view('auction_claim_errors', ['errors' => $errors]);
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

		view('auction_list', [
			'pending' => $pending,
			'characters' => $characters,
			'loadOutfits' => $loadOutfits,
			'is_admin' => $is_admin,
		]);

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

		$max = (is_array($own_characters) && !empty($own_characters))
			? ($own_characters[0]['points'] / $auction['deposit']) * 100
			: 0;

		view('auction_create', [
			'own_characters' => $own_characters,
			'auction' => $auction,
			'minToCreate' => $minToCreate,
			'max' => $max,
		]);
	}
} else {
	view('auction_disabled');
}
theme_close(); ?>
