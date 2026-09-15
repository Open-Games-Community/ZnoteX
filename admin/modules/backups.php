<?php
/**
 * Title: Backups
 * Icon: fa-archive
 * Group: Operations
 * Order: 20
 * Description: Create, download and manage database backups.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$retentionKey = 'config:backups.retention';
$retention = max(1, (int)setting($retentionKey, '10'));

// ---------------------------------------------------------------------------
// Download - streams the file directly, bypassing the normal page render.
// ---------------------------------------------------------------------------
if (($_GET['do'] ?? '') === 'download') {
	$path = znote_backups_path((string)($_GET['name'] ?? ''));
	if ($path === null) {
		http_response_code(404);
		die('No such backup.');
	}

	acp_log('backups.download', basename($path));

	while (function_exists('ob_get_level') && ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/gzip');
	header('Content-Disposition: attachment; filename="' . basename($path) . '"');
	header('Content-Length: ' . filesize($path));
	readfile($path);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$do = (string)($_POST['do'] ?? '');

	if ($do === 'create') {
		list($ok, $result) = znote_backups_create();
		if ($ok) {
			$removed = znote_backups_prune($retention);
			acp_log('backups.create', $result, ['pruned' => $removed]);
			acp_flash_success(t_default('acp.bkp.created', 'Backup created: {name}.', ['name' => h($result)]));
		} else {
			acp_flash_error(t_default('acp.bkp.create_failed', 'Backup failed: {error}', ['error' => h($result)]));
		}
		acp_redirect('backups');
	}

	if ($do === 'delete') {
		$name = (string)($_POST['name'] ?? '');
		if (znote_backups_delete($name)) {
			acp_log('backups.delete', $name);
			acp_flash_success(t_default('acp.bkp.deleted', 'Backup deleted.'));
		} else {
			acp_flash_error(t_default('acp.bkp.delete_failed', 'Could not delete that backup.'));
		}
		acp_redirect('backups');
	}

	if ($do === 'retention') {
		$value = max(1, min(100, intv($_POST['retention'] ?? 10)));
		setting_set($retentionKey, (string)$value);
		acp_log('backups.retention', (string)$value);
		acp_flash_success(t_default('acp.bkp.retention_saved', 'Kept backups set to {n}.', ['n' => $value]));
		acp_redirect('backups');
	}
}

$backups = znote_backups_list();
$totalSize = array_sum(array_column($backups, 'size'));
?>

<div class="acp-stats">
	<?php
	acp_stat(t_default('acp.bkp.stat_count', 'Backups stored'), count($backups), 'fa-life-ring', null, 'blue');
	acp_stat(t_default('acp.bkp.stat_size', 'Total size'), serverdata_human_size($totalSize), 'fa-hdd-o', null, 'purple');
	?>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.bkp.create_title', 'Create a backup') ?></h2>
		<p><?= t_default('acp.bkp.create_sub', 'A full SQL dump of the database, compressed. Restorable with any MySQL client - nothing ZnoteX-specific about the file.') ?></p>
	</header>
	<div class="acp-card-body">
		<form method="post" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="do" value="create">
			<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-plus"></i> <?= t_default('acp.bkp.create_btn', 'Create backup now') ?></button>
			<span class="acp-hint"><?= t_default('acp.bkp.create_hint', 'A large database can take a while - the page waits for it to finish.') ?></span>
		</form>

		<form method="post" style="display:flex;align-items:center;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid var(--acp-line);">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="do" value="retention">
			<label class="acp-label" for="bkp_retention" style="margin:0;"><?= t_default('acp.bkp.retention_label', 'Keep the last') ?></label>
			<input class="acp-input" id="bkp_retention" name="retention" type="number" min="1" max="100" value="<?= $retention ?>" style="width:80px;">
			<span class="is-muted"><?= t_default('acp.bkp.retention_suffix', 'backups (older ones are deleted right after a new one is made)') ?></span>
			<button class="acp-btn acp-btn--sm" type="submit"><i class="fa fa-check"></i> <?= t_default('acp.bkp.retention_save_btn', 'Save') ?></button>
		</form>
	</div>
</section>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.bkp.list_title', 'Existing backups') ?></h2>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($backups): ?>
			<div class="acp-table-wrap">
				<table class="acp-table" data-sortable>
					<thead>
						<tr>
							<th><?= t_default('acp.bkp.col_name', 'File') ?></th>
							<th><?= t_default('acp.bkp.col_date', 'Created') ?></th>
							<th class="is-num"><?= t_default('acp.bkp.col_size', 'Size') ?></th>
							<th class="is-num"><?= t_default('acp.bkp.col_actions', 'Actions') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($backups as $backup): ?>
							<tr>
								<td><code><?= h($backup['name']) ?></code></td>
								<td class="is-nowrap is-muted"><?= h(getClock($backup['time'], true)) ?></td>
								<td class="is-num"><?= h(number_format($backup['size'] / 1048576, 2)) ?> MB</td>
								<td class="is-nowrap is-num">
									<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('backups', ['do' => 'download', 'name' => $backup['name']])) ?>">
										<i class="fa fa-download"></i>
									</a>
									<form class="acp-inline-form" method="post" data-confirm="<?= h(t_default('acp.bkp.confirm_delete', 'Delete this backup? This cannot be undone.')) ?>">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="do" value="delete">
										<input type="hidden" name="name" value="<?= h($backup['name']) ?>">
										<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-trash"></i></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty(t_default('acp.bkp.empty', 'No backups yet.'), 'fa-life-ring'); ?>
		<?php endif; ?>
	</div>
</section>
