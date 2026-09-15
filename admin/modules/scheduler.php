<?php
/**
 * Title: Task Scheduler
 * Icon: fa-clock-o
 * Group: Operations
 * Order: 40
 * Description: Recurring maintenance without a real cron - backups, admin log pruning, health checks.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$tasks = scheduler_tasks();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$do = (string) ($_POST['do'] ?? '');

	if ($do === 'save') {
		foreach ($tasks as $id => $task) {
			$enabled = !empty($_POST['enabled'][$id]) ? '1' : '0';
			$interval = max(1, intv($_POST['interval_hours'][$id] ?? $task['default_interval_hours']));

			setting_set('scheduler:' . $id . ':enabled', $enabled);
			setting_set('scheduler:' . $id . ':interval_hours', (string) $interval);

			if ($id === 'purge_admin_log') {
				$keepDays = max(1, intv($_POST['keep_days'] ?? 90));
				setting_set('scheduler:purge_admin_log:keep_days', (string) $keepDays);
			}
		}

		acp_log('scheduler.settings_save');
		acp_flash_success(t_default('acp.sched.saved', 'Scheduler settings saved.'));
		acp_redirect('scheduler');
	}

	if ($do === 'run_now') {
		$taskId = (string) ($_POST['task'] ?? '');
		if (!isset($tasks[$taskId])) {
			acp_flash_error(t_default('acp.sched.unknown_task', 'Unknown task.'));
			acp_redirect('scheduler');
		}

		try {
			$summary = (string) call_user_func($tasks[$taskId]['run']);
		} catch (Throwable $e) {
			$summary = 'error: ' . $e->getMessage();
		}

		scheduler_mark_run($taskId, $summary);
		acp_log('scheduler.' . $taskId, '', array('summary' => $summary, 'manual' => true));
		acp_flash_success(t_default('acp.sched.run_done', '{task} ran: {summary}', ['task' => $tasks[$taskId]['label'], 'summary' => $summary]));
		acp_redirect('scheduler');
	}
}
?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.sched.title', 'Task Scheduler') ?></h2>
		<p><?= t_default('acp.sched.sub', 'No real cron here - each task runs opportunistically on the next page load once its interval has passed (public pages, and any admin panel page).') ?></p>
	</header>
	<div class="acp-card-body is-flush">
		<form method="post">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="do" value="save">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th><?= t_default('acp.sched.col_task', 'Task') ?></th>
							<th><?= t_default('acp.sched.col_enabled', 'Enabled') ?></th>
							<th><?= t_default('acp.sched.col_interval', 'Every (hours)') ?></th>
							<th><?= t_default('acp.sched.col_last_run', 'Last run') ?></th>
							<th><?= t_default('acp.sched.col_last_result', 'Last result') ?></th>
							<th class="is-num"><?= t_default('acp.sched.col_actions', 'Actions') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($tasks as $id => $task): ?>
							<?php
							$lastRun = scheduler_last_run($id);
							$lastResult = scheduler_last_result($id);
							$resultTone = 'grey';
							if ($lastResult !== '') {
								if (str_starts_with($lastResult, 'error')) {
									$resultTone = 'red';
								} elseif (str_contains($lastResult, 'issue')) {
									$resultTone = 'amber';
								} else {
									$resultTone = 'green';
								}
							}
							?>
							<tr>
								<td>
									<strong><?= h(t_default('acp.sched.task.' . $id, $task['label'])) ?></strong>
									<?php if ($id === 'purge_admin_log'): ?>
										<div class="acp-field" style="margin-top:6px;">
											<label class="acp-label" for="sched_keep_days" style="font-weight:400;"><?= t_default('acp.sched.keep_days_label', 'Keep entries for (days)') ?></label>
											<input class="acp-input" id="sched_keep_days" name="keep_days" type="number" min="1" style="width:100px;" value="<?= (int) setting('scheduler:purge_admin_log:keep_days', '90') ?>">
										</div>
									<?php endif; ?>
								</td>
								<td>
									<input type="checkbox" name="enabled[<?= h($id) ?>]" value="1" <?= scheduler_enabled($id) ? 'checked' : '' ?>>
								</td>
								<td>
									<input class="acp-input" type="number" min="1" style="width:90px;" name="interval_hours[<?= h($id) ?>]" value="<?= scheduler_interval_hours($id, (int) $task['default_interval_hours']) ?>">
								</td>
								<td class="is-muted"><?= $lastRun > 0 ? h(getClock($lastRun, true)) : t_default('acp.sched.never', 'Never') ?></td>
								<td>
									<?php if ($lastResult !== ''): ?>
										<span class="acp-pill acp-pill--<?= $resultTone ?>"><?= h($lastResult) ?></span>
									<?php else: ?>
										<span class="is-muted">-</span>
									<?php endif; ?>
								</td>
								<td class="is-nowrap is-num">
									<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit" form="sched_run_<?= h($id) ?>">
										<i class="fa fa-play"></i> <?= t_default('acp.sched.run_now', 'Run now') ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="acp-actions">
				<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= t_default('acp.sched.save_btn', 'Save') ?></button>
			</div>
		</form>

		<?php foreach ($tasks as $id => $task): ?>
			<form method="post" id="sched_run_<?= h($id) ?>">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="do" value="run_now">
				<input type="hidden" name="task" value="<?= h($id) ?>">
			</form>
		<?php endforeach; ?>
	</div>
</section>
