<?php
/**
 * Title: Accounts
 * Icon: fa-address-card-o
 * Group: Players
 * Order: 5
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
		acp_flash_error('No account selected.');
		acp_redirect('accounts');
	}

	if ($do === 'points') {
		$delta = intv($_POST['points'] ?? 0);

		$row = mysql_select_single("SELECT `points` FROM `znote_accounts` WHERE `account_id` = {$id} LIMIT 1;");
		if (!is_array($row)) {
			acp_flash_error('That account has no znote_accounts row, so it has no points balance.');
		} else {
			$new = max(0, (int)$row['points'] + $delta);
			mysql_update("UPDATE `znote_accounts` SET `points` = {$new} WHERE `account_id` = {$id};");
			acp_flash_success(($delta >= 0 ? '+' : '') . $delta . ' points. New balance: ' . $new . '.');
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
		acp_flash_error('No account with id ' . $accountId . '.');
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
			<i class="fa fa-arrow-left"></i> All accounts
		</a>
	</div>

	<div class="acp-grid acp-grid--2">

		<section class="acp-card">
			<header class="acp-card-head"><h2>Account</h2></header>
			<div class="acp-card-body">
				<dl class="acp-dl">
					<dt>Name</dt><dd><?= h((string)$account['account_name']) ?></dd>
					<dt>E-mail</dt>
					<dd>
						<?= h((string)$account['email']) ?>
						<?php if (!empty($account['active_email'])): ?>
							<span class="acp-pill acp-pill--green">verified</span>
						<?php else: ?>
							<span class="acp-pill acp-pill--grey">unverified</span>
						<?php endif; ?>
					</dd>
					<dt>Registered</dt>
					<dd><?= !empty($account['created']) ? h(getClock((int)$account['created'], true)) : '&mdash;' ?></dd>
					<dt>Last known IP</dt>
					<dd><?= !empty($account['ip']) ? '<code>' . h(long2ip((int)$account['ip'])) . '</code>' : '&mdash;' ?></dd>
					<dt>Country</dt>
					<dd><?= !empty($account['flag']) ? h((string)$account['flag']) : '&mdash;' ?></dd>
					<dt>Shop points</dt>
					<dd><strong><?= number_format((int)($account['points'] ?? 0)) ?></strong></dd>
				</dl>
			</div>
		</section>

		<section class="acp-card">
			<header class="acp-card-head">
				<h2>Adjust points</h2>
				<p>Positive adds, negative removes</p>
			</header>
			<div class="acp-card-body">
				<form method="post">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="points">
					<input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
					<div class="acp-field">
						<label class="acp-label" for="points">Amount</label>
						<input class="acp-input" id="points" name="points" type="number" value="0" required>
						<p class="acp-hint">The balance never goes below zero.</p>
					</div>
					<div class="acp-actions">
						<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-diamond"></i> Apply</button>
					</div>
				</form>

				<hr>
				<p class="is-muted" style="font-size:12.5px;">
					Password resets, bans, positions and teleports are on
					<a href="<?= h(acp_url('players')) ?>">Player Tools</a>, which works by character name.
				</p>
			</div>
		</section>
	</div>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Characters</h2>
			<p><?= count($characters) ?> on this account</p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($characters): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th>Name</th><th>Vocation</th><th class="is-num">Level</th><th>Group</th><th class="is-num">&nbsp;</th></tr></thead>
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
											<span class="acp-pill acp-pill--red">staff</span>
										<?php else: ?>
											<span class="is-muted">player</span>
										<?php endif; ?>
									</td>
									<td class="is-num is-nowrap">
										<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('skills', array('name' => (string)$char['name']))) ?>">
											<i class="fa fa-bolt"></i> Skills
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('No characters on this account.', 'fa-user-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Recent purchases</h2>
			<p>Last 10 shop transactions</p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($purchases): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th>Date</th><th>Type</th><th class="is-num">Count</th><th class="is-num">Points</th></tr></thead>
						<tbody>
							<?php
							$types = array(1 => 'Item', 2 => 'Premium days', 3 => 'Gender change', 4 => 'Name change', 5 => 'Outfit', 6 => 'Mount', 7 => 'Custom');
							foreach ($purchases as $buy): ?>
								<tr>
									<td class="is-nowrap is-muted"><?= h(getClock((int)$buy['time'], true)) ?></td>
									<td><?= h($types[(int)$buy['type']] ?? 'Unknown') ?></td>
									<td class="is-num"><?= (int)$buy['count'] ?></td>
									<td class="is-num"><?= (int)$buy['points'] ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('This account has never bought anything.', 'fa-shopping-cart'); ?>
			<?php endif; ?>
		</div>
	</section>

<?php else: ?>

	<div class="acp-toolbar">
		<form method="get" style="display:flex;gap:8px;flex:1 1 340px;max-width:520px;">
			<input type="hidden" name="p" value="accounts">
			<input class="acp-input" type="search" name="q" value="<?= h($search) ?>"
				   placeholder="Account name, e-mail, or a character on it&hellip;" autofocus>
			<button class="acp-btn" type="submit"><i class="fa fa-search"></i> Search</button>
			<?php if ($search !== ''): ?>
				<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('accounts')) ?>">Clear</a>
			<?php endif; ?>
		</form>
		<span class="is-muted">
			<?= $search !== '' ? count($results) . ' match' . (count($results) === 1 ? '' : 'es') : 'newest 50' ?>
		</span>
	</div>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= $search !== '' ? 'Search results' : 'Newest accounts' ?></h2>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($results): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th>#</th><th>Account</th><th>E-mail</th><th class="is-num">Chars</th><th class="is-num">Points</th><th>Registered</th><th class="is-num">&nbsp;</th></tr>
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
											<i class="fa fa-eye"></i> Open
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty($search !== '' ? 'Nothing matches "' . $search . '".' : 'No accounts yet.', 'fa-address-card-o'); ?>
			<?php endif; ?>
		</div>
	</section>

<?php endif; ?>
