<?php

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$latest = null;
$preflight = null;
$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('check_start', 'check_step', 'install_start', 'install_step'), true)) {
	header('Content-Type: application/json');

	if (!acp_verify_csrf()) {
		http_response_code(400);
		echo json_encode(array('ok' => false, 'error' => 'Your session expired. Reload the page and try again.'));
		exit;
	}

	if ($action === 'check_start') {
		$latest = znote_update_latest(true);
		$result = znote_update_check_start($latest);
	} elseif ($action === 'check_step') {
		$result = znote_update_check_step();
	} elseif ($action === 'install_start') {
		$latest = znote_update_latest(true);
		$result = znote_update_install_start($latest);
	} else {
		$result = znote_update_install_step();
	}

	if (in_array($action, array('check_start', 'check_step'), true) && ($result['phase'] ?? '') === 'done') {
		acp_log('update.check', (string)($result['manifest']['version'] ?? ''), array('passed' => $result['ok']));
	}
	if (($result['phase'] ?? '') === 'done' && $action === 'install_step') {
		acp_log('update.install', (string)$result['version'], array('backup' => $result['backup']));
	} elseif (empty($result['ok']) && $action === 'install_step') {
		acp_log('update.install_failed', '', array('error' => $result['error'] ?? ''));
	}

	echo json_encode($result);
	exit;
}

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
	@set_time_limit(0);
	@ini_set('max_execution_time', '0');

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

	<div id="acpCheckIdle" style="margin-top:18px;">
		<button class="acp-btn" type="button" id="acpCheckBtn" data-csrf="<?= h(acp_csrf()) ?>"><i class="fa fa-refresh"></i> Run full pre-installation check</button>
		<noscript>
			<form method="post" style="margin-top:12px;">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="action" value="check">
				<button class="acp-btn" type="submit"><i class="fa fa-refresh"></i> Run check without a progress bar (JavaScript is off)</button>
			</form>
		</noscript>
	</div>
	<div id="acpCheckProgress" hidden style="margin-top:18px;">
		<div class="acp-progress-bar"><div class="acp-progress-fill" id="acpCheckFill"></div></div>
		<p><span id="acpCheckPct">0%</span> - <span id="acpCheckPhase">Starting...</span></p>
		<div class="acp-progress-log" id="acpCheckLog"></div>
	</div>
<?php acp_card_close(); ?>

<div id="acpPreflightCard" <?= $preflight === null ? 'hidden' : '' ?>>
	<?php acp_card_open('Pre-installation check', $preflight !== null && $preflight['ok'] ? 'Every check passed. The update can be installed.' : 'Installation is blocked until every failed check is resolved.'); ?>
		<div id="acpPreflightCardBody">
			<?php if ($preflight !== null): ?>
				<div id="acpInstallIdle">
					<button class="acp-btn <?= $preflight['ok'] ? 'acp-btn--green' : 'acp-btn--ghost' ?>" type="button" id="acpInstallBtn" data-csrf="<?= h(acp_csrf()) ?>" <?= $preflight['ok'] ? '' : 'disabled' ?>><i class="fa fa-cloud-download"></i> Install version <?= h((string)($manifest['version'] ?? '')) ?></button>
					<noscript>
						<form method="post" style="margin-top:12px;">
							<?= acp_csrf_field() ?>
							<input type="hidden" name="action" value="install">
							<button class="acp-btn acp-btn--green" type="submit" <?= $preflight['ok'] ? '' : 'disabled' ?>><i class="fa fa-cloud-download"></i> Install without a progress bar (JavaScript is off)</button>
						</form>
					</noscript>
				</div>
				<div id="acpInstallProgress" hidden style="margin-top:18px;">
					<div class="acp-progress-bar"><div class="acp-progress-fill" id="acpInstallFill"></div></div>
					<p><span id="acpInstallPct">0%</span> - <span id="acpInstallPhase">Starting...</span></p>
					<div class="acp-progress-log" id="acpInstallLog"></div>
				</div>
				<table class="acp-table" style="margin-top:18px;">
					<thead><tr><th style="width:52px;">Status</th><th>Check</th><th>Result</th></tr></thead>
					<tbody>
					<?php foreach ($preflight['checks'] as $check): ?>
						<tr>
							<td style="font-size:20px;color:<?= !$check['ok'] ? 'var(--acp-red)' : (!empty($check['warn']) ? 'var(--acp-amber)' : 'var(--acp-green)') ?>;"><i class="fa <?= !$check['ok'] ? 'fa-times-circle' : (!empty($check['warn']) ? 'fa-exclamation-triangle' : 'fa-check-circle') ?>"></i></td>
							<td><strong><?= h($check['label']) ?></strong></td>
							<td>
								<?= h($check['detail']) ?>
								<?php if (!empty($check['items'])): ?>
									<div class="acp-scroll-list">
										<?php foreach ($check['items'] as $item): ?><div><?= h($item) ?></div><?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php acp_card_close(); ?>
