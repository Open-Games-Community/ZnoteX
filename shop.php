<?php require_once 'engine/init.php';
theme_open();

if (isset($_GET['callback']) && $_GET['callback'] === 'processing') {
	echo '<script>alert("Seu pagamento está sendo processado pelo PagSeguro...");</script>';
}

// Import from config:
$shop = $config['shop'];
if ($shop['loginToView'] === true) protect_page();
$loggedin = user_logged_in();

function shop_db_offer_columns(): array {
	$columns = array();
	$rows = mysql_select_multi("SHOW COLUMNS FROM `znote_shop_offers`;");

	if (is_array($rows)) {
		foreach ($rows as $row) {
			if (!empty($row['Field'])) {
				$columns[(string)$row['Field']] = true;
			}
		}
	}

	return $columns;
}

function shop_load_db_offers(): array {
	$columns = shop_db_offer_columns();
	$where = !empty($columns['active']) ? "WHERE `active` = 1" : "";
	$order = !empty($columns['sort_order'])
		? "ORDER BY `sort_order` ASC, `id` ASC"
		: "ORDER BY `id` ASC";

	$rows = mysql_select_multi("
		SELECT `id`, `type`, `itemid`, `count`, `description`, `points`
		FROM `znote_shop_offers`
		{$where}
		{$order};
	");

	if (!is_array($rows)) {
		return array();
	}

	$offers = array();
	foreach ($rows as $row) {
		$itemid = (int)$row['itemid'];
		if ((int)$row['type'] === 5 && $itemid > 0) {
			$male = (int)floor($itemid / 10000);
			$female = (int)($itemid % 10000);
			$itemid = array($male, $female);
		}

		$offers[(int)$row['id']] = array(
			'type' => (int)$row['type'],
			'itemid' => $itemid,
			'count' => (int)$row['count'],
			'description' => (string)$row['description'],
			'points' => (int)$row['points'],
		);
	}

	return $offers;
}

$shop_list = shop_load_db_offers();

if ($loggedin === true) {
	if (!empty($_POST['buy']) && isset($_SESSION['shop_session']) && $_SESSION['shop_session'] == ($_POST['session'] ?? null)) {
		$time = time();
		$player_points = (int)$user_znote_data['points'];
		$cid = (int)$user_data['id'];
		// Sanitizing post, setting default buy value
		$buy = false;
		$post = (int)$_POST['buy'];

		foreach ($shop_list as $key => $value) {
			if ($key === $post) {
				$buy = $value;
			}
		}
		if ($buy === false) die("Error: Shop offer ID mismatch.");

		// Plugins may adjust what this offer costs - a discount code, a happy
		// hour, a loyalty rebate. The filtered value is what gets checked,
		// charged and logged, so the three can never disagree.
		$buy['points'] = max(0, (int)znote_hook_filter('shop.price', (int)$buy['points'], array(
			'account_id' => $cid,
			'offer_id'   => $post,
			'offer'      => $buy,
		)));

		// Verify that user can afford this offer.
		if ($player_points >= $buy['points']) {
			$data = mysql_select_single("SELECT `points` FROM `znote_accounts` WHERE `account_id`='$cid';");
			if (!$data) die("0: Account is not converted to work with Znote AAC");
			$old_points = $data['points'];
			if ((int)$old_points != (int)$player_points) die("1: Failed to equalize your points.");
			// Remove points if they can afford
			// Give points to user
			$expense_points = $buy['points'];
			$new_points = $old_points - $expense_points;
			$update_account = mysql_update("UPDATE `znote_accounts` SET `points`='$new_points' WHERE `account_id`='$cid'");

			$data = mysql_select_single("SELECT `points` FROM `znote_accounts` WHERE `account_id`='$cid';");
			$verify = $data['points'];
			if ((int)$old_points == (int)$verify) die("2: Failed to equalize your points.". var_dump((int)$old_points, (int)$verify, $new_points, $expense_points));

			// If this is an outfit offer, convert array into an integer.
			if ($buy['type'] == 5) {
				if (is_array($buy['itemid'])) {
					if (COUNT($buy['itemid']) == 2) $buy['itemid'] = ($buy['itemid'][0] * 1000) + $buy['itemid'][1];
					else $buy['itemid'] = $buy['itemid'][0];
				}
			}

			// Do the magic (insert into db, or change sex etc)
			// If type is 2 or 3
			if ($buy['type'] == 2) {
				// Add premium days to account
				user_account_add_premdays($cid, $buy['count']);
				echo '<font color="green" size="4">You now have '.$buy['count'].' additional days of premium membership.</font>';
			} else if ($buy['type'] == 3) {
				// Character Gender
				mysql_insert("INSERT INTO `znote_shop_orders` (`account_id`, `type`, `itemid`, `count`, `time`) VALUES ('$cid', '". $buy['type'] ."', '". $buy['itemid'] ."', '". $buy['count'] ."', '$time')");
				echo '<font color="green" size="4">'. t('shop.gender_unlocked') .'</font>';
			} else if ($buy['type'] == 4) {
				// Character Name
				mysql_insert("INSERT INTO `znote_shop_orders` (`account_id`, `type`, `itemid`, `count`, `time`) VALUES ('$cid', '". $buy['type'] ."', '". $buy['itemid'] ."', '". $buy['count'] ."', '$time')");
				echo '<font color="green" size="4">'. t('shop.name_unlocked') .'</font>';
			} else {
				mysql_insert("INSERT INTO `znote_shop_orders` (`account_id`, `type`, `itemid`, `count`, `time`) VALUES ('$cid', '". $buy['type'] ."', '". $buy['itemid'] ."', '". $buy['count'] ."', '$time')");
				echo '<font color="green" size="4">Your order is ready to be delivered. Write this command in-game to get it: [!shop].<br>Make sure you are in depot and can carry it before executing the command!</font>';
			}

			// No matter which type, we will always log it.
			mysql_insert("INSERT INTO `znote_shop_logs` (`account_id`, `player_id`, `type`, `itemid`, `count`, `points`, `time`) VALUES ('$cid', '0', '". $buy['type'] ."', '". $buy['itemid'] ."', '". $buy['count'] ."', '". $buy['points'] ."', '$time')");

	// Plugins can react to a purchase - a coupon ledger, a Discord message,
	// a loyalty counter. They cannot change what was bought; this is a
	// notification, fired after the points have already been taken.
			znote_hook('shop.purchased', array(
				'account_id' => $cid,
				'offer_id'   => $post,
				'type'       => $buy['type'],
				'itemid'     => $buy['itemid'],
				'count'      => $buy['count'],
				'points'     => $buy['points'],
			));

		} else echo '<font color="red" size="4">You need more points, this offer cost '.$buy['points'].' points.</font>';
		//var_dump($buy);
		//echo '<font color="red" size="4">'. $_POST['buy'] .'</font>';
	}
}

view('shop');

theme_close();
