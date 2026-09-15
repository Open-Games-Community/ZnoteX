<?php
/**
 * Title: Login Attempts
 * Icon: fa-lock
 * Group: Operations
 * Order: 50
 * Description: Recent login attempts and which IPs are currently locked out.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

if (!znote_table_exists('znote_login_attempts')) {
	acp_flash_error(t_default('acp.lattempt.no_table', 'Run the pending database migration first (Admin Panel > Migrations).'));
	acp_redirect('migrations');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'clear_ip') {
	$ip = substr(trim((string)($_POST['ip'] ?? '')), 0, 45);
	if ($ip !== '') {
		db()->execute("DELETE FROM `znote_login_attempts` WHERE `ip` = ? AND `success` = 0;", [$ip]);
		acp_log('login_guard.clear_ip', $ip);
		acp_flash_success(t_default('acp.lattempt.cleared', 'Cleared - that IP can log in again immediately.'));
	}
	acp_redirect('login_attempts');
}

$cfg = znote_login_guard_config();

$lockedRows = db()->fetchAll("
	SELECT `ip`, COUNT(*) AS `failures`, MAX(`created_at`) AS `last_failure`
	FROM `znote_login_attempts`
	WHERE `success` = 0 AND `created_at` >= ?
	GROUP BY `ip`
	HAVING `failures` >= ?
	ORDER BY `last_failure` DESC;
", [time() - $cfg['window_seconds'], $cfg['threshold']]);
$lockedRows = is_array($lockedRows) ? $lockedRows : array();

$recent = db()->fetchAll("
	SELECT `ip`, `username`, `success`, `created_at`
	FROM `znote_login_attempts`
	ORDER BY `id` DESC
	LIMIT 100;
");
$recent = is_array($recent) ? $recent : array();
?>

<div class="acp-stats">
	<?php
	acp_stat(t_default('acp.lattempt.stat_locked', 'IPs locked out now'), count($lockedRows), 'fa-lock', null, count($lockedRows) > 0 ? 'red' : 'green');
	acp_stat(t_default('acp.lattempt.stat_guard', 'Protection'), $cfg['enabled'] ? t_default('acp.lattempt.on', 'On') : t_default('acp.lattempt.off', 'Off'), 'fa-shield', acp_url('settings'), $cfg['enabled'] ? 'green' : 'grey');
	?>
</div>

<?php if ($lockedRows): ?>
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t_default('acp.lattempt.locked_title', 'Currently locked out') ?></h2>
		</header>
		<div class="acp-card-body is-flush">
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th><?= t_default('acp.lattempt.col_ip', 'IP') ?></th>
							<th class="is-num"><?= t_default('acp.lattempt.col_failures', 'Failures') ?></th>
							<th><?= t_default('acp.lattempt.col_last', 'Last attempt') ?></th>
							<th class="is-num"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($lockedRows as $row): ?>
							<tr>
								<td><code><?= h((string)$row['ip']) ?></code></td>
								<td class="is-num"><?= (int)$row['failures'] ?></td>
								<td class="is-nowrap is-muted"><?= h(getClock((int)$row['last_failure'], true)) ?></td>
								<td class="is-num">
									<form class="acp-inline-form" method="post" data-confirm="<?= h(t_default('acp.lattempt.confirm_clear', 'Let this IP try again immediately?')) ?>">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="do" value="clear_ip">
										<input type="hidden" name="ip" value="<?= h((string)$row['ip']) ?>">
										<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit"><?= t_default('acp.lattempt.clear_btn', 'Unlock') ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.lattempt.recent_title', 'Last 100 attempts') ?></h2>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($recent): ?>
			<div class="acp-table-wrap">
				<table class="acp-table" data-sortable>
					<thead>
						<tr>
							<th><?= t_default('acp.lattempt.col_when', 'When') ?></th>
							<th><?= t_default('acp.lattempt.col_ip', 'IP') ?></th>
							<th><?= t_default('acp.lattempt.col_username', 'Username tried') ?></th>
							<th><?= t_default('acp.lattempt.col_result', 'Result') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($recent as $row): ?>
							<tr>
								<td class="is-nowrap is-muted"><?= h(getClock((int)$row['created_at'], true)) ?></td>
								<td><code><?= h((string)$row['ip']) ?></code></td>
								<td><?= h((string)$row['username']) ?></td>
								<td>
									<span class="acp-pill acp-pill--<?= $row['success'] ? 'green' : 'red' ?>">
										<?= $row['success'] ? t_default('acp.lattempt.success', 'Success') : t_default('acp.lattempt.failed', 'Failed') ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty(t_default('acp.lattempt.empty', 'No login attempts recorded yet.'), 'fa-lock'); ?>
		<?php endif; ?>
	</div>
</section>