</div>

<script>
(function () {
	var checkBtn = document.getElementById('acpCheckBtn');
	if (!checkBtn) return;

	var idle = document.getElementById('acpCheckIdle');
	var box = document.getElementById('acpCheckProgress');
	var fill = document.getElementById('acpCheckFill');
	var pct = document.getElementById('acpCheckPct');
	var phaseEl = document.getElementById('acpCheckPhase');
	var log = document.getElementById('acpCheckLog');
	var csrf = checkBtn.getAttribute('data-csrf');
	var preflightCard = document.getElementById('acpPreflightCard');
	var preflightBody = document.getElementById('acpPreflightCardBody');

	var checkPhaseLabels = { extract: 'Extracting package', localmods: 'Checking for local modifications', done: 'Done' };
	var installPhaseLabels = { backup: 'Backing up current files', migrate: 'Applying database migrations', copy: 'Installing new files', done: 'Done' };

	function addLog(target, text) {
		var line = document.createElement('div');
		line.textContent = text;
		target.appendChild(line);
		target.scrollTop = target.scrollHeight;
	}

	function post(action) {
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('csrf_token', csrf);
		return fetch(window.location.href, { method: 'POST', body: body }).then(function (r) { return r.json(); });
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = String(s == null ? '' : s);
		return d.innerHTML;
	}

	function renderPreflight(result) {
		var rows = result.checks.map(function (check) {
			var color = !check.ok ? 'var(--acp-red)' : (check.warn ? 'var(--acp-amber)' : 'var(--acp-green)');
			var icon = !check.ok ? 'fa-times-circle' : (check.warn ? 'fa-exclamation-triangle' : 'fa-check-circle');
			var items = (check.items && check.items.length)
				? '<div class="acp-scroll-list">' + check.items.map(function (item) { return '<div>' + escapeHtml(item) + '</div>'; }).join('') + '</div>'
				: '';
			return '<tr><td style="font-size:20px;color:' + color + ';"><i class="fa ' + icon + '"></i></td>'
				+ '<td><strong>' + escapeHtml(check.label) + '</strong></td>'
				+ '<td>' + escapeHtml(check.detail) + items + '</td></tr>';
		}).join('');

		var version = (result.manifest && result.manifest.version) ? result.manifest.version : '';

		preflightBody.innerHTML =
			'<div id="acpInstallIdle">'
			+ '<button class="acp-btn ' + (result.ok ? 'acp-btn--green' : 'acp-btn--ghost') + '" type="button" id="acpInstallBtn" data-csrf="' + escapeHtml(csrf) + '"' + (result.ok ? '' : ' disabled') + '><i class="fa fa-cloud-download"></i> Install version ' + escapeHtml(version) + '</button>'
			+ '</div>'
			+ '<div id="acpInstallProgress" hidden style="margin-top:18px;">'
			+ '<div class="acp-progress-bar"><div class="acp-progress-fill" id="acpInstallFill"></div></div>'
			+ '<p><span id="acpInstallPct">0%</span> - <span id="acpInstallPhase">Starting...</span></p>'
			+ '<div class="acp-progress-log" id="acpInstallLog"></div>'
			+ '</div>'
			+ '<table class="acp-table" style="margin-top:18px;"><thead><tr><th style="width:52px;">Status</th><th>Check</th><th>Result</th></tr></thead><tbody>' + rows + '</tbody></table>';

		preflightCard.hidden = false;
		bindInstall();
	}

	function bindInstall() {
		var btn = document.getElementById('acpInstallBtn');
		if (!btn) return;

		var iidle = document.getElementById('acpInstallIdle');
		var ibox = document.getElementById('acpInstallProgress');
		var ifill = document.getElementById('acpInstallFill');
		var ipct = document.getElementById('acpInstallPct');
		var iphase = document.getElementById('acpInstallPhase');
		var ilog = document.getElementById('acpInstallLog');

		function setProgress(cursor, total, phase) {
			var percent = total > 0 ? Math.round((cursor / total) * 100) : (phase === 'migrate' ? 50 : 0);
			ifill.style.width = percent + '%';
			ipct.textContent = percent + '%';
			iphase.textContent = installPhaseLabels[phase] || phase;
		}

		function step() {
			post('install_step').then(function (result) {
				if (!result.ok) {
					addLog(ilog, 'Failed: ' + result.error);
					iphase.textContent = 'Failed';
					return;
				}
				if (result.message) addLog(ilog, result.message);
				setProgress(result.cursor || 0, result.total || 0, result.phase);
				if (result.phase === 'done') {
					addLog(ilog, 'Reloading...');
					setTimeout(function () { window.location.reload(); }, 800);
					return;
				}
				step();
			}).catch(function (e) {
				addLog(ilog, 'Network error: ' + e.message);
			});
		}

		btn.addEventListener('click', function () {
			iidle.hidden = true;
			ibox.hidden = false;
			addLog(ilog, 'Starting install...');
			post('install_start').then(function (result) {
				if (!result.ok) {
					addLog(ilog, 'Failed: ' + result.error);
					iphase.textContent = 'Failed';
					return;
				}
				setProgress(0, result.total || 0, 'backup');
				step();
			}).catch(function (e) {
				addLog(ilog, 'Network error: ' + e.message);
			});
		});
	}

	function setCheckProgress(cursor, total, phase) {
		var percent = total > 0 ? Math.round((cursor / total) * 100) : 0;
		fill.style.width = percent + '%';
		pct.textContent = percent + '%';
		phaseEl.textContent = checkPhaseLabels[phase] || phase;
	}

	function step() {
		post('check_step').then(function (result) {
			if (!result.ok && result.phase !== 'done') {
				addLog(log, 'Failed: ' + result.error);
				phaseEl.textContent = 'Failed';
				return;
			}
			if (result.message) addLog(log, result.message);
			if (result.phase === 'done') {
				setCheckProgress(1, 1, 'done');
				renderPreflight(result);
				return;
			}
			setCheckProgress(result.cursor || 0, result.total || 0, result.phase);
			step();
		}).catch(function (e) {
			addLog(log, 'Network error: ' + e.message);
		});
	}

	checkBtn.addEventListener('click', function () {
		idle.hidden = true;
		box.hidden = false;
		log.innerHTML = '';
		addLog(log, 'Starting check...');
		post('check_start').then(function (result) {
			if (result.phase === 'done') {
				renderPreflight(result);
				idle.hidden = false;
				box.hidden = true;
				return;
			}
			setCheckProgress(0, result.total || 0, result.phase);
			step();
		}).catch(function (e) {
			addLog(log, 'Network error: ' + e.message);
		});
	});

	if (document.getElementById('acpInstallBtn')) {
		bindInstall();
	}
})();
</script>

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
