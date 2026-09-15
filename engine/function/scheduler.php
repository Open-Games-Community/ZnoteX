<?php


function scheduler_tasks(): array
{
	return array(
		'backups' => array(
			'label'                  => 'Automatic backups',
			'default_interval_hours' => 24,
			'run'                    => 'scheduler_run_backups',
		),
		'purge_admin_log' => array(
			'label'                  => 'Purge old admin log entries',
			'default_interval_hours' => 24,
			'run'                    => 'scheduler_run_purge_admin_log',
		),
		'health_check' => array(
			'label'                  => 'Health check',
			'default_interval_hours' => 6,
			'run'                    => 'scheduler_run_health_check',
		),
	);
}

function scheduler_enabled(string $taskId): bool
{
	return setting('scheduler:' . $taskId . ':enabled', '0') === '1';
}

function scheduler_interval_hours(string $taskId, int $default): int
{
	return max(1, (int) setting('scheduler:' . $taskId . ':interval_hours', (string) $default));
}

function scheduler_last_run(string $taskId): int
{
	return (int) setting('scheduler:' . $taskId . ':last_run', '0');
}

function scheduler_last_result(string $taskId): string
{
	return (string) setting('scheduler:' . $taskId . ':last_result', '');
}

function scheduler_mark_run(string $taskId, string $resultSummary): void
{
	setting_set('scheduler:' . $taskId . ':last_run', (string) time());
	setting_set('scheduler:' . $taskId . ':last_result', substr($resultSummary, 0, 255));
}

function scheduler_due(string $taskId, int $defaultIntervalHours): bool
{
	if (!scheduler_enabled($taskId)) {
		return false;
	}

	$interval = scheduler_interval_hours($taskId, $defaultIntervalHours);

	return (scheduler_last_run($taskId) + ($interval * 3600)) <= time();
}

function scheduler_tick(): void
{
	static $ran = false;
	if ($ran || !function_exists('setting') || !function_exists('db')) {
		return;
	}
	$ran = true;

	foreach (scheduler_tasks() as $id => $task) {
		if (!scheduler_due($id, (int) $task['default_interval_hours'])) {
			continue;
		}

		scheduler_mark_run($id, 'running');

		try {
			$summary = (string) call_user_func($task['run']);
		} catch (Throwable $e) {
			$summary = 'error: ' . $e->getMessage();
			error_log('[scheduler] task ' . $id . ' failed: ' . $e->getMessage());
		}

		scheduler_mark_run($id, $summary);

		if (function_exists('acp_log')) {
			acp_log('scheduler.' . $id, '', array('summary' => $summary));
		}
	}
}

function scheduler_run_backups(): string
{
	if (!function_exists('znote_backups_create')) {
		return 'backups module unavailable';
	}

	list($ok, $result) = znote_backups_create();
	if (!$ok) {
		return 'backup failed: ' . $result;
	}

	$retention = max(1, (int) setting('config:backups.retention', '10'));
	$removed   = function_exists('znote_backups_prune') ? znote_backups_prune($retention) : 0;

	return 'created ' . $result . ($removed > 0 ? ', pruned ' . $removed : '');
}

function scheduler_run_purge_admin_log(): string
{
	if (!function_exists('znote_table_exists') || !znote_table_exists('znote_admin_log')) {
		return 'no admin log table';
	}

	$days   = max(1, (int) setting('scheduler:purge_admin_log:keep_days', '90'));
	$cutoff = time() - ($days * 86400);

	db()->execute("DELETE FROM `znote_admin_log` WHERE `created` < ?;", array($cutoff));

	return 'purged rows older than ' . $days . 'd';
}

function scheduler_run_health_check(): string
{
	if (!function_exists('znote_health_issues')) {
		return 'health module unavailable';
	}

	$issues = znote_health_issues();
	if ($issues) {
		$details = array();
		foreach ($issues as $issue) {
			$details[] = (string) ($issue['label'] ?? '') . ': ' . (string) ($issue['detail'] ?? '');
		}
		error_log('[scheduler] health check found ' . count($issues) . ' issue(s): ' . implode('; ', $details));

		return count($issues) . ' issue(s) found';
	}

	return 'ok';
}

if (function_exists('znote_hook_register')) {
	znote_hook_register('page.footer', 'scheduler_tick');
}
