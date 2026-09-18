<?php
/**
 * Character Management page: page.php?p=editcharacter&character=<name>
 *
 * One character's actions (comment, gender, visibility, rename, delete) on a
 * single dedicated page instead of a dropdown+submit combo on myaccount.php.
 * Reuses the exact same server-side rules as myaccount.php's own switch, just
 * scoped to the character validated below instead of a posted selector.
 */

protect_page();

$char_name = getValue($_GET['character'] ?? null);
$char_id   = $char_name !== false ? user_character_id($char_name) : false;

if ($char_name === false || $char_id === false || (int)user_character_account_id($char_name) !== (int)$session_user_id) {
	echo '<h1>' . t('page.not_found') . '</h1>';
	return;
}

$errors = [];
$notice = null;
$deleted = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_POST['action'])) {
	if (!Token::isValid($_POST['token'] ?? null)) {
		exit();
	}
	$action = getValue($_POST['action'] ?? null);

	switch ($action) {
		case 'toggle_hide':
			$hide = (user_character_hide($char_name) == 1 ? 0 : 1);
			user_character_set_hide($char_id, $hide);
			break;

		case 'change_gender':
			if (!user_is_online_10($char_id)) {
				$tickets = shop_account_gender_tickets($session_user_id);
				$tickets = is_array($tickets) ? $tickets : array();
				if (!empty($tickets) || $config['free_sex_change'] == true) {
					$infinite = false;
					$tks = 0;
					foreach ($tickets as $ticket) {
						if ($ticket['count'] == 0) $infinite = true;
						else if ((int)$ticket['count'] > 0 && $infinite === false) $tks += (int)$ticket['count'];
					}
					if ($infinite === true) $tks = 0;
					$dbid = isset($tickets[0]['id']) ? (int)$tickets[0]['id'] : 0;
					if ($dbid > 0 && $tickets[0]['count'] > 1) {
						$tks--;
						shop_update_row_count($dbid, ((int)$tickets[0]['count'] - 1));
					} else if ($dbid > 0 && $tickets[0]['count'] == 1) {
						shop_delete_row_order($dbid);
						$tks--;
					}
					user_character_change_gender($char_name);
					$notice = t('acc.gender_changed', ['name' => $char_name]);
					if ($tks > 0) $notice .= ' ' . t('acc.gender_tickets_left', ['n' => $tks]);
					else if ($infinite !== true) $notice .= ' ' . t('acc.gender_tickets_out');
				} else {
					$errors[] = t('acc.no_gender_tickets');
				}
			} else {
				$errors[] = t('acc.must_be_offline');
			}
			break;

		case 'update_comment':
			user_update_comment($char_id, getValue($_POST['comment'] ?? null));
			$notice = t('acc.comment_updated');
			break;

		case 'change_name':
			$newname = isset($_POST['newName']) ? getValue($_POST['newName'] ?? null) : '';

			if (user_is_online_10($char_id)) {
				$errors[] = t('acc.must_be_offline');
			}

			$order = db()->fetchOne(
				"SELECT `id`, `account_id` FROM `znote_shop_orders` WHERE `type` = 4 AND `account_id` = ? LIMIT 1;",
				[(int)$session_user_id]
			);
			if ($order === false) {
				$errors[] = t('acc.no_name_tickets');
			}

			$validated = validate_name($newname);
			if ($validated === false) {
				$errors[] = t('acc.name_max_words');
			} else {
				$newname = $validated;
				if (empty($newname)) {
					$errors[] = t('acc.name_required');
				} else if (user_character_exist($newname) !== false) {
					$errors[] = t('acc.name_taken');
				} else if (!preg_match("/^[a-zA-Z_ ]+$/", $newname)) {
					$errors[] = t('acc.name_letters');
				} else if (strlen($newname) < $config['minL'] || strlen($newname) > $config['maxL']) {
					$errors[] = t('acc.name_length', ['min' => $config['minL'], 'max' => $config['maxL']]);
				} else if (!ctype_upper($newname[0])) {
					$errors[] = t('acc.name_capital');
				}

				$resname = explode(' ', (string)($_POST['newName'] ?? ''));
				foreach ($resname as $res) {
					if (in_array(strtolower($res), $config['invalidNameTags'])) {
						$errors[] = t('reg.restricted_word');
					} else if (strlen($res) == 1) {
						$errors[] = t('reg.words_too_short');
					}
				}
			}

			if (empty($errors) && !empty($newname)) {
				$db = db();
				if (!$db->beginTransaction()) {
					$errors[] = t('acc.sync_failed');
				} else {
					$ok = $db->execute("UPDATE `players` SET `name` = ? WHERE `id` = ? LIMIT 1;", [$newname, $char_id]);
					$ok = $ok && $db->execute("DELETE FROM `znote_shop_orders` WHERE `id` = ? LIMIT 1;", [(int)$order['id']]);
					if ($ok) {
						$db->commit();
						$char_name = $newname;
						$notice = t('acc.name_changed', ['name' => $newname]);
					} else {
						$db->rollback();
						$errors[] = t('acc.sync_failed');
					}
				}
			}
			break;

		case 'delete_character':
			if (!user_is_online_10($char_id)) {
				if (guild_leader_gid($char_id) === false) {
					user_delete_character_soft($char_id);
					$deleted = true;
				} else {
					$errors[] = t('acc.is_guild_leader');
				}
			} else {
				$errors[] = t('acc.must_be_offline');
			}
			break;
	}
}

