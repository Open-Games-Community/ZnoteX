<?php require_once 'engine/init.php';
theme_open();

/**
 * Public ban list.
 *
 * Two completely different shapes depending on the engine:
 *   TFS 1.x / 1.6 / Canary  account_bans + ip_bans + player_namelocks
 *   TFS 0.x / OTHire        one `bans` table with a type column
 *
 * IP bans are counted but never listed: an address is personal data and
 * publishing it helps nobody. Names and reasons are what players expect to see.
 *
 * Prepared for the view:
 *   $bansSupported  false when the engine has no ban tables this page can read
 *   $accountBans    [name, reason, banned_at, expires_at, by]
 *   $nameLocks      [name, reason, at, by]
 *   $ipBanCount     how many address bans are active, as a number only
 */

$engine   = $config['ServerEngine'];
$isModern = in_array($engine, array('TFS_10'), true); // TFS_16 and CANARY normalise to this

$bansSupported = true;
$accountBans   = array();
$nameLocks     = array();
$ipBanCount    = 0;

$now = time();

if ($isModern) {

	$isOthire   = false;
	$accNameCol = '`a`.`name`';

	// Characters are shown rather than the account name: that is what other
	// players recognise, and one account may have several.
	$rows = mysql_select_multi("
		SELECT {$accNameCol} AS `account_name`,
		       `b`.`reason`, `b`.`banned_at`, `b`.`expires_at`,
		       (SELECT `name` FROM `players` WHERE `account_id` = `b`.`account_id` ORDER BY `level` DESC LIMIT 1) AS `character_name`
		FROM `account_bans` `b`
		INNER JOIN `accounts` `a` ON `a`.`id` = `b`.`account_id`
		ORDER BY `b`.`banned_at` DESC
		LIMIT 200;
	");

	if (is_array($rows)) {
		foreach ($rows as $row) {
			$accountBans[] = array(
				'name'       => (string)($row['character_name'] !== null && $row['character_name'] !== ''
					? $row['character_name'] : $row['account_name']),
				'reason'     => (string)$row['reason'],
				'banned_at'  => (int)$row['banned_at'],
				'expires_at' => (int)$row['expires_at'],
			);
		}
	}

	$locks = mysql_select_multi("
		SELECT `p`.`name`, `n`.`reason`, `n`.`namelocked_at`
		FROM `player_namelocks` `n`
		INNER JOIN `players` `p` ON `p`.`id` = `n`.`player_id`
		ORDER BY `n`.`namelocked_at` DESC
		LIMIT 100;
	");
	if (is_array($locks)) {
		foreach ($locks as $row) {
			$nameLocks[] = array(
				'name'   => (string)$row['name'],
				'reason' => (string)$row['reason'],
				'at'     => (int)$row['namelocked_at'],
			);
		}
	}

	$ipBanCount = (int)(mysql_select_single("
		SELECT COUNT(*) AS `c` FROM `ip_bans`
		WHERE `expires_at` <= 0 OR `expires_at` > {$now};
	")['c'] ?? 0);

} elseif (in_array($engine, array('TFS_02', 'TFS_03', 'OTHIRE'), true)) {

	// One table, a type column: 1 = IP, 2 = namelock, 3 = account, 5 = deletion.
	$rows = mysql_select_multi("
		SELECT `type`, `value`, `param`, `expires`, `added`, `comment`, `reason_id`
		FROM `bans`
		WHERE `active` = 1 OR `active` IS NULL
		ORDER BY `added` DESC
		LIMIT 200;
	");

	if (is_array($rows)) {
		foreach ($rows as $row) {
			$type = (int)$row['type'];

			if ($type === 1) {
				$ipBanCount++;
				continue;
			}

			// value is a player id for namelocks, an account id for bans.
			$name = '';
			if ($type === 2) {
				$player = mysql_select_single("SELECT `name` FROM `players` WHERE `id` = " . (int)$row['value'] . " LIMIT 1;");
				$name   = is_array($player) ? (string)$player['name'] : '';
			} else {
				$player = mysql_select_single("SELECT `name` FROM `players` WHERE `account_id` = " . (int)$row['value'] . " ORDER BY `level` DESC LIMIT 1;");
				$name   = is_array($player) ? (string)$player['name'] : '';
			}

			if ($name === '') {
				continue;
			}

			$entry = array(
				'name'       => $name,
				'reason'     => (string)$row['comment'],
				'banned_at'  => (int)$row['added'],
				'expires_at' => (int)$row['expires'],
				'at'         => (int)$row['added'],
			);

			if ($type === 2) {
				$nameLocks[] = $entry;
			} else {
				$accountBans[] = $entry;
			}
		}
	}

} else {
	$bansSupported = false;
}

view('bans');

theme_close();
