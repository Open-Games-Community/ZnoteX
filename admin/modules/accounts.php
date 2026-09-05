<?php
/**
 * Title: Accounts
 * Icon: fa-address-card-o
 * Group: Players
 * Order: 10
 * Description: Search an account, see its characters, points and history.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$isOthire   = ($config['ServerEngine'] === 'OTHIRE');
$accNameCol = $isOthire ? '`a`.`id`' : '`a`.`name`';

$search    = trim((string)($_GET['q'] ?? ''));
$accountId = intv($_GET['id'] ?? 0);

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$id = intv($_POST['id'] ?? 0);
	$do = (string)($_POST['do'] ?? '');

	if ($id <= 0) {
		acp_flash_error(t('acp.acc.no_selected'));
		acp_redirect('accounts');
	}

	if ($do === 'points') {
		$delta = intv($_POST['points'] ?? 0);

		$row = mysql_select_single("SELECT `points` FROM `znote_accounts` WHERE `account_id` = {$id} LIMIT 1;");
		if (!is_array($row)) {
			acp_flash_error(t('acp.acc.no_row'));
		} else {
			$new = max(0, (int)$row['points'] + $delta);
			mysql_update("UPDATE `znote_accounts` SET `points` = {$new} WHERE `account_id` = {$id};");
			acp_log('account.points', '#' . $id, ['delta' => $delta, 'new_balance' => $new]);
			acp_flash_success(t('acp.acc.points_applied', ['delta' => ($delta >= 0 ? '+' : '') . $delta, 'new' => $new]));
		}
	}

	acp_redirect('accounts', array('id' => $id));
}

// ---------------------------------------------------------------------------
// One account
// ---------------------------------------------------------------------------
$account = null;
if ($accountId > 0) {
	$account = mysql_select_single("
		SELECT `a`.`id`, {$accNameCol} AS `account_name`, `a`.`email`,
		       `za`.`points`, `za`.`created`, `za`.`ip`, `za`.`flag`, `za`.`active_email`
		FROM `accounts` `a`
		LEFT JOIN `znote_accounts` `za` ON `za`.`account_id` = `a`.`id`
		WHERE `a`.`id` = {$accountId}
		LIMIT 1;
	");

	if (!is_array($account)) {
		acp_flash_error(t('acp.acc.no_account', ['id' => $accountId]));
		acp_redirect('accounts');
	}

	$characters = mysql_select_multi("
		SELECT `id`, `name`, `level`, `vocation`, `group_id`
		FROM `players`
		WHERE `account_id` = {$accountId}
		ORDER BY `level` DESC;
	");
	$characters = is_array($characters) ? $characters : array();

	$purchases = mysql_select_multi("
		SELECT `type`, `itemid`, `count`, `points`, `time`
		FROM `znote_shop_logs`
		WHERE `account_id` = {$accountId}
		ORDER BY `id` DESC
		LIMIT 10;
	");
	$purchases = is_array($purchases) ? $purchases : array();
}

// ---------------------------------------------------------------------------
// Search / listing
// ---------------------------------------------------------------------------
$results = array();
if ($account === null) {
	$where = '';
	if ($search !== '') {
		$safe  = esc($search);
		// Match the account name, its e-mail, or a character on it.
		$where = "WHERE " . ($isOthire ? "`a`.`id` = '" . (int)$search . "'" : "`a`.`name` LIKE '%{$safe}%'")
			. " OR `a`.`email` LIKE '%{$safe}%'
			   OR `a`.`id` IN (SELECT `account_id` FROM `players` WHERE `name` LIKE '%{$safe}%')";
	}

	$results = mysql_select_multi("
		SELECT `a`.`id`, {$accNameCol} AS `account_name`, `a`.`email`,
		       `za`.`points`, `za`.`created`,
		       (SELECT COUNT(*) FROM `players` `p` WHERE `p`.`account_id` = `a`.`id`) AS `characters`
		FROM `accounts` `a`
		LEFT JOIN `znote_accounts` `za` ON `za`.`account_id` = `a`.`id`
		{$where}
		ORDER BY `a`.`id` DESC
		LIMIT 50;
	");
	$results = is_array($results) ? $results : array();
}
?>

<?php if ($account !== null): ?>

	<div class="acp-toolbar">
		<div>
			<strong><?= h((string)$account['account_name']) ?></strong>
			<span class="acp-pill acp-pill--grey">#<?= (int)$account['id'] ?></span>
		</div>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('accounts')) ?>">
			<i class="fa fa-arrow-left"></i> <?= t('acp.acc.all_accounts') ?>
		</a>
	</div>

	<div class="acp-grid acp-grid--2">

		<section class="acp-card">
			<header class="acp-card-head"><h2><?= t('acp.acc.account_title') ?></h2></header>
			<div class="acp-card-body">
				<dl class="acp-dl">
					<dt><?= t('acp.acc.name') ?></dt><dd><?= h((string)$account['account_name']) ?></dd>
					<dt><?= t('acp.acc.email') ?></dt>
					<dd>
						<?= h((string)$account['email']) ?>
						<?php if (!empty($account['active_email'])): ?>
							<span class="acp-pill acp-pill--green"><?= t('acp.acc.verified') ?></span>
						<?php else: ?>
							<span class="acp-pill acp-pill--grey"><?= t('acp.acc.unverified') ?></span>
						<?php endif; ?>
					</dd>
					<dt><?= t('acp.acc.registered') ?></dt>
					<dd><?= !empty($account['created']) ? h(getClock((int)$account['created'], true)) : '&mdash;' ?></dd>
					<dt><?= t('acp.acc.last_ip') ?></dt>
					<dd><?= !empty($account['ip']) ? '<code>' . h(long2ip((int)$account['ip'])) . '</code>' : '&mdash;' ?></dd>
					<dt><?= t('acp.acc.country') ?></dt>
					<dd><?= !empty($account['flag']) ? h((string)$account['flag']) : '&mdash;' ?></dd>
					<dt><?= t('acp.acc.shop_points') ?></dt>
					<dd><strong><?= number_format((int)($account['points'] ?? 0)) ?></strong></dd>
				</dl>
			</div>
		</section>

		<section class="acp-card">
			<header class="acp-card-head">
				<h2><?= t('acp.acc.adjust_points') ?></h2>
				<p><?= t('acp.acc.adjust_sub') ?></p>
			</header>
			<div class="acp-card-body">
				<form method="post">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="points">
					<input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
					<div class="acp-field">
						<label class="acp-label" for="points"><?= t('acp.acc.amount') ?></label>
						<input class="acp-input" id="points" name="points" type="number" value="0" required>
						<p class="acp-hint"><?= t('acp.acc.balance_hint') ?></p>
					</div>
					<div class="acp-actions">
						<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-diamond"></i> <?= t('acp.acc.apply') ?></button>
					</div>
				</form>

				<hr>
				<p class="is-muted" style="font-size:12.5px;">
					<?= t('acp.acc.other_tools', [
						'link' => '<a href="' . h(acp_url('players')) . '">' . t('acp.acc.player_tools_link') . '</a>',
					]) ?>
				</p>
			</div>
		</section>
	</div>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.acc.characters') ?></h2>
			<p><?= t('acp.acc.n_on_account', ['n' => count($characters)]) ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($characters): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th><?= t('acp.acc.col_name') ?></th><th><?= t('acp.acc.col_vocation') ?></th><th class="is-num"><?= t('acp.acc.col_level') ?></th><th><?= t('acp.acc.col_group') ?></th><th class="is-num">&nbsp;</th></tr></thead>
						<tbody>
							<?php foreach ($characters as $char): ?>
								<tr>
									<td>
										<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode((string)$char['name']))) ?>" target="_blank" rel="noopener">
											<?= h((string)$char['name']) ?>
										</a>
									</td>
									<td class="is-muted"><?= h(vocation_id_to_name((int)$char['vocation'])) ?></td>
									<td class="is-num"><?= (int)$char['level'] ?></td>
									<td>
										<?php if ((int)$char['group_id'] > 1): ?>
											<span class="acp-pill acp-pill--red"><?= t('acp.acc.staff') ?></span>
										<?php else: ?>
											<span class="is-muted"><?= t('acp.acc.player') ?></span>
										<?php endif; ?>
									</td>
									<td class="is-num is-nowrap">
										<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('skills', array('name' => (string)$char['name']))) ?>">
											<i class="fa fa-bolt"></i> <?= t('acp.acc.skills') ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty(t('acp.acc.no_characters'), 'fa-user-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.acc.recent_purchases') ?></h2>
			<p><?= t('acp.acc.last_10') ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($purchases): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th><?= t('acp.acc.col_date') ?></th><th><?= t('acp.acc.col_type') ?></th><th class="is-num"><?= t('acp.acc.col_count') ?></th><th class="is-num"><?= t('acp.acc.col_points') ?></th></tr></thead>
						<tbody>
							<?php
							$types = array(
								1 => t('acp.acc.type_item'), 2 => t('acp.acc.type_premium'), 3 => t('acp.acc.type_gender'),
								4 => t('acp.acc.type_name'), 5 => t('acp.acc.type_outfit'), 6 => t('acp.acc.type_mount'),
								7 => t('acp.acc.type_custom'),
							);
							foreach ($purchases as $buy): ?>
								<tr>
									<td class="is-nowrap is-muted"><?= h(getClock((int)$buy['time'], true)) ?></td>
									<td><?= h($types[(int)$buy['type']] ?? t('acp.acc.type_unknown')) ?></td>
									<td class="is-num"><?= (int)$buy['count'] ?></td>
									<td class="is-num"><?= (int)$buy['points'] ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty(t('acp.acc.never_bought'), 'fa-shopping-cart'); ?>
			<?php endif; ?>
		</div>
	</section>

<?php else: ?>

	<div class="acp-toolbar">
		<form method="get" style="display:flex;gap:8px;flex:1 1 340px;max-width:520px;">
			<input type="hidden" name="p" value="accounts">
			<input class="acp-input" type="search" name="q" value="<?= h($search) ?>"
				   placeholder="<?= h(t('acp.acc.search_placeholder')) ?>" autofocus>
			<button class="acp-btn" type="submit"><i class="fa fa-search"></i> <?= t('acp.acc.search') ?></button>
			<?php if ($search !== ''): ?>
				<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('accounts')) ?>"><?= t('acp.acc.clear') ?></a>
			<?php endif; ?>
		</form>
		<span class="is-muted">
			<?= $search !== '' ? t('acp.acc.match_count', ['n' => count($results)]) : t('acp.acc.newest_50') ?>
		</span>
	</div>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= $search !== '' ? t('acp.acc.search_results') : t('acp.acc.newest_accounts') ?></h2>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($results): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th>#</th><th><?= t('acp.acc.col_account') ?></th><th><?= t('acp.acc.col_email') ?></th><th class="is-num"><?= t('acp.acc.col_chars') ?></th><th class="is-num"><?= t('acp.acc.col_points') ?></th><th><?= t('acp.acc.col_registered') ?></th><th class="is-num">&nbsp;</th></tr>
						</thead>
						<tbody>
							<?php foreach ($results as $row): ?>
								<tr>
									<td class="is-muted"><?= (int)$row['id'] ?></td>
									<td><?= h((string)$row['account_name']) ?></td>
									<td class="is-muted"><?= h((string)$row['email']) ?></td>
									<td class="is-num"><?= (int)$row['characters'] ?></td>
									<td class="is-num"><?= number_format((int)($row['points'] ?? 0)) ?></td>
									<td class="is-nowrap is-muted"><?= !empty($row['created']) ? h(getClock((int)$row['created'], true)) : '&mdash;' ?></td>
									<td class="is-num">
										<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('accounts', array('id' => (int)$row['id']))) ?>">
											<i class="fa fa-eye"></i> <?= t('acp.acc.open') ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty($search !== '' ? t('acp.acc.no_match', ['search' => $search]) : t('acp.acc.no_accounts_yet'), 'fa-address-card-o'); ?>
			<?php endif; ?>
		</div>
	</section>

<?php endif; ?>
