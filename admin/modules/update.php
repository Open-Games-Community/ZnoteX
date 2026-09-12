<?php

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$latest = null;
$preflight = null;
$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'rollback') {
	$result = znote_update_rollback_latest();
	if ($result['ok']) {
		acp_log('update.rollback', (string)$result['version']);
		acp_flash_success('ZnoteX core files were restored to version ' . h($result['version']) . '. Additive database migrations were kept.');
	} else {
		acp_log('update.rollback_failed', '', array('error' => $result['error']));
		acp_flash_error(h($result['error']));
	}
	acp_redirect('update');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('check', 'install'), true)) {
	$latest = znote_update_latest(true);
	$preflight = znote_update_preflight($latest);
	acp_log('update.check', (string)($latest['manifest']['version'] ?? ''), array('passed' => $preflight['ok']));

	if ($action === 'install') {
		if (!$preflight['ok']) {
			acp_flash_error('The update was not started because at least one check failed.');
		} else {
			$result = znote_update_install($latest);
			if ($result['ok']) {
				acp_log('update.install', (string)$result['version'], array('backup' => $result['backup']));
				acp_flash_success('ZnoteX was updated successfully to version ' . h($result['version']) . '.');
				acp_redirect('update');
			} else {
				acp_log('update.install_failed', (string)($latest['manifest']['version'] ?? ''), array('error' => $result['error']));
				acp_flash_error(h($result['error']));
			}
		}
	}
}

if ($latest === null) {
	$latest = znote_update_latest(false);
}

$manifest = !empty($latest['ok']) ? $latest['manifest'] : array();
$backups = znote_update_backups();
$textValue = static function ($value): string {
	if (is_string($value)) {
		return $value;
	}
	if (!is_array($value)) {
		return '';
	}
	$language = strtolower((string)($GLOBALS['config']['language'] ?? 'en'));
	return (string)($value[$language] ?? $value[substr($language, 0, 2)] ?? $value['en'] ?? reset($value));
};
?>

<div class="acp-grid">
	<?php
	acp_stat('Installed version', znote_update_current_version(), 'fa-code-fork', null, 'blue');
	acp_stat('Latest stable', !empty($latest['ok']) ? (string)$manifest['version'] : 'Unavailable', 'fa-cloud-download', null, !empty($latest['available']) ? 'amber' : 'green');
	acp_stat('Update status', !empty($latest['available']) ? 'Available' : (!empty($latest['ok']) ? 'Up to date' : 'Check failed'), !empty($latest['available']) ? 'fa-arrow-up' : 'fa-check', null, !empty($latest['ok']) ? 'green' : 'red');
	?>
</div>

<?php acp_card_open('ZnoteX core update', 'Stable releases are downloaded from the official GitHub repository and accepted only after signature and integrity verification.'); ?>
	<?php if (empty($latest['ok'])): ?>
		<div style="padding:14px;border:1px solid var(--acp-red);border-radius:6px;color:var(--acp-red);">
			<i class="fa fa-times-circle"></i> <?= h($latest['error'] ?? 'The release could not be checked.') ?>
		</div>
	<?php elseif (!empty($manifest)): ?>
		<h3 style="margin-top:0;"><?= h($textValue($manifest['title'] ?? ('ZnoteX ' . $manifest['version']))) ?></h3>
		<p><?= nl2br(h($textValue($manifest['description'] ?? ($manifest['summary'] ?? '')))) ?></p>
		<?php $highlights = $manifest['highlights'] ?? array(); ?>
		<?php if (is_array($highlights) && $highlights !== array()): ?>
			<ul>
				<?php foreach ($highlights as $highlight): ?><li><?= h($textValue($highlight)) ?></li><?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if (empty($latest['available'])): ?>
			<p style="color:var(--acp-green);"><i class="fa fa-check-circle"></i> This installation already uses the latest stable release.</p>
		<?php endif; ?>
	<?php endif; ?>

	<form method="post" style="margin-top:18px;">
		<?= acp_csrf_field() ?>
		<input type="hidden" name="action" value="check">
		<button class="acp-btn" type="submit"><i class="fa fa-refresh"></i> Run full pre-installation check</button>
	</form>
<?php acp_card_close(); ?>

<?php if ($preflight !== null): ?>
	<?php acp_card_open('Pre-installation check', $preflight['ok'] ? 'Every check passed. The update can be installed.' : 'Installation is blocked until every failed check is resolved.'); ?>
		<table class="acp-table">
			<thead><tr><th style="width:52px;">Status</th><th>Check</th><th>Result</th></tr></thead>
			<tbody>
			<?php foreach ($preflight['checks'] as $check): ?>
				<tr>
					<td style="font-size:20px;color:<?= $check['ok'] ? 'var(--acp-green)' : 'var(--acp-red)' ?>;"><i class="fa <?= $check['ok'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i></td>
					<td><strong><?= h($check['label']) ?></strong></td>
					<td><?= h($check['detail']) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" style="margin-top:18px;">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="action" value="install">
			<button class="acp-btn acp-btn--green" type="submit" <?= $preflight['ok'] ? '' : 'disabled' ?>><i class="fa fa-cloud-download"></i> Install version <?= h((string)($manifest['version'] ?? '')) ?></button>
		</form>
	<?php acp_card_close(); ?>
<?php endif; ?>

<?php acp_card_open('Recovery', 'Each installation creates a complete backup of every managed file before changing the site.'); ?>
	<?php if ($backups === array()): ?>
		<p>No update backup is available yet.</p>
	<?php else: ?>
		<p>Latest backup: <strong><?= h((string)$backups[0]['journal']['version']) ?></strong> from <?= h((string)$backups[0]['journal']['created_at']) ?>.</p>
		<form method="post" onsubmit="return confirm('Restore the latest core file backup?');">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="action" value="rollback">
			<button class="acp-btn acp-btn--amber" type="submit"><i class="fa fa-undo"></i> Restore latest file backup</button>
		</form>
	<?php endif; ?>
<?php acp_card_close(); ?>
