<?php
/**
 * Title: Mass Actions
 * Icon: fa-tasks
 * Group: Players
 * Order: 21
 * Description: Apply points, premium days or a teleport to every account or character at once.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$accountTotal = acp_count("SELECT COUNT(*) AS `c` FROM `accounts`;");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// -------------------------------------------------------- Mass points
	if (isset($_POST['points_delta'])) {
		$delta = intv($_POST['points_delta'] ?? 0);

		if ($delta === 0) {
			acp_flash_error(t('acp.mpts.zero_value'));
			acp_redirect('mass_actions');
		}

		db()->execute("
			INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `flag`)
			SELECT `a`.`id`, 0, ?, ''
			FROM `accounts` `a`
			LEFT JOIN `znote_accounts` `z` ON `z`.`account_id` = `a`.`id`
			WHERE `z`.`account_id` IS NULL;
		", [time()]);

		$updated = db()->execute("UPDATE `znote_accounts` SET `points` = GREATEST(0, `points` + ?);", [$delta]);

		if ($updated !== false) {
			acp_log('accounts.mass_points', '', ['delta' => $delta, 'accounts' => $accountTotal]);
			acp_flash_success(t('acp.mpts.done', ['delta' => ($delta >= 0 ? '+' : '') . $delta, 'n' => $accountTotal]));
		} else {
			acp_flash_error(t('acp.mpts.failed'));
		}

		acp_redirect('mass_actions');
	}

	// --------------------------------------------------- Mass premium days
	if (isset($_POST['premium_days'])) {
		$days = intv($_POST['premium_days'] ?? 0);

		if ($days <= 0) {
			acp_flash_error(t('acp.mprem.invalid_value'));
			acp_redirect('mass_actions');
		}

		$updated = user_accounts_add_premdays_all($days);

		if ($updated !== false) {
			acp_log('accounts.mass_premium_days', '', ['days' => $days, 'accounts' => $accountTotal]);
			acp_flash_success(t('acp.mprem.done', ['days' => $days, 'n' => $accountTotal]));
		} else {
			acp_flash_error(t('acp.mprem.not_supported'));
		}

		acp_redirect('mass_actions');
	}

	// ------------------------------------------------------------ Teleport
	if (isset($_POST['from'], $_POST['to'])) {
		$from = (string)$_POST['from'];
		$to   = (string)$_POST['to'];

		$target = '';
		$fail   = false;

		if ($from === 'only') {
			$target = trim((string)($_POST['player_name'] ?? ''));
			if ($target === '' || !user_character_exist($target)) {
				acp_flash_error(t('acp.plr.err_invalid_teleport_char'));
				$fail = true;
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
				if ($from === 'only') {
					db()->execute("UPDATE `players` SET {$set} WHERE `name` = ?;", [$target]);
				} else {
					db()->execute("UPDATE `players` SET {$set};");
				}
				acp_log('player.teleport', $from === 'only' ? $target : 'ALL', ['destination' => $to]);
				acp_flash_success($from === 'only'
					? t('acp.plr.tp_one_done')
					: t('acp.plr.tp_all_done'));
			}
		}

		acp_redirect('mass_actions');
	}
}
?>

<div class="acp-stats">
	<?php acp_stat(t('acp.mpts.stat_accounts'), $accountTotal, 'fa-users', acp_url('accounts'), 'blue'); ?>
</div>

<div class="acp-grid acp-grid--2">

	<!-- --------------------------------------------------------- Mass points -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.mpts.title') ?></h2>
			<p><?= t('acp.mpts.sub') ?></p>
		</header>
		<div class="acp-card-body">
			<div class="acp-flash acp-flash--error" style="margin-bottom:16px;">
				<i class="fa fa-exclamation-triangle"></i>
				<span><?= t('acp.mpts.warning', ['n' => $accountTotal]) ?></span>
			</div>

			<form method="post" data-confirm="<?= h(t('acp.mpts.confirm', ['n' => $accountTotal])) ?>">
				<?= acp_csrf_field() ?>
				<div class="acp-field">
					<label class="acp-label" for="points_delta"><?= t('acp.mpts.delta_label') ?></label>
					<input class="acp-input" id="points_delta" name="points_delta" type="number" step="1" placeholder="<?= h(t('acp.mpts.delta_placeholder')) ?>" required>
					<p class="acp-hint"><?= t('acp.mpts.delta_help') ?></p>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--amber" type="submit"><i class="fa fa-bolt"></i> <?= t('acp.mpts.submit_btn') ?></button>
				</div>
			</form>
		</div>
	</section>

	<!-- --------------------------------------------------- Mass premium days -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.mprem.title') ?></h2>
			<p><?= t('acp.mprem.sub') ?></p>
		</header>
		<div class="acp-card-body">
			<div class="acp-flash acp-flash--error" style="margin-bottom:16px;">
				<i class="fa fa-exclamation-triangle"></i>
				<span><?= t('acp.mprem.warning', ['n' => $accountTotal]) ?></span>
			</div>

			<form method="post" data-confirm="<?= h(t('acp.mprem.confirm', ['n' => $accountTotal])) ?>">
				<?= acp_csrf_field() ?>
				<div class="acp-field">
					<label class="acp-label" for="premium_days"><?= t('acp.mprem.days_label') ?></label>
					<input class="acp-input" id="premium_days" name="premium_days" type="number" min="1" step="1" placeholder="<?= h(t('acp.mprem.days_placeholder')) ?>" required>
					<p class="acp-hint"><?= t('acp.mprem.days_help') ?></p>
				</div>
				<div class="acp-actions">
					<button class="acp-btn acp-btn--amber" type="submit"><i class="fa fa-star"></i> <?= t('acp.mprem.submit_btn') ?></button>
				</div>
			</form>
		</div>
	</section>

</div>

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
