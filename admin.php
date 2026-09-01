<?php
require_once 'engine/init.php';
include 'layout/overall/header.php';

protect_page();
admin_only($user_data);

if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function intv($v): int {
	return is_numeric($v) ? (int)$v : 0;
}

$enc = 100;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
		http_response_code(400);
		die('Invalid CSRF token');
	}

	if (!empty($_POST['ban_char'])) {

		$char = trim($_POST['ban_char']);

		if (user_character_exist($char)) {

			$type    = intv($_POST['ban_type']   ?? 0) - $enc;
			$action  = intv($_POST['ban_action'] ?? 0) - $enc;
			$reason  = intv($_POST['ban_reason'] ?? 0) - $enc;
			$time    = intv($_POST['ban_time']   ?? 0) - $enc;
			$comment = substr(trim($_POST['ban_comment'] ?? ''), 0, 60);

			if (set_rule_violation($char, $type, $action, $reason, $time, $comment)) {
				$errors[] = "Violation set for {$char}.";
			} else {
				$errors[] = "Failed to set violation. Check website character.";
			}

		} else {
			$errors[] = "Character {$char} does not exist.";
		}
	}

	if (!empty($_POST['del_name'])) {
		$char = trim($_POST['del_name']);
		if (user_character_exist($char)) {
			user_delete_character(user_character_id($char));
			$errors[] = "Character {$char} permanently deleted.";
		} else {
			$errors[] = "Character {$char} does not exist.";
		}
	}
	if (!empty($_POST['reset_pass']) && !empty($_POST['new_pass'])) {
		$char = trim($_POST['reset_pass']);
		if (user_character_exist($char)) {
			$accId = user_character_account_id($char);

			if ($accId !== $session_user_id) {
				if (in_array($config['ServerEngine'], ['TFS_02','TFS_10','OTHIRE'])) {
					user_change_password($accId, $_POST['new_pass']);
				} else {
					user_change_password03($accId, $_POST['new_pass']);
				}
				$errors[] = "Password reset for {$char}.";
			} else {
				header('Location: changepassword.php');
				exit;
			}
		}
	}

	if (!empty($_POST['points_char']) && !empty($_POST['points_value'])) {
		$char = trim($_POST['points_char']);
		$points = intv($_POST['points_value']);

		$acc = mysql_select_single("
			SELECT account_id FROM players
			WHERE name='" . mysql_znote_escape_string($char) . "'
			LIMIT 1
		");

		if ($acc) {
			$znote = mysql_select_single("
				SELECT points FROM znote_accounts
				WHERE account_id={$acc['account_id']}
			");

			$newPoints = intv($znote['points']) + $points;

			mysql_update("
				UPDATE znote_accounts
				SET points={$newPoints}
				WHERE account_id={$acc['account_id']}
			");

			$errors[] = "{$points} points added to {$char}.";
		}
	}

	if (!empty($_POST['position_name']) && isset($_POST['position_type'])) {
		$char = trim($_POST['position_name']);
		$pos  = $_POST['position_type'];

		if (user_character_exist($char) && isset($config['ingame_positions'][$pos])) {

			if (in_array($config['ServerEngine'], ['TFS_02','TFS_10','OTHIRE'])) {
				set_ingame_position($char, $pos);
			} else {
				set_ingame_position03($char, $pos);
			}

			$errors[] = "{$char} received position {$config['ingame_positions'][$pos]}.";
		}
	}
	if (isset($_POST['from'], $_POST['to'])) {

		$where = '';
		if ($_POST['from'] === 'only') {
			if (empty($_POST['player_name']) || !user_character_exist($_POST['player_name'])) {
				$errors[] = 'Invalid character for teleport.';
			} else {
				$where = "WHERE name='" . mysql_znote_escape_string($_POST['player_name']) . "'";
			}
		}

		if (!$errors) {
			$sql = "UPDATE players SET ";

			if ($_POST['to'] === 'home') {
				$sql .= "posx=0,posy=0,posz=0 ";
			} elseif ($_POST['to'] === 'town') {
				$sql .= "posx=0,posy=0,posz=0,town_id=" . intv($_POST['town']);
			} elseif ($_POST['to'] === 'xyz') {
				$sql .= "posx=" . intv($_POST['x']) . ",posy=" . intv($_POST['y']) . ",posz=" . intv($_POST['z']);
			}

			mysql_update($sql . " " . $where);
			$errors[] = "Teleport executed.";
		}
	}
}

if ($errors) {
	echo '<div style="color:red;font-weight:bold;">' . output_errors($errors) . '</div>';
}

$basic = user_znote_data('version', 'installed', 'cached');
if ($basic['version'] !== $version) {
	mysql_update("UPDATE znote SET version='{$version}'");
}
?>

<h1>Admin Page</h1>
<p>
Running Znote AAC Version: <?= h($basic['version']) ?><br>
Last cached: <?= h(getClock($basic['cached'], true)) ?>
</p>

<!-- FORMS -->
<ul>
	<li><b>Delete character</b>
		<form method="post">
			<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
			<input name="del_name" placeholder="Character name">
		</form>
	</li>

	<li><b>Reset password</b>
		<form method="post">
			<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
			<input name="reset_pass" placeholder="Character name">
			<input name="new_pass" placeholder="New password">
			<input type="submit">
		</form>
	</li>

	<li><b>Give shop points</b>
		<form method="post">
			<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
			<input name="points_char" placeholder="Character">
			<input name="points_value" placeholder="Points">
			<input type="submit">
		</form>
	</li>
</ul>

<div id="twitter"><?php include 'twtrNews.php'; ?></div>

<?php include 'layout/overall/footer.php'; ?>