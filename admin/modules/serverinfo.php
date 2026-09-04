<?php
/**
 * Title: Server Information
 * Icon: fa-server
 * Group: Server Info
 * Order: 10
 * Description: Upload config.lua, stages.xml, items.xml, spells.xml and your monster folder.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$sources = serverdata_sources();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$do  = (string)($_POST['do'] ?? '');
	$key = (string)($_POST['src'] ?? '');

	if (!isset($sources[$key])) {
		acp_flash_error('Unknown data source.');
		acp_redirect('serverinfo');
	}

	$label = $sources[$key]['label'];

	if ($do === 'clear') {
		serverdata_clear($key);
		acp_flash_success($label . ' removed. ' . $sources[$key]['page'] . ' no longer has data to show.');
		acp_redirect('serverinfo');
	}

	if ($do === 'rebuild') {
		$error = null;
		if (serverdata_rebuild($key, $error)) {
			$count = serverdata_count($key, serverdata_load($key));
			acp_flash_success($label . ' rebuilt: ' . number_format($count) . ' entries cached.');
			if ($error !== null) {
				acp_flash_info($error);
			}
		} else {
			acp_flash_error($label . ': ' . (string)$error);
		}
		acp_redirect('serverinfo');
	}

	if ($do === 'upload') {
		$file = $_FILES['payload'] ?? null;

		if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			acp_flash_error('Select a file first.');
			acp_redirect('serverinfo');
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
			acp_redirect('serverinfo');
		}

		if (!is_uploaded_file((string)$file['tmp_name'])) {
			acp_flash_error('The upload could not be verified.');
			acp_redirect('serverinfo');
		}

		$error = null;
		if (serverdata_publish_upload($key, (string)$file['tmp_name'], (string)$file['name'], $error)) {
			$count = serverdata_count($key, serverdata_load($key));
			acp_flash_success($label . ' uploaded: ' . number_format($count) . ' entries now live on ' . $sources[$key]['page'] . '.');
			if ($error !== null) {
				acp_flash_info($error);
			}
		} else {
			acp_flash_error($label . ': ' . (string)$error);
		}

		acp_redirect('serverinfo');
	}
}

$status    = serverdata_status();
$itemsPage = !empty($config['items']);
$maxUpload = min(
	(int)serverdata_ini_bytes((string)ini_get('upload_max_filesize')),
	(int)serverdata_ini_bytes((string)ini_get('post_max_size'))
);
?>

<div class="acp-toolbar">
	<div>
		<?php
		$ready = 0;
		foreach ($status as $row) {
			if ($row['cached']) {
				$ready++;
			}
		}
		?>
		<span class="acp-pill acp-pill--<?= $ready === count($status) ? 'green' : 'grey' ?>">
			<?= (int)$ready ?> of <?= count($status) ?> sources published
		</span>
	</div>
	<div class="acp-actions is-tight">
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site('serverinfo.php')) ?>" target="_blank">
			<i class="fa fa-external-link"></i> Server Information
		</a>
	</div>
</div>

<?php if (!$itemsPage): ?>
	<div class="acp-flash acp-flash--info">
		<i class="fa fa-info-circle"></i>
		<span>
			The public items page is turned off. Enable <code>items</code> under
			<a href="<?= h(acp_url('settings')) ?>">Settings</a> for your items.xml upload to be visible.
		</span>
	</div>
<?php endif; ?>

<div class="acp-grid acp-grid--2">
	<?php foreach ($status as $key => $row): ?>
		<section class="acp-card">
			<header class="acp-card-head">
				<h2><?= h($row['label']) ?></h2>
				<p><?= h($row['help']) ?></p>
			</header>
			<div class="acp-card-body">

				<p>
					<?php if ($row['cached']): ?>
						<span class="acp-pill acp-pill--green"><?= number_format((int)$row['count']) ?> published</span>
					<?php else: ?>
						<span class="acp-pill acp-pill--grey">Nothing published</span>
					<?php endif; ?>
					<?php if ($row['upload_size']): ?>
						<span class="acp-pill acp-pill--blue"><?= h(serverdata_human_size((int)$row['upload_size'])) ?></span>
					<?php endif; ?>
					<?php if ($key === 'creatures' && (int)$row['files'] > 1): ?>
						<span class="acp-pill acp-pill--blue"><?= number_format((int)$row['files']) ?> XML files</span>
					<?php endif; ?>
				</p>

				<table class="acp-table">
					<tbody>
						<tr>
							<td>Source</td>
							<td><code><?= h($row['path_label']) ?></code></td>
						</tr>
						<?php if ($row['keeps_file']): ?>
							<tr>
								<td>Uploaded</td>
								<td><?= $row['upload_date'] ? h(date('Y-m-d H:i', (int)$row['upload_date'])) : '&mdash;' ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td>Published</td>
							<td><?= $row['cache_date'] ? h(date('Y-m-d H:i', (int)$row['cache_date'])) : '&mdash;' ?></td>
						</tr>
						<tr>
							<td>Public page</td>
							<td><a href="<?= h(acp_site($row['page'])) ?>" target="_blank"><?= h($row['page']) ?></a></td>
						</tr>
					</tbody>
				</table>

				<form method="post" enctype="multipart/form-data">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="upload">
					<input type="hidden" name="src" value="<?= h($key) ?>">

					<div class="acp-field">
						<label class="acp-label" for="src_<?= h($key) ?>">Upload <?= h($row['accept']) ?></label>
						<input class="acp-input" type="file" id="src_<?= h($key) ?>" name="payload" accept="<?= h($row['accept']) ?>" required>
					</div>

					<div class="acp-actions">
						<button class="acp-btn acp-btn--green" type="submit">
							<i class="fa fa-upload"></i> Upload and publish
						</button>
						<?php if ($row['uploaded'] && $row['keeps_file']): ?>
							<button class="acp-btn acp-btn--ghost" type="submit" form="rebuild_<?= h($key) ?>">
								<i class="fa fa-refresh"></i> Re-read
							</button>
						<?php endif; ?>
						<?php if ($row['uploaded'] || $row['cached']): ?>
							<button class="acp-btn acp-btn--red" type="submit" form="clear_<?= h($key) ?>">
								<i class="fa fa-trash"></i> Remove
							</button>
						<?php endif; ?>
					</div>
				</form>

				<?php if ($row['uploaded'] && $row['keeps_file']): ?>
					<form method="post" id="rebuild_<?= h($key) ?>">
						<?= acp_csrf_field() ?>
						<input type="hidden" name="do" value="rebuild">
						<input type="hidden" name="src" value="<?= h($key) ?>">
					</form>
				<?php endif; ?>
				<?php if ($row['uploaded'] || $row['cached']): ?>
					<form method="post" id="clear_<?= h($key) ?>" onsubmit="return confirm('Remove <?= h($row['label']) ?>? <?= h($row['page']) ?> will stop showing it.');">
						<?= acp_csrf_field() ?>
						<input type="hidden" name="do" value="clear">
						<input type="hidden" name="src" value="<?= h($key) ?>">
					</form>
				<?php endif; ?>

			</div>
		</section>
	<?php endforeach; ?>
</div>

<p class="acp-hint">
	Uploads are stored under <code><?= h(ZNOTE_SERVERDATA_DIR) ?>/</code> and parsed straight away, so the
	public pages only ever read the cache. PHP currently accepts <?= h(serverdata_human_size($maxUpload)) ?> per upload.
</p>
