<?php
/**
 * Title: Landing Page
 * Icon: fa-flag
 * Group: Content
 * Order: 8
 * Description: Show a standalone page from landing/ instead of the front page.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$enabled = empty($_POST['enabled']) ? '' : '1';
	$file    = trim((string)($_POST['file'] ?? ''));

	if ($file !== '' && !landing_is_valid_file($file)) {
		acp_flash_error(t('acp.land.bad_file'));
		acp_redirect('landing');
	}

	if ($enabled === '1') {
		if (!is_dir(landing_root())) {
			acp_flash_error(t('acp.land.no_folder', ['folder' => '<code>landing/</code>']));
			acp_redirect('landing');
		}
		if ($file === '' || !is_file(landing_root() . '/' . $file)) {
			acp_flash_error(t('acp.land.pick_page', ['folder' => '<code>landing/</code>']));
			acp_redirect('landing');
		}
	}

	setting_set('landing.enabled', $enabled);
	setting_set('landing.file', $file);

	acp_log('landing.toggle', $file, ['enabled' => $enabled === '1']);

	acp_flash_success($enabled === '1'
		? t('acp.land.live', ['path' => '<code>landing/' . h($file) . '</code>'])
		: t('acp.land.off'));

	acp_redirect('landing');
}

$files     = landing_available_files();
$hasFolder = is_dir(landing_root());
$enabled   = landing_enabled();
$current   = landing_file();
$live      = landing_ready();
?>

<div class="acp-toolbar">
	<div>
		<?php if ($live): ?>
			<span class="acp-pill acp-pill--green"><?= t('acp.land.live_pill') ?></span>
		<?php elseif ($enabled): ?>
			<span class="acp-pill acp-pill--red"><?= t('acp.land.missing_pill') ?></span>
		<?php else: ?>
			<span class="acp-pill acp-pill--grey"><?= t('acp.land.off_pill') ?></span>
		<?php endif; ?>
	</div>
	<div class="acp-actions is-tight">
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site()) ?>" target="_blank" rel="noopener">
			<i class="fa fa-external-link"></i> <?= t('acp.land.open_site') ?>
		</a>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site('index.php?site=1')) ?>" target="_blank" rel="noopener">
			<i class="fa fa-sign-in"></i> <?= t('acp.land.skip') ?>
		</a>
	</div>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.land.title') ?></h2>
			<p><?= t('acp.land.sub') ?></p>
		</header>
		<div class="acp-card-body">

			<?php if (!$hasFolder): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span><?= t('acp.land.no_folder_flash', [
						'folder'   => '<code>landing/</code>',
						'index'    => '<code>index.html</code>',
						'indexphp' => '<code>index.php</code>',
					]) ?></span>
				</div>
			<?php elseif (!$files): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span><?= t('acp.land.no_pages_flash', [
						'folder' => '<code>landing/</code>',
						'html'   => '<code>.html</code>',
						'php'    => '<code>.php</code>',
					]) ?></span>
				</div>
			<?php endif; ?>

			<form method="post">
				<?= acp_csrf_field() ?>

				<div class="acp-field">
					<label class="acp-label" for="file"><?= t('acp.land.page_to_show') ?></label>
					<select class="acp-select" id="file" name="file" <?= $files ? '' : 'disabled' ?>>
						<?php if (!$files): ?>
							<option value=""><?= t('acp.land.nothing_yet') ?></option>
						<?php endif; ?>
						<?php foreach ($files as $name): ?>
							<option value="<?= h($name) ?>" <?= $name === $current ? 'selected' : '' ?>>
								<?= h('landing/' . $name) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="acp-field">
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
						<input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
						<span><?= t('acp.land.show_at_root') ?></span>
					</label>
				</div>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit">
						<i class="fa fa-check"></i> <?= t('acp.land.save') ?>
					</button>
				</div>
			</form>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.land.behaves_title') ?></h2>
			<p><?= t('acp.land.behaves_sub') ?></p>
		</header>
		<div class="acp-card-body">
			<table class="acp-table">
				<tbody>
					<tr>
						<td><?= t('acp.land.col_folder') ?></td>
						<td><code><?= h(ZNOTE_LANDING_DIR) ?>/</code> <?= $hasFolder ? '' : '<em>' . t('acp.land.missing_suffix') . '</em>' ?></td>
					</tr>
					<tr>
						<td><?= t('acp.land.col_pages') ?></td>
						<td><?= $files ? h(implode(', ', $files)) : '&mdash;' ?></td>
					</tr>
					<tr>
						<td><?= t('acp.land.col_way_in') ?></td>
						<td><code>index.php?site=1</code></td>
					</tr>
				</tbody>
			</table>

			<p class="acp-hint">
				<?= t('acp.land.hint1') ?>
			</p>
			<p class="acp-hint">
				<?= t('acp.land.hint2', [
					'base'   => '<code>&lt;base&gt;</code>',
					'folder' => '<code>' . h(ZNOTE_LANDING_DIR) . '/</code>',
					'link'   => '<code>&lt;a href="/index.php?site=1"&gt;Enter&lt;/a&gt;</code>',
				]) ?>
			</p>
		</div>
	</section>

</div>
