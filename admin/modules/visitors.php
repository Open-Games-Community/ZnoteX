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
function acp_visit_types(): array {
	return array(
		0 => t('acp.vis.type_pageview'),
		1 => t('acp.vis.type_register'),
		2 => t('acp.vis.type_char_created'),
		3 => t('acp.vis.type_highscores'),
		4 => t('acp.vis.type_char_search'),
		5 => t('acp.vis.type_other'),
	);
}

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
			<?= t('acp.vis.off_notice', [
				'off'  => '<strong>' . t('acp.vis.off') . '</strong>',
				'link' => '<a href="' . h(acp_url('settings')) . '">' . t('acp.vis.settings_privacy') . '</a>',
			]) ?>
		</span>
	</div>
<?php endif; ?>

<div class="acp-toolbar">
	<div class="acp-actions is-tight">
		<?php foreach (array(1 => t('acp.vis.today'), 7 => t('acp.vis.7days'), 30 => t('acp.vis.30days'), 90 => t('acp.vis.90days')) as $d => $label): ?>
			<a class="acp-btn <?= $d === $days ? '' : 'acp-btn--ghost' ?> acp-btn--sm"
			   href="<?= h(acp_url('visitors', array('days' => $d))) ?>"><?= h($label) ?></a>
		<?php endforeach; ?>
	</div>
	<span class="is-muted"><?= t('acp.vis.events_recorded', ['n' => number_format($totalRows)]) ?></span>
</div>

<div class="acp-stats">
	<?php
	acp_stat(t('acp.vis.stat_visits'), $rowsPeriod, 'fa-eye', null, 'blue');
	acp_stat(t('acp.vis.stat_unique_period'), $uniquePeriod, 'fa-users', null, 'teal');
	acp_stat(t('acp.vis.stat_unique_all'), $uniqueAll, 'fa-globe', null, 'purple');
	acp_stat(t('acp.vis.stat_events_all'), $totalRows, 'fa-database', null, 'green');
	?>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.vis.per_day') ?></h2>
			<p><?= t('acp.vis.last_n_days', ['n' => (int)$days]) ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($perDay): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr><th><?= t('acp.vis.col_day') ?></th><th class="is-num"><?= t('acp.vis.col_visits') ?></th><th class="is-num"><?= t('acp.vis.col_unique') ?></th><th>&nbsp;</th></tr>
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
				<?php acp_empty(t('acp.vis.empty'), 'fa-line-chart'); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.vis.what_they_did') ?></h2>
			<p><?= t('acp.vis.last_n_days', ['n' => (int)$days]) ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($byType): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th><?= t('acp.vis.col_action') ?></th><th class="is-num"><?= t('acp.vis.col_count') ?></th></tr></thead>
						<tbody>
							<?php foreach ($byType as $row): ?>
								<tr>
									<td><?= h(acp_visit_types()[(int)$row['type']] ?? t('acp.vis.type_n', ['n' => (int)$row['type']])) ?></td>
									<td class="is-num"><?= number_format((int)$row['hits']) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty(t('acp.vis.empty'), 'fa-list'); ?>
			<?php endif; ?>
		</div>
	</section>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t('acp.vis.busiest') ?></h2>
		<p><?= t('acp.vis.busiest_sub') ?></p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($topIps): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr><th><?= t('acp.vis.col_ip') ?></th><th class="is-num"><?= t('acp.vis.col_visits') ?></th><th><?= t('acp.vis.col_last_seen') ?></th><th><?= t('acp.vis.col_account') ?></th></tr>
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
			<?php acp_empty(t('acp.vis.empty'), 'fa-globe'); ?>
		<?php endif; ?>
	</div>
</section>
