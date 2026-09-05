<?php
/**
 * Title: Admin Log
 * Icon: fa-history
 * Group: Overview
 * Order: 30
 * Description: Who did what from the admin panel, and when.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_log_action_label(string $action): string {
	$key   = 'acp.log.action.' . $action;
	$label = t($key);
	if ($label !== $key) {
		return $label;
	}
	$parts = explode('.', $action, 2);
	$words = str_replace(array('_', '-'), ' ', end($parts));
	return $words !== '' ? ucfirst($words) : $action;
}

$hasTable = acp_log_table_exists();

$days = (string)($_GET['days'] ?? 'all');
if (!in_array($days, array('1', '7', '30', '90', 'all'), true)) {
	$days = 'all';
}

$q      = trim((string)($_GET['q'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$page   = max(1, intv($_GET['page'] ?? 1));
$perPage = 40;

$where = array();
if ($hasTable) {
	if ($days !== 'all') {
		$since   = time() - ((int)$days * 86400);
		$where[] = "`created` >= {$since}";
	}
	if ($q !== '') {
		$qEsc    = esc($q);
		$where[] = "(`target` LIKE '%{$qEsc}%' OR `admin_name` LIKE '%{$qEsc}%' OR `action` LIKE '%{$qEsc}%' OR `details` LIKE '%{$qEsc}%')";
	}
	if ($action !== '') {
		$where[] = "`action` = '" . esc($action) . "'";
	}
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total      = $hasTable ? acp_count("SELECT COUNT(*) AS `c` FROM `znote_admin_log` {$whereSql};") : 0;
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$rows = array();
if ($hasTable) {
	$rows = mysql_select_multi("
		SELECT `id`, `admin_id`, `admin_name`, `action`, `target`, `details`, `ip`, `created`
		FROM `znote_admin_log`
		{$whereSql}
		ORDER BY `id` DESC
		LIMIT {$offset}, {$perPage};
	");
	$rows = is_array($rows) ? $rows : array();
}

$actionOptions = array();
if ($hasTable) {
	$actionRows = mysql_select_multi("SELECT DISTINCT `action` FROM `znote_admin_log` ORDER BY `action` ASC;");
	if (is_array($actionRows)) {
		foreach ($actionRows as $row) {
			$actionOptions[] = (string)$row['action'];
		}
	}
}

$eventsToday = $hasTable ? acp_count("SELECT COUNT(*) AS `c` FROM `znote_admin_log` WHERE `created` >= " . (time() - 86400) . ";") : 0;
$eventsAll   = $hasTable ? acp_count("SELECT COUNT(*) AS `c` FROM `znote_admin_log`;") : 0;
$adminsActive = $hasTable ? acp_count("SELECT COUNT(DISTINCT `admin_id`) AS `c` FROM `znote_admin_log` WHERE `created` >= " . (time() - 30 * 86400) . ";") : 0;

function acp_log_query(array $overrides = array()): array {
	global $q, $action, $days;
	return array_filter(array_merge(
		array('q' => $q, 'action' => $action, 'days' => $days === 'all' ? null : $days),
		$overrides
	), static fn($v) => $v !== null && $v !== '');
}
?>

<?php if (!$hasTable): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			<?= t('acp.log.table_missing', [
				'table' => '<code>znote_admin_log</code>',
				'file'  => '<code>SQL/migrations/2.0.0_admin_log.sql</code>',
			]) ?>
		</span>
	</div>
<?php endif; ?>

<div class="acp-stats">
	<?php
	acp_stat(t('acp.log.stat_today'), $eventsToday, 'fa-clock-o', null, 'blue');
	acp_stat(t('acp.log.stat_total'), $eventsAll, 'fa-database', null, 'purple');
	acp_stat(t('acp.log.stat_admins'), $adminsActive, 'fa-users', null, 'teal');
	?>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t('acp.log.title') ?></h2>
		<p><?= t('acp.log.sub') ?></p>
	</header>
	<div class="acp-card-body">
		<form method="get" class="acp-row">
			<input type="hidden" name="p" value="adminlog">
			<div class="acp-field" style="flex:2;min-width:220px;">
				<label class="acp-label" for="q"><?= t('acp.log.search_label') ?></label>
				<input class="acp-input" id="q" name="q" value="<?= h($q) ?>" placeholder="<?= h(t('acp.log.search_placeholder')) ?>">
			</div>
			<div class="acp-field">
				<label class="acp-label" for="action"><?= t('acp.log.action_label') ?></label>
				<select class="acp-select" id="action" name="action">
					<option value=""><?= t('acp.log.all_actions') ?></option>
					<?php foreach ($actionOptions as $opt): ?>
						<option value="<?= h($opt) ?>" <?= $opt === $action ? 'selected' : '' ?>><?= h(acp_log_action_label($opt)) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="acp-field">
				<label class="acp-label" for="days"><?= t('acp.log.period_label') ?></label>
				<select class="acp-select" id="days" name="days">
					<?php foreach (array('1' => t('acp.vis.today'), '7' => t('acp.vis.7days'), '30' => t('acp.vis.30days'), '90' => t('acp.vis.90days'), 'all' => t('acp.log.all_time')) as $val => $label): ?>
						<option value="<?= h($val) ?>" <?= $val === $days ? 'selected' : '' ?>><?= h($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="acp-actions">
				<button class="acp-btn" type="submit"><i class="fa fa-filter"></i> <?= t('acp.log.filter') ?></button>
				<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('adminlog')) ?>"><?= t('acp.log.clear') ?></a>
			</div>
		</form>
	</div>
</section>

<section class="acp-card">
	<div class="acp-card-body is-flush">
		<?php if ($rows): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th><?= t('acp.log.col_date') ?></th>
							<th><?= t('acp.log.col_admin') ?></th>
							<th><?= t('acp.log.col_action') ?></th>
							<th><?= t('acp.log.col_target') ?></th>
							<th><?= t('acp.log.col_details') ?></th>
							<th><?= t('acp.log.col_ip') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $row):
							$details = array();
							if ((string)$row['details'] !== '') {
								$decoded = json_decode((string)$row['details'], true);
								if (is_array($decoded)) {
									$details = $decoded;
								}
							}
						?>
							<tr>
								<td class="is-nowrap"><?= h(getClock((int)$row['created'], true, true)) ?></td>
								<td class="is-nowrap">
									<?php if ((int)$row['admin_id'] > 0): ?>
										<a href="<?= h(acp_url('accounts', array('id' => (int)$row['admin_id']))) ?>"><?= h((string)$row['admin_name']) ?></a>
									<?php else: ?>
										<span class="is-muted"><?= h((string)$row['admin_name'] !== '' ? (string)$row['admin_name'] : '—') ?></span>
									<?php endif; ?>
								</td>
								<td><span class="acp-pill acp-pill--blue"><?= h(acp_log_action_label((string)$row['action'])) ?></span></td>
								<td><?= $row['target'] !== '' ? '<strong>' . h((string)$row['target']) . '</strong>' : '<span class="is-muted">—</span>' ?></td>
								<td class="is-muted">
									<?php if ($details): ?>
										<?php
										$pairs = array();
										foreach ($details as $k => $v) {
											if (is_array($v)) {
												$v = implode(', ', array_map('strval', $v));
											}
											$pairs[] = '<code>' . h((string)$k) . '</code>: ' . h((string)$v);
										}
										echo implode('<br>', $pairs);
										?>
									<?php else: ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td class="is-nowrap is-muted"><code><?= h((string)$row['ip']) ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ($totalPages > 1): ?>
				<div class="acp-toolbar">
					<span class="is-muted"><?= t('acp.log.page_of', ['page' => $page, 'total' => $totalPages, 'n' => number_format($total)]) ?></span>
					<div class="acp-actions is-tight">
						<?php if ($page > 1): ?>
							<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('adminlog', acp_log_query(array('page' => (string)($page - 1))))) ?>"><?= t('common.previous') ?></a>
						<?php endif; ?>
						<?php if ($page < $totalPages): ?>
							<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('adminlog', acp_log_query(array('page' => (string)($page + 1))))) ?>"><?= t('common.next') ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php else: ?>
			<?php acp_empty(t('acp.log.empty'), 'fa-history'); ?>
		<?php endif; ?>
	</div>
</section>
