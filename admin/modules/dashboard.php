<?php
/**
 * Title: Dashboard
 * Icon: fa-tachometer
 * Group: Overview
 * Order: 10
 * Description: Server and community at a glance.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

// OTHIRE has no accounts.name column - it identifies accounts by number.
$isOthire   = ($config['ServerEngine'] === 'OTHIRE');
$accNameCol = $isOthire ? '`a`.`id`' : '`a`.`name`';

// ---------------------------------------------------------------------------
// Counters. acp_count() returns 0 for a table this engine does not have,
// so an unusual schema degrades to a zero instead of a fatal error.
// ---------------------------------------------------------------------------
$statAccounts = acp_count("SELECT COUNT(*) AS `c` FROM `accounts`;");
$statPlayers  = acp_count("SELECT COUNT(*) AS `c` FROM `players`;");
$statGuilds   = acp_count("SELECT COUNT(*) AS `c` FROM `guilds`;");
$statHouses   = acp_count("SELECT COUNT(*) AS `c` FROM `houses`;");

$statOnline = ($config['ServerEngine'] === 'TFS_10')
	? acp_count("SELECT COUNT(*) AS `c` FROM `players_online`;")
	: acp_count("SELECT COUNT(*) AS `c` FROM `players` WHERE `online` > 0;");

$statPoints = acp_count("SELECT COALESCE(SUM(`points`), 0) AS `c` FROM `znote_accounts`;");
$statOrders = acp_count("SELECT COUNT(*) AS `c` FROM `znote_shop_orders`;");

// Moderation queues - reuse the same counters the sidebar badges use.
$queueReports  = acp_badge_reports();
$queueTickets  = acp_badge_helpdesk();
$queueImages   = acp_badge_gallery();
$queueFeedback = acp_badge_feedback();

// ---------------------------------------------------------------------------
// Recent activity
// ---------------------------------------------------------------------------
$latestAccounts = mysql_select_multi("
	SELECT `a`.`id` AS `account_id`, {$accNameCol} AS `account_name`, `za`.`created`, `za`.`points`
	FROM `znote_accounts` `za`
	INNER JOIN `accounts` `a` ON `a`.`id` = `za`.`account_id`
	ORDER BY `za`.`created` DESC
	LIMIT 10;
");

$latestPlayers = mysql_select_multi("
	SELECT `name`, `level`, `vocation`
	FROM `players`
	ORDER BY `id` DESC
	LIMIT 10;
");

$topPoints = mysql_select_multi("
	SELECT `a`.`id` AS `account_id`, {$accNameCol} AS `account_name`, `za`.`points`
	FROM `znote_accounts` `za`
	INNER JOIN `accounts` `a` ON `a`.`id` = `za`.`account_id`
	WHERE `za`.`points` > 0
	ORDER BY `za`.`points` DESC
	LIMIT 10;
");

// znote row: keep the stored version in step with the running one, the way
// the old admin.php did on every visit.
$znote = user_znote_data('version', 'installed', 'cached');
if (is_array($znote) && ($znote['version'] ?? null) !== $version) {
	mysql_update("UPDATE `znote` SET `version`='" . esc($version) . "';");
	$znote['version'] = $version;
}
?>

<div class="acp-stats">
	<?php
	acp_stat('Accounts', $statAccounts, 'fa-user-plus', null, 'blue');
	acp_stat('Characters', $statPlayers, 'fa-users', null, 'red');
	acp_stat('Online now', $statOnline, 'fa-signal', null, 'teal');
	acp_stat('Guilds', $statGuilds, 'fa-shield', null, 'green');
	acp_stat('Houses', $statHouses, 'fa-home', null, 'amber');
	acp_stat('Shop points', $statPoints, 'fa-diamond', acp_url('shop'), 'purple');
	?>
</div>

<div class="acp-grid acp-grid--2">

	<!-- ------------------------------------------------ Moderation queue -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Needs attention</h2>
			<p>Open items across the panel</p>
		</header>
		<div class="acp-card-body is-flush">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<tbody>
						<tr>
							<td><i class="fa fa-bug is-muted"></i> &nbsp;Bug reports open</td>
							<td class="is-num">
								<span class="acp-pill <?= $queueReports > 0 ? 'acp-pill--red' : 'acp-pill--green' ?>"><?= (int)$queueReports ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('reports')) ?>">Review</a></td>
						</tr>
						<tr>
							<td><i class="fa fa-life-ring is-muted"></i> &nbsp;Helpdesk tickets open</td>
							<td class="is-num">
								<span class="acp-pill <?= $queueTickets > 0 ? 'acp-pill--amber' : 'acp-pill--green' ?>"><?= (int)$queueTickets ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('helpdesk')) ?>">Review</a></td>
						</tr>
						<tr>
							<td><i class="fa fa-picture-o is-muted"></i> &nbsp;Images awaiting moderation</td>
							<td class="is-num">
								<span class="acp-pill <?= $queueImages > 0 ? 'acp-pill--amber' : 'acp-pill--green' ?>"><?= (int)$queueImages ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('gallery')) ?>">Review</a></td>
						</tr>
						<tr>
							<td><i class="fa fa-comments-o is-muted"></i> &nbsp;Feedback threads without a staff reply</td>
							<td class="is-num">
								<span class="acp-pill <?= $queueFeedback > 0 ? 'acp-pill--amber' : 'acp-pill--green' ?>"><?= (int)$queueFeedback ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_site('forum.php?cat=4')) ?>">Open</a></td>
						</tr>
						<tr>
							<td><i class="fa fa-shopping-cart is-muted"></i> &nbsp;Shop orders pending delivery</td>
							<td class="is-num">
								<span class="acp-pill <?= $statOrders > 0 ? 'acp-pill--blue' : 'acp-pill--green' ?>"><?= (int)$statOrders ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('shop_orders')) ?>">Open</a></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<!-- ------------------------------------------------------ Environment -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Environment</h2>
			<p>What this install is running</p>
		</header>
		<div class="acp-card-body">
			<dl class="acp-dl">
				<dt>ZnoteX</dt>
				<dd><?= h($version) ?><?= is_array($znote) && isset($znote['version']) ? '' : ' <span class="acp-pill acp-pill--red">znote table missing</span>' ?></dd>

				<dt>PHP</dt>
				<dd><?= h(PHP_VERSION) ?></dd>

				<dt>Server engine</dt>
				<dd><span class="acp-pill acp-pill--blue"><?= h(serverEngineReal()) ?></span></dd>

				<dt>Database</dt>
				<dd><?= h($config['sqlDatabase'] ?? '') ?> @ <?= h($config['sqlHost'] ?? '') ?></dd>

				<dt>Site URL</dt>
				<dd><a href="<?= h($config['site_url'] ?? '#') ?>" target="_blank" rel="noopener"><?= h($config['site_url'] ?? '') ?></a></dd>

				<dt>Installed</dt>
				<dd><?= is_array($znote) && !empty($znote['installed']) ? h(getClock((int)$znote['installed'], true)) : '&mdash;' ?></dd>

				<dt>Last cache</dt>
				<dd><?= is_array($znote) && !empty($znote['cached']) ? h(getClock((int)$znote['cached'], true)) : '&mdash;' ?></dd>

				<dt>Two-factor</dt>
				<dd><?= !empty($config['twoFactorAuthenticator'])
					? '<span class="acp-pill acp-pill--green">Enabled</span>'
					: '<span class="acp-pill acp-pill--grey">Disabled</span>' ?></dd>
			</dl>
		</div>
	</section>
</div>

<div class="acp-grid acp-grid--3">

	<!-- ------------------------------------------------- Latest accounts -->
	<section class="acp-card">
		<header class="acp-card-head"><h2>Newest accounts</h2></header>
		<div class="acp-card-body is-flush">
			<?php if (is_array($latestAccounts) && $latestAccounts): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th>Account</th><th>Created</th><th class="is-num">Points</th></tr>
						</thead>
						<tbody>
							<?php foreach ($latestAccounts as $row): ?>
								<tr>
									<td><?= h($row['account_name']) ?></td>
									<td class="is-nowrap is-muted"><?= h(getClock((int)$row['created'], true)) ?></td>
									<td class="is-num"><?= (int)$row['points'] ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('No accounts registered yet.', 'fa-user-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<!-- -------------------------------------------------- Latest players -->
	<section class="acp-card">
		<header class="acp-card-head"><h2>Newest characters</h2></header>
		<div class="acp-card-body is-flush">
			<?php if (is_array($latestPlayers) && $latestPlayers): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th>Name</th><th>Vocation</th><th class="is-num">Level</th></tr>
						</thead>
						<tbody>
							<?php foreach ($latestPlayers as $row): ?>
								<tr>
									<td>
										<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode((string)$row['name']))) ?>" target="_blank" rel="noopener">
											<?= h($row['name']) ?>
										</a>
									</td>
									<td class="is-muted"><?= h(vocation_id_to_name((int)$row['vocation'])) ?></td>
									<td class="is-num"><?= (int)$row['level'] ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('No characters created yet.', 'fa-user-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<!-- ---------------------------------------------------- Top balances -->
	<section class="acp-card">
		<header class="acp-card-head"><h2>Largest point balances</h2></header>
		<div class="acp-card-body is-flush">
			<?php if (is_array($topPoints) && $topPoints): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th>#</th><th>Account</th><th class="is-num">Points</th></tr>
						</thead>
						<tbody>
							<?php $rank = 0; foreach ($topPoints as $row): $rank++; ?>
								<tr>
									<td class="is-muted"><?= $rank ?></td>
									<td><?= h($row['account_name']) ?></td>
									<td class="is-num"><strong><?= number_format((int)$row['points']) ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('Nobody holds shop points yet.', 'fa-diamond'); ?>
			<?php endif; ?>
		</div>
	</section>
</div>
