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
		acp_flash_error(t('acp.srv.unknown_source'));
		acp_redirect('serverinfo');
	}

	$label = $sources[$key]['label'];

	if ($do === 'clear') {
		serverdata_clear($key);
		acp_flash_success(t('acp.srv.removed', ['label' => $label, 'page' => $sources[$key]['page']]));
		acp_redirect('serverinfo');
	}

	if ($do === 'rebuild') {
		$error = null;
		if (serverdata_rebuild($key, $error)) {
			$count = serverdata_count($key, serverdata_load($key));
			acp_flash_success(t('acp.srv.rebuilt', ['label' => $label, 'count' => number_format($count)]));
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
			acp_flash_error(t('acp.srv.select_file'));
			acp_redirect('serverinfo');
		}

		if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			$reasons = [
				UPLOAD_ERR_INI_SIZE   => t('acp.srv.err_ini_size'),
				UPLOAD_ERR_FORM_SIZE  => t('acp.srv.err_form_size'),
				UPLOAD_ERR_PARTIAL    => t('acp.srv.err_partial'),
				UPLOAD_ERR_NO_TMP_DIR => t('acp.srv.err_no_tmp_dir'),
				UPLOAD_ERR_CANT_WRITE => t('acp.srv.err_cant_write'),
				UPLOAD_ERR_EXTENSION  => t('acp.srv.err_extension'),
			];
			acp_flash_error($reasons[(int)$file['error']] ?? t('acp.srv.upload_failed'));
			acp_redirect('serverinfo');
		}

		if (!is_uploaded_file((string)$file['tmp_name'])) {
			acp_flash_error(t('acp.srv.upload_unverified'));
			acp_redirect('serverinfo');
		}

		$error = null;
		if (serverdata_publish_upload($key, (string)$file['tmp_name'], (string)$file['name'], $error)) {
			$count = serverdata_count($key, serverdata_load($key));
			acp_flash_success(t('acp.srv.uploaded', [
				'label' => $label,
				'count' => number_format($count),
				'page'  => $sources[$key]['page'],
			]));
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
			<?= t('acp.srv.sources_published', ['ready' => (int)$ready, 'total' => count($status)]) ?>
		</span>
	</div>
	<div class="acp-actions is-tight">
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site('serverinfo.php')) ?>" target="_blank">
			<i class="fa fa-external-link"></i> <?= t('acp.srv.public_page') ?>
		</a>
	</div>
</div>

<?php if (!$itemsPage): ?>
	<div class="acp-flash acp-flash--info">
		<i class="fa fa-info-circle"></i>
		<span>
			<?= t('acp.srv.items_off', [
				'items'    => '<code>items</code>',
				'settings' => '<a href="' . h(acp_url('settings')) . '">' . t('acp.srv.settings_link') . '</a>',
			]) ?>
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
						<span class="acp-pill acp-pill--green"><?= t('acp.srv.published', ['count' => number_format((int)$row['count'])]) ?></span>
					<?php else: ?>
						<span class="acp-pill acp-pill--grey"><?= t('acp.srv.nothing_published') ?></span>
					<?php endif; ?>
					<?php if ($row['upload_size']): ?>
						<span class="acp-pill acp-pill--blue"><?= h(serverdata_human_size((int)$row['upload_size'])) ?></span>
					<?php endif; ?>
					<?php if ($key === 'creatures' && (int)$row['files'] > 1): ?>
						<span class="acp-pill acp-pill--blue"><?= t('acp.srv.xml_files', ['count' => number_format((int)$row['files'])]) ?></span>
					<?php endif; ?>
				</p>

				<table class="acp-table">
					<tbody>
						<tr>
							<td><?= t('acp.srv.col_source') ?></td>
							<td><code><?= h($row['path_label']) ?></code></td>
						</tr>
						<?php if ($row['keeps_file']): ?>
							<tr>
								<td><?= t('acp.srv.col_uploaded') ?></td>
								<td><?= $row['upload_date'] ? h(date('Y-m-d H:i', (int)$row['upload_date'])) : '&mdash;' ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td><?= t('acp.srv.col_published') ?></td>
							<td><?= $row['cache_date'] ? h(date('Y-m-d H:i', (int)$row['cache_date'])) : '&mdash;' ?></td>
						</tr>
						<tr>
							<td><?= t('acp.srv.col_public_page') ?></td>
							<td><a href="<?= h(acp_site($row['page'])) ?>" target="_blank"><?= h($row['page']) ?></a></td>
						</tr>
					</tbody>
				</table>

				<form method="post" enctype="multipart/form-data">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="upload">
					<input type="hidden" name="src" value="<?= h($key) ?>">

					<div class="acp-field">
						<label class="acp-label" for="src_<?= h($key) ?>"><?= t('acp.srv.upload_label', ['accept' => h($row['accept'])]) ?></label>
						<input class="acp-input" type="file" id="src_<?= h($key) ?>" name="payload" accept="<?= h($row['accept']) ?>" required>
					</div>

					<div class="acp-actions">
						<button class="acp-btn acp-btn--green" type="submit">
							<i class="fa fa-upload"></i> <?= t('acp.srv.upload_publish') ?>
						</button>
						<?php if ($row['uploaded'] && $row['keeps_file']): ?>
							<button class="acp-btn acp-btn--ghost" type="submit" form="rebuild_<?= h($key) ?>">
								<i class="fa fa-refresh"></i> <?= t('acp.srv.reread') ?>
							</button>
						<?php endif; ?>
						<?php if ($row['uploaded'] || $row['cached']): ?>
							<button class="acp-btn acp-btn--red" type="submit" form="clear_<?= h($key) ?>">
								<i class="fa fa-trash"></i> <?= t('acp.srv.remove') ?>
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
					<form method="post" id="clear_<?= h($key) ?>" onsubmit="return confirm('<?= h(t('acp.srv.confirm_remove', ['label' => $row['label'], 'page' => $row['page']])) ?>');">
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
	<?= t('acp.srv.storage_hint', [
		'path' => '<code>' . h(ZNOTE_SERVERDATA_DIR) . '</code>',
		'size' => h(serverdata_human_size($maxUpload)),
	]) ?>
</p>
