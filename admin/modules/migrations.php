<?php
/**
 * Title: Migrations
 * Icon: fa-database
 * Group: Settings
 * Order: 20
 * Description: Apply SQL updates from SQL/migrations without phpMyAdmin.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_migration_state_label(string $state): string {
	switch ($state) {
		case 'applied': return 'Applied';
		case 'pending': return 'Pending';
		case 'changed': return 'Changed after apply';
		case 'missing': return 'Applied file missing';
		default: return ucfirst($state);
	}
}

function acp_migration_state_class(string $state): string {
	switch ($state) {
		case 'applied': return 'green';
		case 'pending': return 'amber';
		case 'changed':
		case 'missing':
			return 'red';
		default: return 'grey';
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string)($_POST['do'] ?? '');

	if ($action === 'run_pending') {
		$results = znote_migrations_run_pending();
		if (!$results) {
			acp_flash_info('No pending migrations.');
		} else {
			$ok = 0;
			$failed = '';
			foreach ($results as $name => $result) {
				if (!empty($result['ok'])) {
					$ok++;
				} else {
					$failed = $name . ': ' . ($result['message'] ?? 'failed');
					break;
				}
			}

			if ($failed !== '') {
				acp_flash_error(h($failed));
			} else {
				acp_log('system.migrations', '', ['applied' => array_keys($results)]);
				acp_flash_success('Applied ' . $ok . ' migration' . ($ok === 1 ? '' : 's') . '.');
			}
		}
		acp_redirect('migrations');
	}

	if ($action === 'run_one') {
		$name = basename((string)($_POST['migration'] ?? ''));
		$result = znote_migration_run($name);
		if (!empty($result['ok'])) {
			acp_log('system.migration', $name, ['statements' => $result['statements'] ?? 0]);
			acp_flash_success(h($name) . ': ' . h((string)$result['message']));
		} else {
			acp_flash_error(h($name) . ': ' . h((string)$result['message']));
		}
		acp_redirect('migrations');
	}

	acp_redirect('migrations');
}

$status = znote_migrations_status();
$pending = array_filter($status, static fn(array $row): bool => $row['state'] === 'pending');
$applied = array_filter($status, static fn(array $row): bool => $row['state'] === 'applied');
$warnings = array_filter($status, static fn(array $row): bool => in_array($row['state'], array('changed', 'missing'), true));
?>

<div class="acp-stats">
	<?php
	acp_stat('Pending', count($pending), 'fa-clock-o', null, count($pending) ? 'amber' : 'green');
	acp_stat('Applied', count($applied), 'fa-check', null, 'blue');
	acp_stat('Warnings', count($warnings), 'fa-exclamation-triangle', null, count($warnings) ? 'red' : 'grey');
	?>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Database migrations</h2>
		<p>Runs SQL files from <code>SQL/migrations</code> and records successful executions in <code>znote_migrations</code>.</p>
	</header>
	<div class="acp-card-body">
		<?php if (!$status): ?>
			<?php acp_empty('No migration files found.', 'fa-database'); ?>
		<?php else: ?>
			<div class="acp-actions" style="margin-bottom:14px;">
				<form method="post" data-confirm="Run all pending migrations now?">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="run_pending">
					<button class="acp-btn" type="submit" <?= !$pending ? 'disabled' : '' ?>>
						<i class="fa fa-play"></i> Run pending migrations
					</button>
				</form>
			</div>

			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th>Migration</th>
							<th>Status</th>
							<th>Checksum</th>
							<th>Executed</th>
							<th>Time</th>
							<th style="width:1%">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($status as $row):
							$state = (string)$row['state'];
							$appliedRow = is_array($row['applied']) ? $row['applied'] : array();
						?>
							<tr>
								<td>
									<strong><?= h($row['name']) ?></strong>
									<?php if (!empty($row['size'])): ?>
										<br><small><?= h(number_format((int)$row['size'])) ?> bytes</small>
									<?php endif; ?>
								</td>
								<td>
									<span class="acp-pill acp-pill--<?= h(acp_migration_state_class($state)) ?>">
										<?= h(acp_migration_state_label($state)) ?>
									</span>
								</td>
								<td><code title="<?= h($row['checksum']) ?>"><?= h(substr((string)$row['checksum'], 0, 12)) ?></code></td>
								<td>
									<?php if (!empty($appliedRow['executed_at'])): ?>
										<?= h(date('Y-m-d H:i:s', (int)$appliedRow['executed_at'])) ?>
									<?php else: ?>
										<span class="is-muted">Never</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if (isset($appliedRow['execution_time_ms'])): ?>
										<?= h((string)(int)$appliedRow['execution_time_ms']) ?> ms
									<?php else: ?>
										<span class="is-muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($state === 'pending'): ?>
										<form method="post" data-confirm="Run this migration now?">
											<?= acp_csrf_field() ?>
											<input type="hidden" name="do" value="run_one">
											<input type="hidden" name="migration" value="<?= h($row['name']) ?>">
											<button class="acp-btn acp-btn--sm" type="submit">Run</button>
										</form>
									<?php elseif ($state === 'changed'): ?>
										<span class="is-muted">File changed</span>
									<?php elseif ($state === 'missing'): ?>
										<span class="is-muted">Missing</span>
									<?php else: ?>
										<span class="is-muted">Done</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</section>

<div class="acp-flash acp-flash--info">
	<i class="fa fa-info-circle"></i>
	<span>
		Use this page after pulling/updating ZnoteX. Payment IPNs and normal visitors never run migrations; only admins can apply them here.
	</span>
</div>
