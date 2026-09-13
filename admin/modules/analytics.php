<?php
/**
 * Title: Analytics
 * Icon: fa-bar-chart
 * Group: Overview
 * Order: 15
 * Description: Accounts, activity, shop revenue and where your players come from.
 */

/*
 * Everything here is read-only and cached for a few minutes (engine/cache/acp_analytics),
 * so opening this page never costs more than one batch of queries per cache
 * window, no matter how many admins are looking at it.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_analytics_period_count(string $table, string $column, int $since): int {
	if (!znote_table_exists($table) || !znote_column_exists($table, $column)) {
		return 0;
	}
	return acp_count("SELECT COUNT(*) AS `c` FROM `{$table}` WHERE `{$column}` >= ?;", [$since]);
}

/** Recent lines in the PHP error log that came from a plugin hook. */
function acp_analytics_plugin_errors(int $sinceDays): int {
	$path = trim((string)(@ini_get('error_log') ?: ''));
	if ($path === '' || !is_file($path) || !is_readable($path)) {
		return 0;
	}

	$handle = @fopen($path, 'rb');
	if ($handle === false) {
		return 0;
	}

	$cutoff = time() - ($sinceDays * 86400);
	$count = 0;
	$pattern = '/\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})[^\]]*\].*\[ZnoteX plugin\]/';

	while (($line = fgets($handle)) !== false) {
		if (strpos($line, '[ZnoteX plugin]') === false) {
			continue;
		}
		if (preg_match($pattern, $line, $m) && ($ts = strtotime($m[1])) !== false && $ts < $cutoff) {
			continue;
		}
		$count++;
	}
	fclose($handle);

	return $count;
}