$char = null;
$gender = null;
$comment_data = null;
if (!$deleted) {
	$rows = user_character_list($session_user_id);
	if (is_array($rows)) {
		foreach ($rows as $row) {
			if ($row['name'] === $char_name) {
				$char = $row;
				break;
			}
		}
	}
	$gender = user_character_data($char_id, 'sex');
	$comment_data = user_znote_character_data($char_id, 'comment');
}

$nameTicketCount = (int)(db()->fetchOne(
	"SELECT COUNT(*) AS `c` FROM `znote_shop_orders` WHERE `type` = 4 AND `account_id` = ?;",
	[(int)$session_user_id]
)['c'] ?? 0);

$genderTickets = shop_account_gender_tickets($session_user_id);
$genderTickets = is_array($genderTickets) ? $genderTickets : array();
$genderTicketInfinite = false;
$genderTicketCount = 0;
foreach ($genderTickets as $ticket) {
	if ($ticket['count'] == 0) $genderTicketInfinite = true;
	else if ((int)$ticket['count'] > 0 && $genderTicketInfinite === false) $genderTicketCount += (int)$ticket['count'];
}
?>
<div class="znx-acct">

<div class="znx-editchar-back"><a href="myaccount.php">&laquo; <?= t_default('acc.back_to_account', 'Back to My Account') ?></a></div>

<?php if (!empty($errors)): ?>
	<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--error"><?php echo output_errors($errors); ?></div>
<?php endif; ?>
<?php if ($notice): ?>
	<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--ok"><?php echo $notice; ?></div>
<?php endif; ?>

<?php if ($deleted): ?>
	<div class="znx-acct-head"><?= htmlspecialchars($char_name, ENT_QUOTES, 'UTF-8') ?></div>
	<div class="znx-acct-info">
		<p><?= t_default('acc.delete_scheduled', 'This character is scheduled for deletion. You can cancel it from your account page.') ?></p>
		<a href="myaccount.php" class="znx-acct-btn"><?= t_default('acc.back_to_account', 'Back to My Account') ?></a>
	</div>
