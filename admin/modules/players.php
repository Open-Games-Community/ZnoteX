<?php
/**
 * Title: Player Tools
 * Icon: fa-users
 * Group: Players
 * Order: 20
 * Description: Punish, move and maintain characters and their accounts.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

// Select values are offset by this so a "0" option is still truthy in the
// original admin.php handler. Kept as-is so behaviour does not change.
$enc = 100;

$legacyEngines = ['TFS_02', 'TFS_10', 'OTHIRE'];

function acp_players_table_exists(string $table): bool {
	return mysql_select_single("SHOW TABLES LIKE '" . esc($table) . "';") !== false;
}

function acp_players_column_exists(string $table, string $column): bool {
	return mysql_select_single("
		SHOW COLUMNS FROM `" . esc($table) . "`
		LIKE '" . esc($column) . "';
	") !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// ------------------------------------------------- Rule violation / ban
	if (!empty($_POST['ban_char'])) {
		$char = trim((string)$_POST['ban_char']);

		if (user_character_exist($char)) {
			$type    = intv($_POST['ban_type']   ?? 0) - $enc;
			$action  = intv($_POST['ban_action'] ?? 0) - $enc;
			$reason  = intv($_POST['ban_reason'] ?? 0) - $enc;
			$time    = intv($_POST['ban_time']   ?? 0) - $enc;
			$comment = substr(trim((string)($_POST['ban_comment'] ?? '')), 0, 60);

			if (set_rule_violation($char, $type, $action, $reason, $time, $comment)) {
				acp_flash_success('Violation set for <strong>' . h($char) . '</strong>.');
			} else {
				acp_flash_error('Failed to set violation. Check the $config[\'website_char\'] character.');
			}
		} else {
			acp_flash_error('Character <strong>' . h($char) . '</strong> does not exist.');
		}

		acp_redirect('players');
	}

	// ----------------------------------------------------- Delete character
	if (!empty($_POST['del_name'])) {
		$char = trim((string)$_POST['del_name']);

		if (user_character_exist($char)) {
			user_delete_character(user_character_id($char));
			acp_flash_success('Character <strong>' . h($char) . '</strong> permanently deleted.');
		} else {
			acp_flash_error('Character <strong>' . h($char) . '</strong> does not exist.');
		}

		acp_redirect('players');
	}

	// ------------------------------------------------------- Reset password
	if (!empty($_POST['reset_pass']) && !empty($_POST['new_pass'])) {
		$char = trim((string)$_POST['reset_pass']);

		if (user_character_exist($char)) {
			$accId = user_character_account_id($char);

			// Changing your own password goes through the normal page, so the
			// session stays consistent.
			if ($accId !== $session_user_id) {
				if (in_array($config['ServerEngine'], $legacyEngines, true)) {
					user_change_password($accId, $_POST['new_pass']);
				} else {
					user_change_password03($accId, $_POST['new_pass']);
				}
				acp_flash_success('Password reset for <strong>' . h($char) . '</strong>.');
				acp_redirect('players');
			}

			header('Location: ../changepassword.php');
			exit;
		}

		acp_flash_error('Character <strong>' . h($char) . '</strong> does not exist.');
		acp_redirect('players');
	}

	// ---------------------------------------------------- Rename character
	if (!empty($_POST['rename_character'])) {
		$currentName = trim((string)($_POST['rename_current'] ?? ''));
		$newNameInput = trim((string)($_POST['rename_new'] ?? ''));
		$newName = validate_name($newNameInput);
		$problems = [];

		$onlineSelect = acp_players_column_exists('players', 'online') ? ', `online`' : '';
		$character = $currentName !== '' ? mysql_select_single("
			SELECT `id`, `name`{$onlineSelect}
			FROM `players`
			WHERE `name` = '" . esc($currentName) . "'
			LIMIT 1;
		") : false;

		if (!is_array($character)) {
			$problems[] = 'The current character does not exist.';
		}
		if ($newName === false) {
			$problems[] = 'The new name contains too many words.';
		} else {
			$newName = format_character_name($newName);
			if (!preg_match('/^[a-zA-Z ]+$/', $newName)) {
				$problems[] = 'The new name may only contain letters and spaces.';
			}
			$minLength = (int)($config['minL'] ?? 3);
			$maxLength = (int)($config['maxL'] ?? 20);
			if (strlen($newName) < $minLength || strlen($newName) > $maxLength) {
				$problems[] = "The new name must be between {$minLength} and {$maxLength} characters.";
			}
			foreach (explode(' ', $newName) as $word) {
				if (strlen($word) === 1) {
					$problems[] = 'Every word in the new name must contain at least two letters.';
					break;
				}
				if (in_array(strtolower($word), $config['invalidNameTags'] ?? [], true)) {
					$problems[] = 'The new name contains a restricted word.';
					break;
				}
			}
			if (in_array(strtolower($newName), $config['creatureNameTags'] ?? [], true)) {
				$problems[] = 'The new name is reserved for a creature.';
			}
		}

		if (is_array($character) && isset($character['online']) && (int)$character['online'] !== 0) {
			$problems[] = 'The character must be offline before it can be renamed.';
		}

		if (!$problems && is_array($character)) {
			$characterId = (int)$character['id'];
			$duplicate = mysql_select_single("
				SELECT `id` FROM `players`
				WHERE `name` = '" . esc((string)$newName) . "'
				AND `id` <> {$characterId}
				LIMIT 1;
			");
			if (is_array($duplicate)) {
				$problems[] = 'Another character already uses that name.';
			}
		}

		if ($problems) {
			acp_flash_error(implode(' ', array_map('h', $problems)));
			acp_redirect('players');
		}

		$oldName = (string)$character['name'];
		$characterId = (int)$character['id'];
		if (strcasecmp($oldName, (string)$newName) === 0 && $oldName === $newName) {
			acp_flash_error('The current and new character names are identical.');
			acp_redirect('players');
		}

		if (!mysql_update("
			UPDATE `players`
			SET `name` = '" . esc((string)$newName) . "'
			WHERE `id` = {$characterId}
			LIMIT 1;
		")) {
			acp_flash_error('The character could not be renamed.');
			acp_redirect('players');
		}

		foreach (['znote_forum_threads', 'znote_forum_posts'] as $forumTable) {
			if (acp_players_table_exists($forumTable)) {
				mysql_update("
					UPDATE `{$forumTable}`
					SET `player_name` = '" . esc((string)$newName) . "'
					WHERE `player_id` = {$characterId};
				");
			}
		}

		znote_hook('character.renamed', [
			'player_id' => $characterId,
			'old_name' => $oldName,
			'new_name' => $newName,
		]);
		acp_flash_success('<strong>' . h($oldName) . '</strong> renamed to <strong>' . h((string)$newName) . '</strong>.');
		acp_redirect('players');
	}

	// ---------------------------------------------------------- Give points
	if (!empty($_POST['points_char']) && !empty($_POST['points_value'])) {
		$char   = trim((string)$_POST['points_char']);
		$points = intv($_POST['points_value']);

		$acc = mysql_select_single("
			SELECT `account_id` FROM `players`
			WHERE `name` = '" . esc($char) . "'
			LIMIT 1;
		");

		if (is_array($acc)) {
			$accountId = (int)$acc['account_id'];

			$znote = mysql_select_single("
				SELECT `points` FROM `znote_accounts`
				WHERE `account_id` = {$accountId}
				LIMIT 1;
			");

			if (is_array($znote)) {
				$newPoints = intv($znote['points']) + $points;

				mysql_update("
					UPDATE `znote_accounts`
					SET `points` = {$newPoints}
					WHERE `account_id` = {$accountId};
				");

				acp_flash_success(
					h((string)$points) . ' points given to <strong>' . h($char) . '</strong>'
					. ' (new balance: ' . h((string)$newPoints) . ').'
				);
			} else {
				acp_flash_error('No znote_accounts row for that account - nothing was credited.');
			}
		} else {
			acp_flash_error('Character <strong>' . h($char) . '</strong> does not exist.');
		}

		acp_redirect('players');
	}

	// ------------------------------------------------------ Ingame position
	if (!empty($_POST['position_name']) && isset($_POST['position_type'])) {
		$char = trim((string)$_POST['position_name']);
		$pos  = $_POST['position_type'];

		if (user_character_exist($char) && isset($config['ingame_positions'][$pos])) {
			if (in_array($config['ServerEngine'], $legacyEngines, true)) {
				set_ingame_position($char, $pos);
			} else {
				set_ingame_position03($char, $pos);
			}

			acp_flash_success(
				'<strong>' . h($char) . '</strong> is now '
				. h($config['ingame_positions'][$pos]) . '.'
			);
		} else {
			acp_flash_error('Unknown character or position.');
		}

		acp_redirect('players');
	}

	// ------------------------------------------------------------ Teleport
	if (isset($_POST['from'], $_POST['to'])) {
		$from = (string)$_POST['from'];
		$to   = (string)$_POST['to'];

		$where = '';
		$fail  = false;

		if ($from === 'only') {
			$target = trim((string)($_POST['player_name'] ?? ''));
			if ($target === '' || !user_character_exist($target)) {
				acp_flash_error('Invalid character for teleport.');
				$fail = true;
			} else {
				$where = "WHERE `name` = '" . esc($target) . "'";
			}
		}

		if (!$fail) {
			$set = null;

			if ($to === 'home') {
				$set = '`posx`=0, `posy`=0, `posz`=0';
			} elseif ($to === 'town') {
				$set = '`posx`=0, `posy`=0, `posz`=0, `town_id`=' . intv($_POST['town'] ?? 0);
			} elseif ($to === 'xyz') {
				$set = '`posx`=' . intv($_POST['x'] ?? 0)
					. ', `posy`=' . intv($_POST['y'] ?? 0)
					. ', `posz`=' . intv($_POST['z'] ?? 0);
			}

			// Anything else would have produced "UPDATE players SET " and a
			// SQL error, so refuse instead of guessing.
			if ($set === null) {
				acp_flash_error('Unknown teleport destination.');
			} else {
				mysql_update("UPDATE `players` SET {$set} {$where};");
				acp_flash_success($from === 'only'
					? 'Character teleported.'
					: 'All characters teleported.');
			}
		}

		acp_redirect('players');
	}
}
?>

<div class="acp-grid acp-grid--2">

	<!-- ---------------------------------------------------- Give points -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Give shop points</h2>
			<p>Credited to the character's account</p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="points_char">Character</label>
						<input class="acp-input" id="points_char" name="points_char" placeholder="Character name" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="points_value">Points</label>
						<input class="acp-input" id="points_value" name="points_value" type="number" value="10" required>
					</div>
				</div>
				<p class="acp-hint">A negative value subtracts points.</p>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-diamond"></i> Give points</button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------- Reset password -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Reset account password</h2>
			<p>Identified by one of its characters</p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="reset_pass">Character</label>
						<input class="acp-input" id="reset_pass" name="reset_pass" placeholder="Character name" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="new_pass">New password</label>
						<input class="acp-input" id="new_pass" name="new_pass" type="text" placeholder="New password" required>
					</div>
				</div>
				<p class="acp-hint">Your own account is redirected to the normal change-password page.</p>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--amber" type="submit"><i class="fa fa-key"></i> Reset password</button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------ Rename character -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Rename character</h2>
			<p>The character must be offline</p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="rename_character" value="1">
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="rename_current">Current name</label>
						<input class="acp-input" id="rename_current" name="rename_current" placeholder="Current character name" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="rename_new">New name</label>
						<input class="acp-input" id="rename_new" name="rename_new" placeholder="New character name" required>
					</div>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--blue" type="submit"><i class="fa fa-pencil"></i> Rename character</button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------ Ingame position -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Set ingame position</h2>
			<p>Promote or demote a character's group</p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="position_name">Character</label>
						<input class="acp-input" id="position_name" name="position_name" placeholder="Character name" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="position_type">Position</label>
						<select class="acp-select" id="position_type" name="position_type">
							<?php foreach (($config['ingame_positions'] ?? []) as $pid => $pname): ?>
								<option value="<?= h((string)$pid) ?>"><?= h($pname) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--blue" type="submit"><i class="fa fa-star"></i> Set position</button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------ Delete character -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Delete character</h2>
			<p>Immediate and permanent</p>
		</header>
		<div class="acp-card-body">
			<form method="post" data-confirm="Permanently delete this character? This cannot be undone.">
				<?= acp_csrf_field() ?>
				<div class="acp-field">
					<label class="acp-label" for="del_name">Character</label>
					<input class="acp-input" id="del_name" name="del_name" placeholder="Character name" required>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--red" type="submit"><i class="fa fa-trash"></i> Delete character</button>
				</div>
			</form>
		</div>
	</section>
</div>

<!-- ------------------------------------------------------ Rule violation -->
<section class="acp-card">
	<header class="acp-card-head">
		<h2>Rule violation</h2>
		<p>Writes the ban straight into the server's ban table</p>
	</header>
	<div class="acp-card-body">
		<form method="post" data-confirm="Apply this punishment?">
			<?= acp_csrf_field() ?>
			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="ban_char">Character</label>
					<input class="acp-input" id="ban_char" name="ban_char" placeholder="Character name" required>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_type">Type</label>
					<select class="acp-select" id="ban_type" name="ban_type">
						<?php foreach (($config['ban_type'] ?? []) as $id => $label): ?>
							<option value="<?= (int)$id + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_action">Action</label>
					<select class="acp-select" id="ban_action" name="ban_action">
						<?php foreach (($config['ban_action'] ?? []) as $id => $label): ?>
							<option value="<?= (int)$id + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="ban_reason">Reason</label>
					<select class="acp-select" id="ban_reason" name="ban_reason">
						<?php foreach (($config['ban_reason'] ?? []) as $id => $label): ?>
							<option value="<?= (int)$id + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_time">Duration</label>
					<select class="acp-select" id="ban_time" name="ban_time">
						<?php foreach (($config['ban_time'] ?? []) as $seconds => $label): ?>
							<option value="<?= (int)$seconds + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_comment">Comment</label>
					<input class="acp-input" id="ban_comment" name="ban_comment" maxlength="60" placeholder="Visible to the player (60 chars)">
				</div>
			</div>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--red" type="submit"><i class="fa fa-gavel"></i> Apply violation</button>
			</div>
		</form>
	</div>
</section>

<!-- ------------------------------------------------------------ Teleport -->
<section class="acp-card">
	<header class="acp-card-head">
		<h2>Teleport characters</h2>
		<p>Offline characters only - the server writes positions on logout</p>
	</header>
	<div class="acp-card-body">
		<form method="post" data-confirm="Move the selected characters?">
			<?= acp_csrf_field() ?>
			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="tp_from">Who</label>
					<select class="acp-select" id="tp_from" name="from">
						<option value="only">One character</option>
						<option value="all">Every character on the server</option>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="player_name">Character</label>
					<input class="acp-input" id="player_name" name="player_name" placeholder="Only used for 'One character'">
				</div>
				<div class="acp-field">
					<label class="acp-label" for="tp_to">Destination</label>
					<select class="acp-select" id="tp_to" name="to">
						<option value="home">Their own town temple</option>
						<option value="town">A specific town</option>
						<option value="xyz">Exact coordinates</option>
					</select>
				</div>
			</div>

			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="tp_town">Town</label>
					<select class="acp-select" id="tp_town" name="town">
						<?php foreach (($config['towns'] ?? []) as $tid => $tname): ?>
							<option value="<?= (int)$tid ?>"><?= h($tname) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="tp_x">X</label>
					<input class="acp-input" id="tp_x" name="x" type="number" value="0">
				</div>
				<div class="acp-field">
					<label class="acp-label" for="tp_y">Y</label>
					<input class="acp-input" id="tp_y" name="y" type="number" value="0">
				</div>
				<div class="acp-field">
					<label class="acp-label" for="tp_z">Z</label>
					<input class="acp-input" id="tp_z" name="z" type="number" value="7">
				</div>
			</div>

			<div class="acp-actions">
				<button class="acp-btn" type="submit"><i class="fa fa-location-arrow"></i> Teleport</button>
			</div>
		</form>
	</div>
</section>
