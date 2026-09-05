<?php
/**
 * Title: Plugins
 * Icon: fa-plug
 * Group: Settings
 * Order: 20
 * Description: Install, update, enable and disable what is in plugins/.
 */

/*
 * How a plugin gets here: you download it and drop the folder into plugins/.
 * ZnoteX does not fetch anything by itself - installing a plugin means running
 * someone else's PHP on every page of your site, so putting the file there
 * stays a deliberate act, not a button.
 *
 * From that point this page does the rest:
 *
 *   Install   runs install.sql and records the version   (folder -> working)
 *   Update    shown when the folder is newer than what was installed
 *   Enable    the plugin starts running
 *   Disable   it stops, tables untouched
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name   = znote_plugin_sanitize((string)($_POST['plugin'] ?? ''));
	$action = (string)($_POST['action'] ?? '');
	$known  = znote_plugins(true);

	if ($name === '' || !isset($known[$name])) {
		acp_flash_error(t('acp.plg.no_such'));
		acp_redirect('plugins');
	}

	$plugin = $known[$name];
	$label  = $plugin['name'];

	switch ($action) {

		case 'install':
			$error = znote_plugin_install($name);

			if ($error !== '') {
				acp_flash_error(t('acp.plg.install_failed', ['plugin' => $label, 'error' => $error]));
				break;
			}

			// Installing implies wanting it on. Nobody installs a plugin in
			// order to leave it switched off.
			znote_plugin_set_enabled($name, true);
			acp_log('plugin.install', $name, ['version' => $plugin['version']]);
			acp_flash_success(t('acp.plg.installed', ['plugin' => $label, 'version' => $plugin['version']]));
			break;

		case 'update':
			$from  = $plugin['installed_version'];
			$error = znote_plugin_install($name);

			if ($error !== '') {
				acp_flash_error(t('acp.plg.update_failed', ['plugin' => $label, 'error' => $error]));
			} else {
				acp_log('plugin.update', $name, ['from' => $from, 'to' => $plugin['version']]);
				acp_flash_success(t('acp.plg.updated', ['plugin' => $label, 'from' => $from, 'to' => $plugin['version']]));
			}
			break;

		case 'enable':
			znote_plugin_set_enabled($name, true);
			acp_log('plugin.enable', $name);
			acp_flash_success(t('acp.plg.enabled', ['plugin' => $label]));
			break;

		case 'disable':
			znote_plugin_set_enabled($name, false);
			acp_log('plugin.disable', $name);
			acp_flash_info(t('acp.plg.disabled', ['plugin' => $label]));
			break;

		case 'uninstall':
			// Forgets the install, keeps the data. Dropping a player's coupons
			// because someone clicked a button would be the wrong default.
			znote_plugin_uninstall($name);
			acp_log('plugin.uninstall', $name);
			acp_flash_info(t('acp.plg.uninstalled', ['plugin' => $label]));
			break;

		default:
			acp_flash_error(t('acp.plg.unknown_action'));
	}

	acp_redirect('plugins');
}

$plugins   = znote_plugins(true);
$active    = 0;
$updatable = 0;

foreach ($plugins as $plugin) {
	if ($plugin['enabled'] && $plugin['installed']) {
		$active++;
	}
	if ($plugin['update']) {
		$updatable++;
	}
}
?>

<div class="acp-grid">
	<?php
	acp_stat(t('acp.plg.stat_in_folder'), count($plugins), 'fa-folder-o', null, 'blue');
	acp_stat(t('acp.plg.stat_running'), $active, 'fa-check', null, 'green');
	if ($updatable > 0) {
		acp_stat(t('acp.plg.stat_updates'), $updatable, 'fa-arrow-up', null, 'amber');
	}
	?>
</div>

<?php acp_card_open(t('acp.plg.title'), t('acp.plg.sub')); ?>

	<?php if (!$plugins): ?>

		<?php acp_empty(t('acp.plg.empty'), 'fa-plug'); ?>

	<?php else: ?>

		<table class="acp-table">
			<thead>
				<tr>
					<th><?= t('acp.plg.col_plugin') ?></th>
					<th><?= t('acp.plg.col_adds') ?></th>
					<th><?= t('acp.plg.col_version') ?></th>
					<th><?= t('acp.plg.col_status') ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($plugins as $name => $plugin):

				$adds = array();
				if ($plugin['pages']) { $adds[] = t('acp.plg.page_count', ['n' => $plugin['pages']]); }
				if ($plugin['admin']) { $adds[] = t('acp.plg.admin_page_count', ['n' => $plugin['admin']]); }
				if ($plugin['sql'])   { $adds[] = t('acp.plg.tables'); }

				$running = $plugin['installed'] && $plugin['enabled'];
			?>
				<tr>
					<td>
						<strong><?= h($plugin['name']) ?></strong>
						<?php if ($plugin['description'] !== ''): ?>
							<span class="acp-hint" style="display:block;"><?= h($plugin['description']) ?></span>
						<?php endif; ?>
						<span class="acp-hint" style="display:block;">
							<code><?= h($name) ?></code>
							<?php if ($plugin['author'] !== ''): ?>&middot; <?= h($plugin['author']) ?><?php endif; ?>
							<?php if ($plugin['url'] !== ''): ?>
								&middot; <a href="<?= h($plugin['url']) ?>" target="_blank" rel="noopener noreferrer"><?= t('acp.plg.website') ?></a>
							<?php endif; ?>
						</span>
					</td>

					<td><?= $adds ? h(implode(', ', $adds)) : '<span class="acp-hint">' . t('acp.plg.hooks_only') . '</span>' ?></td>

					<td>
						<?= $plugin['version'] !== '' ? h($plugin['version']) : '&mdash;' ?>
						<?php if ($plugin['update']): ?>
							<span class="acp-hint" style="display:block;"><?= t('acp.plg.installed_label', ['version' => h($plugin['installed_version'])]) ?></span>
						<?php endif; ?>
					</td>

					<td>
						<?php if ($plugin['update']): ?>
							<span class="acp-pill acp-pill--amber"><?= t('acp.plg.update_available') ?></span>
						<?php elseif (!$plugin['installed']): ?>
							<span class="acp-pill"><?= t('acp.plg.not_installed') ?></span>
						<?php elseif ($running): ?>
							<span class="acp-pill acp-pill--green"><?= t('acp.plg.running_pill') ?></span>
						<?php else: ?>
							<span class="acp-pill"><?= t('acp.plg.disabled_pill') ?></span>
						<?php endif; ?>
					</td>

					<td style="text-align:right;white-space:nowrap;">

						<?php if ($running && $plugin['pages']): ?>
							<a class="acp-btn acp-btn--ghost acp-btn--sm"
							   href="<?= h(acp_site(znote_plugin_url($name, $plugin['page_list'][0]))) ?>"
							   target="_blank" rel="noopener"><?= t('acp.plg.view') ?></a>
						<?php endif; ?>

						<?php if (!$plugin['installed']): ?>

							<form method="post" style="display:inline;">
								<?= acp_csrf_field() ?>
								<input type="hidden" name="plugin" value="<?= h($name) ?>">
								<input type="hidden" name="action" value="install">
								<button class="acp-btn acp-btn--green acp-btn--sm" type="submit">
									<i class="fa fa-download"></i> <?= t('acp.plg.install') ?>
								</button>
							</form>

						<?php else: ?>

							<?php if ($plugin['update']): ?>
								<form method="post" style="display:inline;">
									<?= acp_csrf_field() ?>
									<input type="hidden" name="plugin" value="<?= h($name) ?>">
									<input type="hidden" name="action" value="update">
									<button class="acp-btn acp-btn--amber acp-btn--sm" type="submit">
										<i class="fa fa-arrow-up"></i> <?= t('acp.plg.update_to', ['version' => h($plugin['version'])]) ?>
									</button>
								</form>
							<?php endif; ?>

							<form method="post" style="display:inline;">
								<?= acp_csrf_field() ?>
								<input type="hidden" name="plugin" value="<?= h($name) ?>">
								<input type="hidden" name="action" value="<?= $plugin['enabled'] ? 'disable' : 'enable' ?>">
								<button class="acp-btn acp-btn--sm <?= $plugin['enabled'] ? '' : 'acp-btn--green' ?>" type="submit">
									<?= $plugin['enabled'] ? t('acp.plg.disable') : t('acp.plg.enable') ?>
								</button>
							</form>

							<form method="post" style="display:inline;"
							      onsubmit="return confirm('<?= h(t('acp.plg.confirm_uninstall', ['plugin' => $plugin['name']])) ?>');">
								<?= acp_csrf_field() ?>
								<input type="hidden" name="plugin" value="<?= h($name) ?>">
								<input type="hidden" name="action" value="uninstall">
								<button class="acp-btn acp-btn--red acp-btn--sm" type="submit" title="<?= h(t('acp.plg.uninstall')) ?>">
									<i class="fa fa-times"></i>
								</button>
							</form>

						<?php endif; ?>

					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>

<?php acp_card_close(); ?>

<?php acp_card_open(t('acp.plg.how_title'), ''); ?>
	<p>
		<strong><?= t('acp.plg.how_install') ?></strong>
		<?= t('acp.plg.how_install_text', [
			'folder'  => '<code>plugins/</code>',
			'install' => '<em>' . t('acp.plg.install') . '</em>',
		]) ?>
	</p>
	<p>
		<strong><?= t('acp.plg.how_update') ?></strong>
		<?= t('acp.plg.how_update_text', [
			'json'   => '<code>plugin.json</code>',
			'update' => '<em>Update</em>',
		]) ?>
	</p>
	<p>
		<strong><?= t('acp.plg.how_remove') ?></strong>
		<?= t('acp.plg.how_remove_text', [
			'disable'   => '<em>' . t('acp.plg.disable') . '</em>',
			'uninstall' => '<em>' . t('acp.plg.uninstall') . '</em>',
		]) ?>
	</p>
	<p>
		<?= t('acp.plg.how_write', [
			'json'    => '<code>plugin.json</code>',
			'readme'  => '<code>plugins/README.md</code>',
			'example' => '<code>plugins/shop_coupons/</code>',
		]) ?>
	</p>
<?php acp_card_close(); ?>
