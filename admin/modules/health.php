<?php
/**
 * Title: System Health
 * Icon: fa-heartbeat
 * Group: Operations
 * Order: 10
 * Description: PHP, database, cache, disk and migrations at a glance.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_health_bytes(int $bytes): string {
	if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
	if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
	if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
	return $bytes . ' B';
}

// --- PHP ---------------------------------------------------------------
$phpChecks = array(
	array('PHP version', PHP_VERSION_ID >= 80100, PHP_VERSION),
);
foreach (array('mysqli' => true, 'curl' => false, 'openssl' => false, 'gd' => false, 'zip' => false, 'mbstring' => false) as $ext => $required) {
	$phpChecks[] = array(
		'Extension: ' . $ext,
		extension_loaded($ext),
		extension_loaded($ext) ? 'Loaded' : ($required ? 'Missing (required)' : 'Missing (optional)'),
	);
}

// --- Database ------------------------------------------------------------
$dbSize = db()->fetchOne("
	SELECT
		ROUND(SUM(data_length + index_length)) AS `bytes`,
		COUNT(*) AS `tables`
	FROM `information_schema`.`tables`
	WHERE `table_schema` = DATABASE();
");
$dbBytes = (int)($dbSize['bytes'] ?? 0);
$dbTables = (int)($dbSize['tables'] ?? 0);

// --- Cache -----------------------------------------------------------
$cacheStats = function_exists('znote_cache_stats') ? znote_cache_stats() : array('files' => 0, 'bytes' => 0, 'apcu_available' => false);

// --- Migrations --------------------------------------------------------
$migrationStatus = function_exists('znote_migrations_status') ? znote_migrations_status() : array();
$pendingMigrations = 0;
foreach ($migrationStatus as $m) {
	if (($m['state'] ?? '') === 'pending') $pendingMigrations++;
}

// --- Disk --------------------------------------------------------------
$diskFree = @disk_free_space(dirname(__DIR__, 2));
$diskTotal = @disk_total_space(dirname(__DIR__, 2));

// --- Error log -----------------------------------------------------------
$errorLogPath = trim((string)(setting('error_log:path', '') ?: (string)@ini_get('error_log')));
$errorLogSize = ($errorLogPath !== '' && is_file($errorLogPath)) ? (int)filesize($errorLogPath) : null;
$errorLogModified = ($errorLogPath !== '' && is_file($errorLogPath)) ? (int)filemtime($errorLogPath) : null;

// --- Backups -------------------------------------------------------------
$backups = function_exists('znote_backups_list') ? znote_backups_list() : array();
$lastBackup = $backups[0] ?? null;
?>

<div class="acp-stats">
	<?php
	acp_stat(t_default('acp.health.stat_db_size', 'Database size'), acp_health_bytes($dbBytes), 'fa-database', null, 'blue');
	acp_stat(t_default('acp.health.stat_cache', 'Cache entries'), number_format((int)($cacheStats['files'] ?? 0)), 'fa-bolt', acp_url('settings'), 'purple');
	acp_stat(t_default('acp.health.stat_migrations', 'Pending migrations'), $pendingMigrations, 'fa-database', acp_url('migrations'), $pendingMigrations > 0 ? 'amber' : 'green');
	acp_stat(t_default('acp.health.stat_backup', 'Last backup'), $lastBackup ? h(getClock((int)$lastBackup['time'], true)) : t_default('acp.health.no_backup', 'None yet'), 'fa-archive', acp_url('backups'), $lastBackup ? 'green' : 'amber');
	?>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t_default('acp.health.php_title', 'PHP & extensions') ?></h2>
		</header>
		<div class="acp-card-body is-flush">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<tbody>
						<?php foreach ($phpChecks as $check): ?>
							<tr>
								<td><?= h($check[0]) ?></td>
								<td class="is-num">
									<span class="acp-pill acp-pill--<?= $check[1] ? 'green' : 'amber' ?>"><?= h($check[2]) ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t_default('acp.health.storage_title', 'Storage') ?></h2>
		</header>
		<div class="acp-card-body is-flush">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<tbody>
						<tr>
							<td><?= t_default('acp.health.db_tables', 'Database tables') ?></td>
							<td class="is-num"><?= number_format($dbTables) ?></td>
						</tr>
						<tr>
							<td><?= t_default('acp.health.cache_size', 'Cache on disk') ?></td>
							<td class="is-num"><?= h(acp_health_bytes((int)($cacheStats['bytes'] ?? 0))) ?></td>
						</tr>
						<tr>
							<td><?= t_default('acp.health.apcu', 'APCu memory cache') ?></td>
							<td class="is-num">
								<span class="acp-pill acp-pill--<?= !empty($cacheStats['apcu_available']) ? 'green' : 'grey' ?>">
									<?= !empty($cacheStats['apcu_available']) ? t_default('acp.health.available', 'Available') : t_default('acp.health.unavailable', 'Unavailable') ?>
								</span>
							</td>
						</tr>
						<?php if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0): ?>
							<tr>
								<td><?= t_default('acp.health.disk_free', 'Disk free') ?></td>
								<td class="is-num"><?= h(acp_health_bytes((int)$diskFree)) ?> / <?= h(acp_health_bytes((int)$diskTotal)) ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.health.errorlog_title', 'Error log') ?></h2>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('error_log')) ?>" style="margin-left:auto;">
			<?= t_default('acp.health.errorlog_open', 'Open') ?>
		</a>
	</header>
	<div class="acp-card-body">
		<?php if ($errorLogSize !== null): ?>
			<p>
				<?= t_default('acp.health.errorlog_summary', '{path} - {size}, last written {when}.', [
					'path' => '<code>' . h($errorLogPath) . '</code>',
					'size' => h(acp_health_bytes($errorLogSize)),
					'when' => h(getClock((int)$errorLogModified, true)),
				]) ?>
			</p>
		<?php else: ?>
			<p class="is-muted"><?= t_default('acp.health.errorlog_none', 'No log file found at the configured or auto-detected path.') ?></p>
		<?php endif; ?>
	</div>
</section>
