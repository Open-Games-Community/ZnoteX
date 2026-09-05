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
				acp_log('player.violation', $char, [
					'type' => $type, 'action' => $action, 'reason' => $reason,
					'time' => $time, 'comment' => $comment,
				]);
				acp_flash_success(t('acp.plr.violation_set', ['char' => h($char)]));
			} else {
				acp_flash_error(t('acp.plr.violation_failed'));
			}
		} else {
			acp_flash_error(t('acp.plr.char_not_exist', ['char' => h($char)]));
		}

		acp_redirect('players');
	}

	// ----------------------------------------------------- Delete character
	if (!empty($_POST['del_name'])) {
		$char = trim((string)$_POST['del_name']);

		if (user_character_exist($char)) {
			user_delete_character(user_character_id($char));
			acp_log('player.delete_char', $char);
			acp_flash_success(t('acp.plr.char_deleted', ['char' => h($char)]));
		} else {
			acp_flash_error(t('acp.plr.char_not_exist', ['char' => h($char)]));
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
				acp_log('player.reset_password', $char);
				acp_flash_success(t('acp.plr.password_reset', ['char' => h($char)]));
				acp_redirect('players');
			}

			header('Location: ../changepassword.php');
			exit;
		}

		acp_flash_error(t('acp.plr.char_not_exist', ['char' => h($char)]));
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
			$problems[] = t('acp.plr.err_current_not_exist');
		}
		if ($newName === false) {
			$problems[] = t('acp.plr.err_too_many_words');
		} else {
			$newName = format_character_name($newName);
			if (!preg_match('/^[a-zA-Z ]+$/', $newName)) {
				$problems[] = t('acp.plr.err_letters_only');
			}
			$minLength = (int)($config['minL'] ?? 3);
			$maxLength = (int)($config['maxL'] ?? 20);
			if (strlen($newName) < $minLength || strlen($newName) > $maxLength) {
				$problems[] = t('acp.plr.err_length', ['min' => $minLength, 'max' => $maxLength]);
			}
			foreach (explode(' ', $newName) as $word) {
				if (strlen($word) === 1) {
					$problems[] = t('acp.plr.err_word_length');
					break;
				}
				if (in_array(strtolower($word), $config['invalidNameTags'] ?? [], true)) {
					$problems[] = t('acp.plr.err_restricted_word');
					break;
				}
			}
			if (in_array(strtolower($newName), $config['creatureNameTags'] ?? [], true)) {
				$problems[] = t('acp.plr.err_creature_name');
			}
		}

		if (is_array($character) && isset($character['online']) && (int)$character['online'] !== 0) {
			$problems[] = t('acp.plr.err_must_be_offline');
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
				$problems[] = t('acp.plr.err_name_taken');
			}
		}

		if ($problems) {
			acp_flash_error(implode(' ', array_map('h', $problems)));
			acp_redirect('players');
		}

		$oldName = (string)$character['name'];
		$characterId = (int)$character['id'];
		if (strcasecmp($oldName, (string)$newName) === 0 && $oldName === $newName) {
			acp_flash_error(t('acp.plr.err_same_name'));
			acp_redirect('players');
		}

		if (!mysql_update("
			UPDATE `players`
			SET `name` = '" . esc((string)$newName) . "'
			WHERE `id` = {$characterId}
			LIMIT 1;
		")) {
			acp_flash_error(t('acp.plr.err_rename_failed'));
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
		acp_log('player.rename', $oldName, ['new_name' => (string)$newName]);
		acp_flash_success(t('acp.plr.renamed', ['old' => h($oldName), 'new' => h((string)$newName)]));
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

				acp_log('player.give_points', $char, ['points' => $points, 'new_balance' => $newPoints]);
				acp_flash_success(t('acp.plr.points_given', [
					'points' => h((string)$points),
					'char' => h($char),
					'balance' => h((string)$newPoints),
				]));
			} else {
				acp_flash_error(t('acp.plr.err_no_znote_row'));
			}
		} else {
			acp_flash_error(t('acp.plr.char_not_exist', ['char' => h($char)]));
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

			acp_log('player.set_position', $char, ['position' => $config['ingame_positions'][$pos]]);
			acp_flash_success(t('acp.plr.position_set', [
				'char' => h($char),
				'position' => h($config['ingame_positions'][$pos]),
			]));
		} else {
			acp_flash_error(t('acp.plr.err_unknown_char_position'));
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
				acp_flash_error(t('acp.plr.err_invalid_teleport_char'));
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
				acp_flash_error(t('acp.plr.err_unknown_destination'));
			} else {
				mysql_update("UPDATE `players` SET {$set} {$where};");
				acp_log('player.teleport', $from === 'only' ? $target : 'ALL', ['destination' => $to]);
				acp_flash_success($from === 'only'
					? t('acp.plr.tp_one_done')
					: t('acp.plr.tp_all_done'));
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
			<h2><?= h(t('acp.plr.give_points_title')) ?></h2>
			<p><?= h(t('acp.plr.give_points_sub')) ?></p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="points_char"><?= h(t('acp.plr.character_label')) ?></label>
						<input class="acp-input" id="points_char" name="points_char" placeholder="<?= h(t('acp.plr.character_name_placeholder')) ?>" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="points_value"><?= h(t('acp.plr.points_label')) ?></label>
						<input class="acp-input" id="points_value" name="points_value" type="number" value="10" required>
					</div>
				</div>
				<p class="acp-hint"><?= h(t('acp.plr.points_hint')) ?></p>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-diamond"></i> <?= h(t('acp.plr.give_points_btn')) ?></button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------- Reset password -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= h(t('acp.plr.reset_pass_title')) ?></h2>
			<p><?= h(t('acp.plr.reset_pass_sub')) ?></p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="reset_pass"><?= h(t('acp.plr.character_label')) ?></label>
						<input class="acp-input" id="reset_pass" name="reset_pass" placeholder="<?= h(t('acp.plr.character_name_placeholder')) ?>" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="new_pass"><?= h(t('acp.plr.new_password_label')) ?></label>
						<input class="acp-input" id="new_pass" name="new_pass" type="text" placeholder="<?= h(t('acp.plr.new_password_label')) ?>" required>
					</div>
				</div>
				<p class="acp-hint"><?= h(t('acp.plr.reset_pass_hint')) ?></p>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--amber" type="submit"><i class="fa fa-key"></i> <?= h(t('acp.plr.reset_pass_btn')) ?></button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------ Rename character -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= h(t('acp.plr.rename_title')) ?></h2>
			<p><?= h(t('acp.plr.rename_sub')) ?></p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="rename_character" value="1">
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="rename_current"><?= h(t('acp.plr.current_name_label')) ?></label>
						<input class="acp-input" id="rename_current" name="rename_current" placeholder="<?= h(t('acp.plr.current_name_placeholder')) ?>" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="rename_new"><?= h(t('acp.plr.new_name_label')) ?></label>
						<input class="acp-input" id="rename_new" name="rename_new" placeholder="<?= h(t('acp.plr.new_name_placeholder')) ?>" required>
					</div>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--blue" type="submit"><i class="fa fa-pencil"></i> <?= h(t('acp.plr.rename_btn')) ?></button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------ Ingame position -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= h(t('acp.plr.position_title')) ?></h2>
			<p><?= h(t('acp.plr.position_sub')) ?></p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="position_name"><?= h(t('acp.plr.character_label')) ?></label>
						<input class="acp-input" id="position_name" name="position_name" placeholder="<?= h(t('acp.plr.character_name_placeholder')) ?>" required>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="position_type"><?= h(t('acp.plr.position_label')) ?></label>
						<select class="acp-select" id="position_type" name="position_type">
							<?php foreach (($config['ingame_positions'] ?? []) as $pid => $pname): ?>
								<option value="<?= h((string)$pid) ?>"><?= h($pname) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--blue" type="submit"><i class="fa fa-star"></i> <?= h(t('acp.plr.set_position_btn')) ?></button>
				</div>
			</form>
		</div>
	</section>

	<!-- ------------------------------------------------ Delete character -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= h(t('acp.plr.delete_title')) ?></h2>
			<p><?= h(t('acp.plr.delete_sub')) ?></p>
		</header>
		<div class="acp-card-body">
			<form method="post" data-confirm="<?= h(t('acp.plr.delete_confirm')) ?>">
				<?= acp_csrf_field() ?>
				<div class="acp-field">
					<label class="acp-label" for="del_name"><?= h(t('acp.plr.character_label')) ?></label>
					<input class="acp-input" id="del_name" name="del_name" placeholder="<?= h(t('acp.plr.character_name_placeholder')) ?>" required>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--red" type="submit"><i class="fa fa-trash"></i> <?= h(t('acp.plr.delete_btn')) ?></button>
				</div>
			</form>
		</div>
	</section>
</div>

<!-- ------------------------------------------------------ Rule violation -->
<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= h(t('acp.plr.violation_title')) ?></h2>
		<p><?= h(t('acp.plr.violation_sub')) ?></p>
	</header>
	<div class="acp-card-body">
		<form method="post" data-confirm="<?= h(t('acp.plr.violation_confirm')) ?>">
			<?= acp_csrf_field() ?>
			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="ban_char"><?= h(t('acp.plr.character_label')) ?></label>
					<input class="acp-input" id="ban_char" name="ban_char" placeholder="<?= h(t('acp.plr.character_name_placeholder')) ?>" required>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_type"><?= h(t('acp.plr.type_label')) ?></label>
					<select class="acp-select" id="ban_type" name="ban_type">
						<?php foreach (($config['ban_type'] ?? []) as $id => $label): ?>
							<option value="<?= (int)$id + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_action"><?= h(t('acp.plr.action_label')) ?></label>
					<select class="acp-select" id="ban_action" name="ban_action">
						<?php foreach (($config['ban_action'] ?? []) as $id => $label): ?>
							<option value="<?= (int)$id + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="ban_reason"><?= h(t('acp.plr.reason_label')) ?></label>
					<select class="acp-select" id="ban_reason" name="ban_reason">
						<?php foreach (($config['ban_reason'] ?? []) as $id => $label): ?>
							<option value="<?= (int)$id + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_time"><?= h(t('acp.plr.duration_label')) ?></label>
					<select class="acp-select" id="ban_time" name="ban_time">
						<?php foreach (($config['ban_time'] ?? []) as $seconds => $label): ?>
							<option value="<?= (int)$seconds + $enc ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="ban_comment"><?= h(t('acp.plr.comment_label')) ?></label>
					<input class="acp-input" id="ban_comment" name="ban_comment" maxlength="60" placeholder="<?= h(t('acp.plr.comment_placeholder')) ?>">
				</div>
			</div>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--red" type="submit"><i class="fa fa-gavel"></i> <?= h(t('acp.plr.apply_violation_btn')) ?></button>
			</div>
		</form>
	</div>
</section>

<!-- ------------------------------------------------------------ Teleport -->
<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= h(t('acp.plr.teleport_title')) ?></h2>
		<p><?= h(t('acp.plr.teleport_sub')) ?></p>
	</header>
	<div class="acp-card-body">
		<form method="post" data-confirm="<?= h(t('acp.plr.teleport_confirm')) ?>">
			<?= acp_csrf_field() ?>
			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="tp_from"><?= h(t('acp.plr.who_label')) ?></label>
					<select class="acp-select" id="tp_from" name="from">
						<option value="only"><?= h(t('acp.plr.tp_one_option')) ?></option>
						<option value="all"><?= h(t('acp.plr.tp_all_option')) ?></option>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="player_name"><?= h(t('acp.plr.character_label')) ?></label>
					<input class="acp-input" id="player_name" name="player_name" placeholder="<?= h(t('acp.plr.tp_char_placeholder')) ?>">
				</div>
				<div class="acp-field">
					<label class="acp-label" for="tp_to"><?= h(t('acp.plr.destination_label')) ?></label>
					<select class="acp-select" id="tp_to" name="to">
						<option value="home"><?= h(t('acp.plr.dest_home')) ?></option>
						<option value="town"><?= h(t('acp.plr.dest_town')) ?></option>
						<option value="xyz"><?= h(t('acp.plr.dest_xyz')) ?></option>
					</select>
				</div>
			</div>

			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="tp_town"><?= h(t('acp.plr.town_label')) ?></label>
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
				<button class="acp-btn" type="submit"><i class="fa fa-location-arrow"></i> <?= h(t('acp.plr.teleport_btn')) ?></button>
			</div>
		</form>
	</div>
</section>
