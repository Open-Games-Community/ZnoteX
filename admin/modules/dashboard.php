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
	acp_stat(t('acp.dash.stat_accounts'), $statAccounts, 'fa-user-plus', null, 'blue');
	acp_stat(t('acp.dash.stat_characters'), $statPlayers, 'fa-users', null, 'red');
	acp_stat(t('acp.dash.stat_online'), $statOnline, 'fa-signal', null, 'teal');
	acp_stat(t('acp.dash.stat_guilds'), $statGuilds, 'fa-shield', null, 'green');
	acp_stat(t('acp.dash.stat_houses'), $statHouses, 'fa-home', null, 'amber');
	acp_stat(t('acp.dash.stat_points'), $statPoints, 'fa-diamond', acp_url('shop'), 'purple');
	?>
</div>

<div class="acp-grid acp-grid--2">

	<!-- ------------------------------------------------ Moderation queue -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.dash.attention_title') ?></h2>
			<p><?= t('acp.dash.attention_sub') ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<tbody>
						<tr>
							<td><i class="fa fa-bug is-muted"></i> &nbsp;<?= t('acp.dash.bug_reports') ?></td>
							<td class="is-num">
								<span class="acp-pill <?= $queueReports > 0 ? 'acp-pill--red' : 'acp-pill--green' ?>"><?= (int)$queueReports ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('reports')) ?>"><?= t('acp.dash.review') ?></a></td>
						</tr>
						<tr>
							<td><i class="fa fa-life-ring is-muted"></i> &nbsp;<?= t('acp.dash.helpdesk_open') ?></td>
							<td class="is-num">
								<span class="acp-pill <?= $queueTickets > 0 ? 'acp-pill--amber' : 'acp-pill--green' ?>"><?= (int)$queueTickets ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('helpdesk')) ?>"><?= t('acp.dash.review') ?></a></td>
						</tr>
						<tr>
							<td><i class="fa fa-picture-o is-muted"></i> &nbsp;<?= t('acp.dash.images_pending') ?></td>
							<td class="is-num">
								<span class="acp-pill <?= $queueImages > 0 ? 'acp-pill--amber' : 'acp-pill--green' ?>"><?= (int)$queueImages ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('gallery')) ?>"><?= t('acp.dash.review') ?></a></td>
						</tr>
						<tr>
							<td><i class="fa fa-comments-o is-muted"></i> &nbsp;<?= t('acp.dash.feedback_open') ?></td>
							<td class="is-num">
								<span class="acp-pill <?= $queueFeedback > 0 ? 'acp-pill--amber' : 'acp-pill--green' ?>"><?= (int)$queueFeedback ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_site('forum.php?cat=4')) ?>"><?= t('acp.dash.open') ?></a></td>
						</tr>
						<tr>
							<td><i class="fa fa-shopping-cart is-muted"></i> &nbsp;<?= t('acp.dash.orders_pending') ?></td>
							<td class="is-num">
								<span class="acp-pill <?= $statOrders > 0 ? 'acp-pill--blue' : 'acp-pill--green' ?>"><?= (int)$statOrders ?></span>
							</td>
							<td class="is-nowrap is-num"><a href="<?= h(acp_url('shop_orders')) ?>"><?= t('acp.dash.open') ?></a></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<!-- ------------------------------------------------------ Environment -->
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.dash.env_title') ?></h2>
			<p><?= t('acp.dash.env_sub') ?></p>
		</header>
		<div class="acp-card-body">
			<dl class="acp-dl">
				<dt>ZnoteX</dt>
				<dd><?= h($version) ?><?= is_array($znote) && isset($znote['version']) ? '' : ' <span class="acp-pill acp-pill--red">'. t('acp.dash.znote_table_missing') .'</span>' ?></dd>

				<dt>PHP</dt>
				<dd><?= h(PHP_VERSION) ?></dd>

				<dt><?= t('acp.dash.server_engine') ?></dt>
				<dd><span class="acp-pill acp-pill--blue"><?= h(serverEngineReal()) ?></span></dd>

				<dt><?= t('acp.dash.database') ?></dt>
				<dd><?= h($config['sqlDatabase'] ?? '') ?> @ <?= h($config['sqlHost'] ?? '') ?></dd>

				<dt><?= t('acp.dash.site_url') ?></dt>
				<dd><a href="<?= h($config['site_url'] ?? '#') ?>" target="_blank" rel="noopener"><?= h($config['site_url'] ?? '') ?></a></dd>

				<dt><?= t('acp.dash.installed') ?></dt>
				<dd><?= is_array($znote) && !empty($znote['installed']) ? h(getClock((int)$znote['installed'], true)) : '&mdash;' ?></dd>

				<dt><?= t('acp.dash.last_cache') ?></dt>
				<dd><?= is_array($znote) && !empty($znote['cached']) ? h(getClock((int)$znote['cached'], true)) : '&mdash;' ?></dd>

				<dt><?= t('acp.dash.two_factor') ?></dt>
				<dd><?= !empty($config['twoFactorAuthenticator'])
					? '<span class="acp-pill acp-pill--green">'. t('acp.dash.enabled') .'</span>'
					: '<span class="acp-pill acp-pill--grey">'. t('acp.dash.disabled') .'</span>' ?></dd>
			</dl>
		</div>
	</section>
</div>

<div class="acp-grid acp-grid--3">

	<!-- ------------------------------------------------- Latest accounts -->
	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t('acp.dash.newest_accounts') ?></h2></header>
		<div class="acp-card-body is-flush">
			<?php if (is_array($latestAccounts) && $latestAccounts): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th><?= t('acp.dash.col_account') ?></th><th><?= t('acp.dash.col_created') ?></th><th class="is-num"><?= t('acp.dash.col_points') ?></th></tr>
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
				<?php acp_empty(t('acp.dash.no_accounts'), 'fa-user-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<!-- -------------------------------------------------- Latest players -->
	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t('acp.dash.newest_characters') ?></h2></header>
		<div class="acp-card-body is-flush">
			<?php if (is_array($latestPlayers) && $latestPlayers): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th><?= t('acp.dash.col_name') ?></th><th><?= t('acp.dash.col_vocation') ?></th><th class="is-num"><?= t('acp.dash.col_level') ?></th></tr>
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
				<?php acp_empty(t('acp.dash.no_characters'), 'fa-user-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<!-- ---------------------------------------------------- Top balances -->
	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t('acp.dash.top_balances') ?></h2></header>
		<div class="acp-card-body is-flush">
			<?php if (is_array($topPoints) && $topPoints): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th>#</th><th><?= t('acp.dash.col_account') ?></th><th class="is-num"><?= t('acp.dash.col_points') ?></th></tr>
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
				<?php acp_empty(t('acp.dash.no_points'), 'fa-diamond'); ?>
			<?php endif; ?>
		</div>
	</section>
</div>
