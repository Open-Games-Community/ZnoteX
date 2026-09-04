<?php
/**
 * Title: Minimap
 * Icon: fa-map-o
 * Group: Server Info
 * Order: 20
 * Description: Import an OTClient .otmm minimap and show it on Server Information.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$do = (string)($_POST['do'] ?? '');

	if ($do === 'delete') {
		minimap_delete();
		acp_flash_success('Minimap removed. Server Information no longer shows a map.');
		acp_redirect('minimap');
	}

	if ($do === 'upload') {
		$file = $_FILES['otmm'] ?? null;

		if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			acp_flash_error('Select a .otmm file first.');
			acp_redirect('minimap');
		}

		if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			$reasons = [
				UPLOAD_ERR_INI_SIZE   => 'The file is larger than upload_max_filesize in php.ini.',
				UPLOAD_ERR_FORM_SIZE  => 'The file is larger than the form allows.',
				UPLOAD_ERR_PARTIAL    => 'The upload was interrupted.',
				UPLOAD_ERR_NO_TMP_DIR => 'PHP has no temporary folder to write to.',
				UPLOAD_ERR_CANT_WRITE => 'PHP could not write the upload to disk.',
				UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
			];
			acp_flash_error($reasons[(int)$file['error']] ?? 'The upload failed.');
			acp_redirect('minimap');
		}

		if (!is_uploaded_file((string)$file['tmp_name'])) {
			acp_flash_error('The upload could not be verified.');
			acp_redirect('minimap');
		}

		$error = null;
		if (minimap_import((string)$file['tmp_name'], (string)$file['name'], $error)) {
			$fresh = json_decode((string)file_get_contents(minimap_meta_path()), true);
			acp_flash_success(sprintf(
				'Minimap imported: %s tiles across %s floors. It is now visible on Server Information.',
				number_format((int)($fresh['tiles'] ?? 0)),
				number_format(count((array)($fresh['floors'] ?? [])))
			));
		} else {
			acp_flash_error((string)$error);
		}

		acp_redirect('minimap');
	}
}

$minimap = minimap_data();
$root    = minimap_root();

$diskBytes = 0;
foreach (glob($root . '/*.png') ?: [] as $tile) {
	$diskBytes += (int)filesize($tile);
}

$writable  = is_dir($root) ? is_writable($root) : is_writable(dirname($root));
$maxUpload = min(
	(int)serverdata_ini_bytes((string)ini_get('upload_max_filesize')),
	(int)serverdata_ini_bytes((string)ini_get('post_max_size'))
);
?>

<div class="acp-toolbar">
	<div>
		<?php if ($minimap !== false): ?>
			<span class="acp-pill acp-pill--green">Minimap active</span>
		<?php else: ?>
			<span class="acp-pill acp-pill--grey">No minimap imported</span>
		<?php endif; ?>
	</div>
	<div class="acp-actions is-tight">
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site('serverinfo.php')) ?>" target="_blank">
			<i class="fa fa-external-link"></i> View Server Information
		</a>
	</div>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Import a minimap</h2>
			<p>The .otmm file OTClient / OTCv8 writes into its <code>minimap</code> folder.</p>
		</header>
		<div class="acp-card-body">

			<?php if (!$writable): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span><code><?= h(ZNOTE_MINIMAP_DIR) ?>/</code> is not writable by the web server. Fix the permissions before importing.</span>
				</div>
			<?php endif; ?>

			<?php if (!extension_loaded('gd') || !extension_loaded('zlib')): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span>PHP needs both the <code>gd</code> and <code>zlib</code> extensions to convert an .otmm file.</span>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="do" value="upload">

				<div class="acp-field">
					<label class="acp-label" for="otmm">Minimap file</label>
					<input class="acp-input" type="file" id="otmm" name="otmm" accept=".otmm" required>
				</div>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit" <?= $writable ? '' : 'disabled' ?>>
						<i class="fa fa-upload"></i> Import minimap
					</button>
					<?php if ($minimap !== false): ?>
						<button class="acp-btn acp-btn--red" type="submit" form="minimapDelete">
							<i class="fa fa-trash"></i> Remove minimap
						</button>
					<?php endif; ?>
				</div>
			</form>

			<?php if ($minimap !== false): ?>
				<form method="post" id="minimapDelete" onsubmit="return confirm('Remove the imported minimap? Server Information will stop showing a map.');">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="delete">
				</form>
			<?php endif; ?>

			<p class="acp-hint">
				Uploading replaces whatever is stored now. The .otmm itself is not kept: it is converted
				into 64&times;64 PNG tiles under <code><?= h(ZNOTE_MINIMAP_DIR) ?>/</code>.
				Maximum <?= (int)(ZNOTE_MINIMAP_MAX_BYTES / 1048576) ?> MB, and PHP currently accepts
				<?= h(serverdata_human_size($maxUpload)) ?> per upload.
			</p>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Status</h2>
			<p>Server Information shows the map only while a minimap is imported.</p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($minimap === false): ?>
				<div class="acp-card-body">
					<?php acp_empty('No minimap imported. serverinfo.php shows no map section at all.', 'fa-map-o'); ?>
				</div>
			<?php else: ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<tbody>
							<tr>
								<td>Source file</td>
								<td><code><?= h((string)($minimap['source'] ?? 'minimap.otmm')) ?></code></td>
							</tr>
							<tr>
								<td>Imported</td>
								<td><?= h(date('Y-m-d H:i', (int)($minimap['date'] ?? 0))) ?></td>
							</tr>
							<tr>
								<td>Floors</td>
								<td><?= h(implode(', ', array_map('intval', array_keys((array)$minimap['floors'])))) ?></td>
							</tr>
							<tr>
								<td>Tiles</td>
								<td><?= number_format((int)($minimap['tiles'] ?? 0)) ?></td>
							</tr>
							<tr>
								<td>Disk usage</td>
								<td><?= h(serverdata_human_size($diskBytes)) ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</section>

</div>