<?php elseif ($char !== null): ?>

	<div class="znx-acct-head"><?= htmlspecialchars($char_name, ENT_QUOTES, 'UTF-8') ?> &mdash; <?= t_default('acc.character_management', 'Character Management') ?></div>
	<div class="znx-acct-info znx-editchar-info">
		<div class="znx-editchar-info__row"><span><?= mb_strtoupper(t('common.vocation')) ?></span><strong><?= htmlspecialchars((string)$char['vocation'], ENT_QUOTES, 'UTF-8') ?></strong></div>
		<div class="znx-editchar-info__row"><span><?= mb_strtoupper(t('common.level')) ?></span><strong><?= (int)$char['level'] ?></strong></div>
		<div class="znx-editchar-info__row"><span><?= mb_strtoupper(t('common.town')) ?></span><strong><?= htmlspecialchars((string)$char['town_id'], ENT_QUOTES, 'UTF-8') ?></strong></div>
	</div>

	<div class="znx-acct-head"><?= t('acc.change_name') ?></div>
	<?php if ($nameTicketCount > 0): ?>
		<form action="" method="post" class="znx-editchar-box">
			<input type="hidden" name="action" value="change_name">
			<?php Token::create(); ?>
			<div class="znx-editchar-ticket-note"><?= t_default('acc.tickets_remaining', 'Remaining:') ?> <strong><?= $nameTicketCount ?></strong></div>
			<div class="znx-acct-toolbar">
				<div class="znx-acct-toolbar__field">
					<input type="text" name="newName" placeholder="<?= htmlspecialchars(t('common.new_name_placeholder'), ENT_QUOTES, 'UTF-8') ?>" class="znx-acct-select">
				</div>
				<div class="znx-acct-toolbar__submit"><button type="submit" class="znx-acct-btn"><?= t('common.submit') ?></button></div>
			</div>
		</form>
	<?php else: ?>
		<div class="znx-editchar-box znx-editchar-box--muted"><?= t('acc.no_name_tickets') ?></div>
	<?php endif; ?>

	<div class="znx-acct-head"><?= t('acc.change_gender') ?></div>
	<form action="" method="post" class="znx-editchar-box">
		<input type="hidden" name="action" value="change_gender">
		<?php Token::create(); ?>
		<div class="znx-editchar-inline">
			<span><?= t_default('acc.current_gender', 'Current gender:') ?> <strong><?= ($gender && (int)$gender['sex'] === 1) ? t_default('common.male', 'Male') : t_default('common.female', 'Female') ?></strong></span>
			<button type="submit" class="znx-char-action"><?= t('acc.change_gender') ?></button>
		</div>
		<div class="znx-editchar-ticket-note">
			<?php if ($config['free_sex_change'] == true): ?>
				<?= t_default('acc.tickets_free', 'Free for this account.') ?>
			<?php elseif ($genderTicketInfinite): ?>
				<?= t_default('acc.tickets_unlimited', 'Unlimited gender change tickets.') ?>
			<?php elseif ($genderTicketCount > 0): ?>
				<?= t_default('acc.tickets_remaining', 'Remaining:') ?> <strong><?= $genderTicketCount ?></strong>
			<?php else: ?>
				<?= t('acc.no_gender_tickets') ?>
			<?php endif; ?>
		</div>
	</form>

	<div class="znx-acct-head"><?= t_default('acc.visibility', 'Visibility') ?></div>
	<form action="" method="post" class="znx-editchar-box">
		<input type="hidden" name="action" value="toggle_hide">
		<?php Token::create(); ?>
		<div class="znx-editchar-inline">
			<span><?= t_default('acc.current_status', 'Current status:') ?> <strong><?= htmlspecialchars(hide_char_to_name($char['hide_char']), ENT_QUOTES, 'UTF-8') ?></strong></span>
			<button type="submit" class="znx-char-action"><?= t('acc.toggle_hide') ?></button>
		</div>
	</form>

	<div class="znx-acct-head"><?= t('acc.change_comment') ?></div>
	<form action="" method="post" class="znx-editchar-box">
		<input type="hidden" name="action" value="update_comment">
		<?php Token::create(); ?>
		<textarea name="comment" class="znx-editchar-textarea" rows="6"><?php echo htmlspecialchars((string)($comment_data['comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
		<button type="submit" class="znx-acct-btn"><?= t('acc.update_comment') ?></button>
	</form>

	<div class="znx-acct-head"><?= t('acc.delete_char') ?></div>
	<form action="" method="post" class="znx-editchar-box">
		<input type="hidden" name="action" value="delete_character">
		<?php Token::create(); ?>
		<button type="submit" class="znx-char-action znx-char-action--danger needconfirmation"><?= t('acc.delete_char') ?></button>
	</form>

<?php endif; ?>

</div>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.needconfirmation').forEach(function (btn) {
			btn.addEventListener('click', function (event) {
				if (!confirm(<?= json_encode(t('acc.confirm_delete', ['name' => $char_name])) ?>)) {
					event.preventDefault();
				}
			});
		});
	});
</script>
