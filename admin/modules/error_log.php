<?php
/**
 * Title: Error Log
 * Icon: fa-bug
 * Group: Overview
 * Order: 40
 * Description: Tail the PHP error log - website, plugin and theme errors all land here.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_error_log_ini_path(): string {
	$v = @ini_get('error_log');
	return is_string($v) ? trim($v) : '';
}

function acp_error_log_path(): string {
	$configured = trim((string) setting('error_log:path', ''));
	return $configured !== '' ? $configured : acp_error_log_ini_path();
}

/** Last $lines lines of a (possibly large) text file, without loading it all into memory. */
function acp_error_log_tail(string $file, int $lines): array {
	$handle = @fopen($file, 'rb');
	if ($handle === false) {
		return array();
	}

	$chunkSize = 8192;
	$buffer    = '';
	$foundLines = 0;
	fseek($handle, 0, SEEK_END);
	$pos = ftell($handle);

	while ($pos > 0 && $foundLines <= $lines) {
		$read = min($chunkSize, $pos);
		$pos -= $read;
		fseek($handle, $pos);
		$buffer = fread($handle, $read) . $buffer;
		$foundLines = substr_count($buffer, "\n");
	}
	fclose($handle);

	$rows = explode("\n", rtrim($buffer, "\n"));
	$rows = array_slice($rows, -$lines);
	return array_reverse($rows);
}

function acp_error_log_severity(string $line): string {
	if (preg_match('/\b(Fatal error|SQL ERROR|Uncaught|Parse error|ZnoteX DB)\b/i', $line)) {
		return 'red';
	}
	if (preg_match('/\b(Warning|Deprecated)\b/i', $line)) {
		return 'amber';
	}
	return '';
}

$module = 'error_log';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$do = (string) ($_POST['do'] ?? '');
	if ($do === 'save_path') {
		$newPath = trim((string) ($_POST['path'] ?? ''));
		if (setting_set('error_log:path', $newPath)) {
			acp_log('error_log.path_save', $newPath);
			acp_flash_success('Log path saved.');
		} else {
			acp_flash_error('The log path could not be saved.');
		}
	} elseif ($do === 'reset_path') {
		if (setting_set('error_log:path', '')) {
			acp_log('error_log.path_reset');
			acp_flash_success('Reverted to the auto-detected path.');
		} else {
			acp_flash_error('The log path could not be reset.');
		}
	}
	acp_redirect($module);
}

$path       = acp_error_log_path();
$hasFile    = $path !== '' && is_file($path) && is_readable($path);
$linesWant  = in_array((string) ($_GET['lines'] ?? ''), array('100', '300', '1000'), true) ? (int) $_GET['lines'] : 300;
$rows       = $hasFile ? acp_error_log_tail($path, $linesWant) : array();
$configured = trim((string) setting('error_log:path', ''));
?>

<?php if (!$hasFile): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			<?php if ($path === ''): ?>
				PHP is not configured with an <code>error_log</code> file for this request - it is very likely logging to the web server's own error log instead (e.g. Apache's log under XAMPP, WAMP, Uniform Server, or a Linux host), which lives outside this site's folder and cannot be found automatically.
			<?php else: ?>
				The configured path <code><?= h($path) ?></code> does not exist or is not readable from PHP.
			<?php endif; ?>
			Paste the correct path below - check your stack's control panel (XAMPP: <code>apache/logs/error.log</code>, WAMP: <code>logs/apache_error.log</code>, Uniform Server: its <code>logs</code> folder) or your <code>php.ini</code>'s <code>error_log</code> directive.
		</span>
	</div>
<?php endif; ?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Log file</h2>
		<p>PHP's <code>ini_get('error_log')</code> for this request: <code><?= h(acp_error_log_ini_path() !== '' ? acp_error_log_ini_path() : '(empty - using the SAPI default)') ?></code></p>
	</header>
	<div class="acp-card-body">
		<form method="post" class="acp-row">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="do" value="save_path">
			<div class="acp-field" style="flex:2;min-width:280px;">
				<label class="acp-label" for="path">Path to the error log</label>
				<input class="acp-input" id="path" name="path" value="<?= h($configured) ?>" placeholder="<?= h(acp_error_log_ini_path()) ?>">
			</div>
			<div class="acp-actions">
				<button class="acp-btn" type="submit"><i class="fa fa-check"></i> Save</button>
				<?php if ($configured !== ''): ?>
					<form method="post" style="display:inline">
						<?= acp_csrf_field() ?>
						<input type="hidden" name="do" value="reset_path">
						<button class="acp-btn acp-btn--ghost" type="submit">Use auto-detected path</button>
					</form>
				<?php endif; ?>
			</div>
		</form>
	</div>
</section>

<?php if ($hasFile): ?>
	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Latest entries</h2>
			<p>
				<code><?= h($path) ?></code>
				&middot; <?= h(number_format(@filesize($path) ?: 0)) ?> bytes
				&middot; last modified <?= h(date('Y-m-d H:i:s', @filemtime($path) ?: time())) ?>
			</p>
		</header>
		<div class="acp-card-body">
			<div class="acp-row" style="margin-bottom:12px">
				<?php foreach (array('100', '300', '1000') as $n): ?>
					<a class="acp-btn <?= $linesWant === (int) $n ? '' : 'acp-btn--ghost' ?> acp-btn--sm" href="<?= h(acp_url($module, array('lines' => $n))) ?>">Last <?= $n ?></a>
				<?php endforeach; ?>
				<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url($module, array('lines' => (string) $linesWant))) ?>"><i class="fa fa-refresh"></i> Refresh</a>
			</div>
			<?php if (!$rows): ?>
				<?php acp_empty('The log file is empty.', 'fa-file-text-o'); ?>
			<?php else: ?>
				<div class="acp-table-wrap" style="max-height:70vh;overflow:auto">
					<table class="acp-table">
						<tbody>
							<?php foreach ($rows as $line): $sev = acp_error_log_severity($line); ?>
								<tr>
									<td style="font-family:monospace;font-size:12.5px;white-space:pre-wrap;overflow-wrap:anywhere<?= $sev === 'red' ? ';color:#c0392b' : ($sev === 'amber' ? ';color:#b8860b' : '') ?>"><?= h($line) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>