function znote_analytics_snapshot(): array {
	$now = time();
	$day = 86400;

	$data = array();

	// -------------------------------------------------------------- Accounts
	$data['accounts_24h'] = acp_analytics_period_count('znote_accounts', 'created', $now - $day);
	$data['accounts_7d']  = acp_analytics_period_count('znote_accounts', 'created', $now - 7 * $day);
	$data['accounts_30d'] = acp_analytics_period_count('znote_accounts', 'created', $now - 30 * $day);

	$data['accounts_daily'] = array();
	if (znote_table_exists('znote_accounts')) {
		$rows = db()->fetchAll("
			SELECT FLOOR((? - `created`) / {$day}) AS `days_ago`, COUNT(*) AS `c`
			FROM `znote_accounts`
			WHERE `created` >= ?
			GROUP BY `days_ago`;
		", [$now, $now - 14 * $day]);
		$byDay = array();
		foreach ((is_array($rows) ? $rows : array()) as $row) {
			$byDay[(int)$row['days_ago']] = (int)$row['c'];
		}
		for ($i = 13; $i >= 0; $i--) {
			$data['accounts_daily'][] = array('label' => date('M j', $now - $i * $day), 'count' => $byDay[$i] ?? 0);
		}
	}

	// ---------------------------------------------------------- Active players
	$data['active_24h'] = acp_analytics_period_count('players', 'lastlogin', $now - $day);
	$data['active_7d']  = acp_analytics_period_count('players', 'lastlogin', $now - 7 * $day);
	$data['active_30d'] = acp_analytics_period_count('players', 'lastlogin', $now - 30 * $day);

	// New vs returning, last 30 days: a returning player logged in during the
	// window but their account existed before it started.
	$data['new_players_30d'] = 0;
	$data['returning_players_30d'] = 0;
	if (znote_table_exists('players') && znote_column_exists('players', 'lastlogin') && znote_table_exists('accounts') && znote_column_exists('accounts', 'created')) {
		$windowStart = $now - 30 * $day;
		$data['new_players_30d'] = acp_count("
			SELECT COUNT(DISTINCT `p`.`id`) AS `c`
			FROM `players` `p`
			INNER JOIN `accounts` `a` ON `a`.`id` = `p`.`account_id`
			WHERE `p`.`lastlogin` >= ? AND `a`.`created` >= ?;
		", [$windowStart, $windowStart]);
		$data['returning_players_30d'] = acp_count("
			SELECT COUNT(DISTINCT `p`.`id`) AS `c`
			FROM `players` `p`
			INNER JOIN `accounts` `a` ON `a`.`id` = `p`.`account_id`
			WHERE `p`.`lastlogin` >= ? AND `a`.`created` < ?;
		", [$windowStart, $windowStart]);
	}

	// ------------------------------------------------------------- Peak online
	$record = znote_record_get();
	$data['peak_online'] = $record['players'];
	$data['peak_online_at'] = $record['time'];
	$data['online_now'] = znote_server_adapter()->onlineCount();

	// --------------------------------------------------------- Shop / revenue
	$data['orders_30d'] = acp_analytics_period_count('znote_shop_orders', 'time', $now - 30 * $day);

	$data['revenue_30d'] = 0.0;
	$data['revenue_currency'] = '';
	$data['payments_failed_30d'] = 0;
	$data['payments_pending_30d'] = 0;
	if (znote_table_exists('znote_payment_transactions')) {
		$since = $now - 30 * $day;
		$paid = db()->fetchOne("
			SELECT COALESCE(SUM(`price`), 0) AS `total`, MAX(`currency`) AS `currency`, COUNT(*) AS `c`
			FROM `znote_payment_transactions`
			WHERE `credited` = 1 AND `created_at` >= ?;
		", [$since]);
		if (is_array($paid)) {
			$data['revenue_30d'] = (float)$paid['total'];
			$data['revenue_currency'] = (string)($paid['currency'] ?? '');
		}
		$data['payments_failed_30d'] = acp_count("
			SELECT COUNT(*) AS `c` FROM `znote_payment_transactions`
			WHERE `credited` = 0 AND `created_at` >= ?
			AND `status` IN ('failed', 'declined', 'cancelled', 'expired', 'error', 'not_paid', 'not_approved');
		", [$since]);
		$data['payments_pending_30d'] = acp_count("
			SELECT COUNT(*) AS `c` FROM `znote_payment_transactions`
			WHERE `credited` = 0 AND `created_at` >= ?
			AND `status` NOT IN ('failed', 'declined', 'cancelled', 'expired', 'error', 'not_paid', 'not_approved');
		", [$since]);
	}

	// -------------------------------------------------------------- Countries
	$data['top_countries'] = array();
	if (znote_table_exists('znote_accounts') && znote_column_exists('znote_accounts', 'flag')) {
		$rows = db()->fetchAll("
			SELECT `flag`, COUNT(*) AS `c`
			FROM `znote_accounts`
			WHERE `flag` <> ''
			GROUP BY `flag`
			ORDER BY `c` DESC
			LIMIT 10;
		");
		$data['top_countries'] = is_array($rows) ? $rows : array();
	}

	// ---------------------------------------------------------------- Tickets
	$data['tickets_open'] = acp_badge_helpdesk();
	$data['tickets_30d']  = acp_analytics_period_count('znote_tickets', 'creation', $now - 30 * $day);

	// ---------------------------------------------------------- Plugin errors
	$data['plugin_errors_7d'] = acp_analytics_plugin_errors(7);

	return $data;
}

$acp_analytics_cache = new Cache('engine/cache/acp_analytics');
$acp_analytics_cache->setExpiration(300);

if (isset($_GET['refresh'])) {
	$acp_analytics_cache->delete();
	acp_redirect('analytics');
}

if (!$acp_analytics_cache->hasExpired() && is_array($acp_analytics_cache->load())) {
	$stats = $acp_analytics_cache->load();
} else {
	$stats = znote_analytics_snapshot();
	$acp_analytics_cache->setContent($stats);
	$acp_analytics_cache->save();
}

$maxDaily = max(1, ...array_map(static fn($d) => (int)$d['count'], $stats['accounts_daily'] ?: array(array('count' => 0))));
?>

<div class="acp-toolbar">
	<div></div>
	<div class="acp-actions is-tight">
		<a class="acp-btn" href="<?= h(acp_url('analytics', array('refresh' => 1))) ?>"><i class="fa fa-refresh"></i> <?= t_default('acp.analytics.refresh', 'Refresh now') ?></a>
	</div>
</div>

<div class="acp-stats">
	<?php
	acp_stat(t_default('acp.analytics.accounts_24h', 'Accounts (24h)'), $stats['accounts_24h'], 'fa-user-plus', null, 'blue');
	acp_stat(t_default('acp.analytics.accounts_7d', 'Accounts (7d)'), $stats['accounts_7d'], 'fa-user-plus', null, 'blue');
	acp_stat(t_default('acp.analytics.accounts_30d', 'Accounts (30d)'), $stats['accounts_30d'], 'fa-user-plus', null, 'blue');
	acp_stat(t_default('acp.analytics.online_now', 'Online now'), $stats['online_now'], 'fa-signal', null, 'teal');
	acp_stat(t_default('acp.analytics.peak_online', 'Peak online (all-time)'), $stats['peak_online'], 'fa-line-chart', null, 'green');
	?>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t_default('acp.analytics.accounts_daily_title', 'New accounts, last 14 days') ?></h2>
		</header>
		<div class="acp-card-body">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<tbody>
						<?php foreach ($stats['accounts_daily'] as $day): ?>
							<tr>
								<td class="is-nowrap is-muted"><?= h($day['label']) ?></td>
								<td style="width:100%;">
									<div style="background:var(--acp-accent, #3b82f6); height:10px; border-radius:3px; width:<?= (int)round($day['count'] / $maxDaily * 100) ?>%;"></div>
								</td>
								<td class="is-num is-nowrap"><?= (int)$day['count'] ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t_default('acp.analytics.activity_title', 'Player activity') ?></h2>
		</header>
		<div class="acp-card-body is-flush">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<tbody>
						<tr><td><?= t_default('acp.analytics.active_24h', 'Active players (24h)') ?></td><td class="is-num"><?= (int)$stats['active_24h'] ?></td></tr>
						<tr><td><?= t_default('acp.analytics.active_7d', 'Active players (7d)') ?></td><td class="is-num"><?= (int)$stats['active_7d'] ?></td></tr>
						<tr><td><?= t_default('acp.analytics.active_30d', 'Active players (30d)') ?></td><td class="is-num"><?= (int)$stats['active_30d'] ?></td></tr>
						<tr><td><?= t_default('acp.analytics.new_players', 'New players (30d)') ?></td><td class="is-num"><?= (int)$stats['new_players_30d'] ?></td></tr>
						<tr><td><?= t_default('acp.analytics.returning_players', 'Returning players (30d)') ?></td><td class="is-num"><?= (int)$stats['returning_players_30d'] ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</section>
</div>

<div class="acp-grid acp-grid--3">

	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t_default('acp.analytics.shop_title', 'Shop (30d)') ?></h2></header>
		<div class="acp-card-body is-flush">
			<table class="acp-table">
				<tbody>
					<tr><td><?= t_default('acp.analytics.orders', 'Orders delivered') ?></td><td class="is-num"><?= (int)$stats['orders_30d'] ?></td></tr>
					<tr><td><?= t_default('acp.analytics.revenue', 'Revenue') ?></td><td class="is-num"><?= number_format($stats['revenue_30d'], 2) ?> <?= h($stats['revenue_currency']) ?></td></tr>
					<tr><td><?= t_default('acp.analytics.payments_pending', 'Payments pending') ?></td><td class="is-num"><?= (int)$stats['payments_pending_30d'] ?></td></tr>
					<tr><td><?= t_default('acp.analytics.payments_failed', 'Payments failed') ?></td><td class="is-num"><span class="acp-pill <?= $stats['payments_failed_30d'] > 0 ? 'acp-pill--red' : 'acp-pill--green' ?>"><?= (int)$stats['payments_failed_30d'] ?></span></td></tr>
				</tbody>
			</table>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t_default('acp.analytics.top_countries', 'Top countries') ?></h2></header>
		<div class="acp-card-body is-flush">
			<?php if ($stats['top_countries']): ?>
				<table class="acp-table">
					<tbody>
						<?php foreach ($stats['top_countries'] as $row): ?>
							<tr>
								<td><?= translate_flag(strtolower((string)$row['flag'])) ?> <?= h(strtoupper((string)$row['flag'])) ?></td>
								<td class="is-num"><?= (int)$row['c'] ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<?php acp_empty(t_default('acp.analytics.no_countries', 'No data yet.'), 'fa-globe'); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t_default('acp.analytics.support_title', 'Support & health') ?></h2></header>
		<div class="acp-card-body is-flush">
			<table class="acp-table">
				<tbody>
					<tr><td><?= t_default('acp.analytics.tickets_open', 'Open tickets') ?></td><td class="is-num"><?= (int)$stats['tickets_open'] ?></td></tr>
					<tr><td><?= t_default('acp.analytics.tickets_30d', 'Tickets opened (30d)') ?></td><td class="is-num"><?= (int)$stats['tickets_30d'] ?></td></tr>
					<tr><td><?= t_default('acp.analytics.plugin_errors', 'Plugin errors (7d)') ?></td><td class="is-num"><span class="acp-pill <?= $stats['plugin_errors_7d'] > 0 ? 'acp-pill--amber' : 'acp-pill--green' ?>"><?= (int)$stats['plugin_errors_7d'] ?></span></td></tr>
				</tbody>
			</table>
		</div>
	</section>
</div>
