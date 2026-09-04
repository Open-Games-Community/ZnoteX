<?php
/**
 * Title: Visitors
 * Icon: fa-line-chart
 * Group: Overview
 * Order: 20
 * Description: Traffic ZnoteX has been recording all along.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

/**
 * ZnoteX has written to znote_visitors and znote_visitors_details on every page
 * load since forever, and until now nothing read them back - a SQL write per
 * visitor, for nobody. This page is that data, finally shown.
 *
 * Turn the collection off entirely in Settings > Privacy if you would rather
 * not keep it.
 */

// The type column, as written by znote_visitor_insert_detailed_data().
const ACP_VISIT_TYPES = array(
	0 => 'Page view',
	1 => 'Account registered',
	2 => 'Character created',
	3 => 'Highscores / listing',
	4 => 'Character searched',
	5 => 'Other form',
);

$days = intv($_GET['days'] ?? 7);
if (!in_array($days, array(1, 7, 30, 90), true)) {
	$days = 7;
}
$since = time() - ($days * 86400);

$collecting = !empty($config['log_ip']);

$totalRows   = acp_count("SELECT COUNT(*) AS `c` FROM `znote_visitors_details`;");
$uniqueAll   = acp_count("SELECT COUNT(DISTINCT `ip`) AS `c` FROM `znote_visitors_details`;");
$rowsPeriod  = acp_count("SELECT COUNT(*) AS `c` FROM `znote_visitors_details` WHERE `time` >= {$since};");
$uniquePeriod= acp_count("SELECT COUNT(DISTINCT `ip`) AS `c` FROM `znote_visitors_details` WHERE `time` >= {$since};");

// Visits per day
$perDay = mysql_select_multi("
	SELECT FROM_UNIXTIME(`time`, '%Y-%m-%d') AS `day`,
	       COUNT(*) AS `hits`,
	       COUNT(DISTINCT `ip`) AS `uniques`
	FROM `znote_visitors_details`
	WHERE `time` >= {$since}
	GROUP BY `day`
	ORDER BY `day` DESC;
");
$perDay = is_array($perDay) ? $perDay : array();

// Breakdown by what people did
$byType = mysql_select_multi("
	SELECT `type`, COUNT(*) AS `hits`
	FROM `znote_visitors_details`
	WHERE `time` >= {$since}
	GROUP BY `type`
	ORDER BY `hits` DESC;
");
$byType = is_array($byType) ? $byType : array();

// Busiest addresses
$topIps = mysql_select_multi("
	SELECT `ip`, COUNT(*) AS `hits`, MAX(`time`) AS `last`, MAX(`account_id`) AS `account_id`
	FROM `znote_visitors_details`
	WHERE `time` >= {$since}
	GROUP BY `ip`
	ORDER BY `hits` DESC
	LIMIT 15;
");
$topIps = is_array($topIps) ? $topIps : array();

$peak = 0;
foreach ($perDay as $row) {
	$peak = max($peak, (int)$row['hits']);
}
?>

<?php if (!$collecting): ?>
	<div class="acp-flash acp-flash--info">
		<i class="fa fa-info-circle"></i>
		<span>
			Visitor logging is currently <strong>off</strong>, so nothing new is being recorded.
			Anything below is history. Turn it back on in
			<a href="<?= h(acp_url('settings')) ?>">Settings &rarr; Privacy</a>.
		</span>
	</div>
<?php endif; ?>

<div class="acp-toolbar">
	<div class="acp-actions is-tight">
		<?php foreach (array(1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days') as $d => $label): ?>
			<a class="acp-btn <?= $d === $days ? '' : 'acp-btn--ghost' ?> acp-btn--sm"
			   href="<?= h(acp_url('visitors', array('days' => $d))) ?>"><?= h($label) ?></a>
		<?php endforeach; ?>
	</div>
	<span class="is-muted"><?= number_format($totalRows) ?> events on record</span>
</div>

<div class="acp-stats">
	<?php
	acp_stat('Visits, period', $rowsPeriod, 'fa-eye', null, 'blue');
	acp_stat('Unique IPs, period', $uniquePeriod, 'fa-users', null, 'teal');
	acp_stat('Unique IPs, all time', $uniqueAll, 'fa-globe', null, 'purple');
	acp_stat('Events, all time', $totalRows, 'fa-database', null, 'green');
	?>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Per day</h2>
			<p>Last <?= (int)$days ?> day<?= $days === 1 ? '' : 's' ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($perDay): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th>Day</th><th class="is-num">Visits</th><th class="is-num">Unique</th><th>&nbsp;</th></tr>
						</thead>
						<tbody>
							<?php foreach ($perDay as $row):
								$hits = (int)$row['hits'];
								$pct  = $peak > 0 ? round(($hits / $peak) * 100) : 0;
							?>
								<tr>
									<td class="is-nowrap"><?= h((string)$row['day']) ?></td>
									<td class="is-num"><strong><?= number_format($hits) ?></strong></td>
									<td class="is-num is-muted"><?= number_format((int)$row['uniques']) ?></td>
									<td style="width:45%;">
										<div style="height:8px;border-radius:4px;background:var(--acp-panel-2);overflow:hidden;">
											<div style="height:8px;width:<?= $pct ?>%;background:var(--acp-blue);"></div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('Nothing recorded in this period.', 'fa-line-chart'); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>What they did</h2>
			<p>Last <?= (int)$days ?> day<?= $days === 1 ? '' : 's' ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($byType): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th>Action</th><th class="is-num">Count</th></tr></thead>
						<tbody>
							<?php foreach ($byType as $row): ?>
								<tr>
									<td><?= h(ACP_VISIT_TYPES[(int)$row['type']] ?? ('Type ' . (int)$row['type'])) ?></td>
									<td class="is-num"><?= number_format((int)$row['hits']) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('Nothing recorded in this period.', 'fa-list'); ?>
			<?php endif; ?>
		</div>
	</section>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Busiest addresses</h2>
		<p>Useful for spotting a scraper or a stuck client</p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($topIps): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr><th>IP</th><th class="is-num">Visits</th><th>Last seen</th><th>Account</th></tr>
					</thead>
					<tbody>
						<?php foreach ($topIps as $row):
							$accountId = (int)$row['account_id'];
						?>
							<tr>
								<td class="is-nowrap"><code><?= h(long2ip((int)$row['ip'])) ?></code></td>
								<td class="is-num"><strong><?= number_format((int)$row['hits']) ?></strong></td>
								<td class="is-nowrap is-muted"><?= h(getClock((int)$row['last'], true)) ?></td>
								<td>
									<?php if ($accountId > 0): ?>
										<a href="<?= h(acp_url('accounts', array('id' => $accountId))) ?>">#<?= $accountId ?></a>
									<?php else: ?>
										<span class="is-muted">&mdash;</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('Nothing recorded in this period.', 'fa-globe'); ?>
		<?php endif; ?>
	</div>
</section>
