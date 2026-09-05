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
		acp_log('minimap.remove');
		acp_flash_success(t('acp.map.removed'));
		acp_redirect('minimap');
	}

	if ($do === 'upload') {
		$file = $_FILES['otmm'] ?? null;

		if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			acp_flash_error(t('acp.map.select_file'));
			acp_redirect('minimap');
		}

		if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			$reasons = [
				UPLOAD_ERR_INI_SIZE   => t('acp.map.err_ini_size'),
				UPLOAD_ERR_FORM_SIZE  => t('acp.map.err_form_size'),
				UPLOAD_ERR_PARTIAL    => t('acp.map.err_partial'),
				UPLOAD_ERR_NO_TMP_DIR => t('acp.map.err_no_tmp_dir'),
				UPLOAD_ERR_CANT_WRITE => t('acp.map.err_cant_write'),
				UPLOAD_ERR_EXTENSION  => t('acp.map.err_extension'),
			];
			acp_flash_error($reasons[(int)$file['error']] ?? t('acp.map.upload_failed'));
			acp_redirect('minimap');
		}

		if (!is_uploaded_file((string)$file['tmp_name'])) {
			acp_flash_error(t('acp.map.unverified'));
			acp_redirect('minimap');
		}

		$error = null;
		if (minimap_import((string)$file['tmp_name'], (string)$file['name'], $error)) {
			$fresh = json_decode((string)file_get_contents(minimap_meta_path()), true);
			acp_log('minimap.import', (string)$file['name'], [
				'tiles' => (int)($fresh['tiles'] ?? 0),
				'floors' => count((array)($fresh['floors'] ?? [])),
			]);
			acp_flash_success(t('acp.map.imported', [
				'tiles'  => number_format((int)($fresh['tiles'] ?? 0)),
				'floors' => number_format(count((array)($fresh['floors'] ?? []))),
			]));
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
			<span class="acp-pill acp-pill--green"><?= t('acp.map.active_pill') ?></span>
		<?php else: ?>
			<span class="acp-pill acp-pill--grey"><?= t('acp.map.none_pill') ?></span>
		<?php endif; ?>
	</div>
	<div class="acp-actions is-tight">
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site('serverinfo.php')) ?>" target="_blank">
			<i class="fa fa-external-link"></i> <?= t('acp.map.view_serverinfo') ?>
		</a>
	</div>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.map.import_title') ?></h2>
			<p><?= t('acp.map.import_sub', ['folder' => '<code>minimap</code>']) ?></p>
		</header>
		<div class="acp-card-body">

			<?php if (!$writable): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span><?= t('acp.map.not_writable', ['folder' => '<code>' . h(ZNOTE_MINIMAP_DIR) . '</code>']) ?></span>
				</div>
			<?php endif; ?>

			<?php if (!extension_loaded('gd') || !extension_loaded('zlib')): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span><?= t('acp.map.missing_ext', ['gd' => '<code>gd</code>', 'zlib' => '<code>zlib</code>']) ?></span>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="do" value="upload">

				<div class="acp-field">
					<label class="acp-label" for="otmm"><?= t('acp.map.file_label') ?></label>
					<input class="acp-input" type="file" id="otmm" name="otmm" accept=".otmm" required>
				</div>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit" <?= $writable ? '' : 'disabled' ?>>
						<i class="fa fa-upload"></i> <?= t('acp.map.import_btn') ?>
					</button>
					<?php if ($minimap !== false): ?>
						<button class="acp-btn acp-btn--red" type="submit" form="minimapDelete">
							<i class="fa fa-trash"></i> <?= t('acp.map.remove_btn') ?>
						</button>
					<?php endif; ?>
				</div>
			</form>

			<?php if ($minimap !== false): ?>
				<form method="post" id="minimapDelete" onsubmit="return confirm('<?= h(t('acp.map.confirm_remove')) ?>');">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="delete">
				</form>
			<?php endif; ?>

			<p class="acp-hint">
				<?= t('acp.map.upload_hint', [
					'folder'  => '<code>' . h(ZNOTE_MINIMAP_DIR) . '</code>',
					'max'     => (int)(ZNOTE_MINIMAP_MAX_BYTES / 1048576),
					'accepts' => h(serverdata_human_size($maxUpload)),
				]) ?>
			</p>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.map.status_title') ?></h2>
			<p><?= t('acp.map.status_sub') ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($minimap === false): ?>
				<div class="acp-card-body">
					<?php acp_empty(t('acp.map.none_body'), 'fa-map-o'); ?>
				</div>
			<?php else: ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<tbody>
							<tr>
								<td><?= t('acp.map.col_source') ?></td>
								<td><code><?= h((string)($minimap['source'] ?? 'minimap.otmm')) ?></code></td>
							</tr>
							<tr>
								<td><?= t('acp.map.col_imported') ?></td>
								<td><?= h(date('Y-m-d H:i', (int)($minimap['date'] ?? 0))) ?></td>
							</tr>
							<tr>
								<td><?= t('acp.map.col_floors') ?></td>
								<td><?= h(implode(', ', array_map('intval', array_keys((array)$minimap['floors'])))) ?></td>
							</tr>
							<tr>
								<td><?= t('acp.map.col_tiles') ?></td>
								<td><?= number_format((int)($minimap['tiles'] ?? 0)) ?></td>
							</tr>
							<tr>
								<td><?= t('acp.map.col_disk') ?></td>
								<td><?= h(serverdata_human_size($diskBytes)) ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</section>

</div>
