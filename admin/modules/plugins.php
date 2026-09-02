<?php
/**
 * Title: Plugins
 * Icon: fa-plug
 * Group: Plugins
 * Order: 10
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
		acp_flash_error('No such plugin. It may have been removed from plugins/.');
		acp_redirect('plugins');
	}

	$plugin = $known[$name];
	$label  = $plugin['name'];

	switch ($action) {

		case 'install':
			$error = znote_plugin_install($name);

			if ($error !== '') {
				acp_flash_error($label . ' could not be installed: ' . $error);
				break;
			}

			// Installing implies wanting it on. Nobody installs a plugin in
			// order to leave it switched off.
			znote_plugin_set_enabled($name, true);
			acp_flash_success($label . ' ' . $plugin['version'] . ' is installed and enabled.');
			break;

		case 'update':
			$from  = $plugin['installed_version'];
			$error = znote_plugin_install($name);

			if ($error !== '') {
				acp_flash_error($label . ' could not be updated: ' . $error);
			} else {
				acp_flash_success($label . ' updated from ' . $from . ' to ' . $plugin['version'] . '.');
			}
			break;

		case 'enable':
			znote_plugin_set_enabled($name, true);
			acp_flash_success($label . ' is enabled.');
			break;

		case 'disable':
			znote_plugin_set_enabled($name, false);
			acp_flash_info($label . ' is disabled. Its database tables were left untouched.');
			break;

		case 'uninstall':
			// Forgets the install, keeps the data. Dropping a player's coupons
			// because someone clicked a button would be the wrong default.
			znote_plugin_uninstall($name);
			acp_flash_info($label . ' is no longer installed. Its tables and their data are still there, '
				. 'and deleting the folder from plugins/ removes it for good.');
			break;

		default:
			acp_flash_error('Unknown action.');
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
	acp_stat('In plugins/', count($plugins), 'fa-folder-o', null, 'blue');
	acp_stat('Running', $active, 'fa-check', null, 'green');
	if ($updatable > 0) {
		acp_stat('Updates', $updatable, 'fa-arrow-up', null, 'amber');
	}
	?>
</div>

<?php acp_card_open('Plugins', 'Drop a plugin folder into plugins/ and it shows up here'); ?>

	<?php if (!$plugins): ?>

		<?php acp_empty('Nothing in plugins/ yet. Download one, unzip it into that folder, and reload this page.', 'fa-plug'); ?>

	<?php else: ?>

		<table class="acp-table">
			<thead>
				<tr>
					<th>Plugin</th>
					<th>Adds</th>
					<th>Version</th>
					<th>Status</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($plugins as $name => $plugin):

				$adds = array();
				if ($plugin['pages']) { $adds[] = $plugin['pages'] . ' page' . ($plugin['pages'] > 1 ? 's' : ''); }
				if ($plugin['admin']) { $adds[] = $plugin['admin'] . ' admin page' . ($plugin['admin'] > 1 ? 's' : ''); }
				if ($plugin['sql'])   { $adds[] = 'tables'; }

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
								&middot; <a href="<?= h($plugin['url']) ?>" target="_blank" rel="noopener noreferrer">website</a>
							<?php endif; ?>
						</span>
					</td>

					<td><?= $adds ? h(implode(', ', $adds)) : '<span class="acp-hint">hooks only</span>' ?></td>

					<td>
						<?= $plugin['version'] !== '' ? h($plugin['version']) : '&mdash;' ?>
						<?php if ($plugin['update']): ?>
							<span class="acp-hint" style="display:block;">installed: <?= h($plugin['installed_version']) ?></span>
						<?php endif; ?>
					</td>

					<td>
						<?php if ($plugin['update']): ?>
							<span class="acp-pill acp-pill--amber">Update available</span>
						<?php elseif (!$plugin['installed']): ?>
							<span class="acp-pill">Not installed</span>
						<?php elseif ($running): ?>
							<span class="acp-pill acp-pill--green">Running</span>
						<?php else: ?>
							<span class="acp-pill">Disabled</span>
						<?php endif; ?>
					</td>

					<td style="text-align:right;white-space:nowrap;">

						<?php if ($running && $plugin['pages']): ?>
							<a class="acp-btn acp-btn--ghost acp-btn--sm"
							   href="<?= h(acp_site(znote_plugin_url($name, $plugin['page_list'][0]))) ?>"
							   target="_blank" rel="noopener">View</a>
						<?php endif; ?>

						<?php if (!$plugin['installed']): ?>

							<form method="post" style="display:inline;">
								<?= acp_csrf_field() ?>
								<input type="hidden" name="plugin" value="<?= h($name) ?>">
								<input type="hidden" name="action" value="install">
								<button class="acp-btn acp-btn--green acp-btn--sm" type="submit">
									<i class="fa fa-download"></i> Install
								</button>
							</form>

						<?php else: ?>

							<?php if ($plugin['update']): ?>
								<form method="post" style="display:inline;">
									<?= acp_csrf_field() ?>
									<input type="hidden" name="plugin" value="<?= h($name) ?>">
									<input type="hidden" name="action" value="update">
									<button class="acp-btn acp-btn--amber acp-btn--sm" type="submit">
										<i class="fa fa-arrow-up"></i> Update to <?= h($plugin['version']) ?>
									</button>
								</form>
							<?php endif; ?>

							<form method="post" style="display:inline;">
								<?= acp_csrf_field() ?>
								<input type="hidden" name="plugin" value="<?= h($name) ?>">
								<input type="hidden" name="action" value="<?= $plugin['enabled'] ? 'disable' : 'enable' ?>">
								<button class="acp-btn acp-btn--sm <?= $plugin['enabled'] ? '' : 'acp-btn--green' ?>" type="submit">
									<?= $plugin['enabled'] ? 'Disable' : 'Enable' ?>
								</button>
							</form>

							<form method="post" style="display:inline;"
							      onsubmit="return confirm('Forget that <?= h($plugin['name']) ?> is installed? Its tables and their data stay in the database.');">
								<?= acp_csrf_field() ?>
								<input type="hidden" name="plugin" value="<?= h($name) ?>">
								<input type="hidden" name="action" value="uninstall">
								<button class="acp-btn acp-btn--red acp-btn--sm" type="submit" title="Uninstall">
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

<?php acp_card_open('How this works', ''); ?>
	<p>
		<strong>Installing.</strong> Download the plugin, unzip it into <code>plugins/</code>,
		reload this page and press <em>Install</em>. That creates its database tables and
		switches it on. ZnoteX never downloads a plugin by itself: a plugin is PHP that runs
		on every page of your site, so putting the files there stays your decision.
	</p>
	<p>
		<strong>Updating.</strong> Replace the folder with the newer version. If its
		<code>plugin.json</code> carries a higher version than the one you installed, an
		<em>Update</em> button appears here and applies whatever the new version needs.
	</p>
	<p>
		<strong>Removing.</strong> <em>Disable</em> stops a plugin. <em>Uninstall</em> also
		forgets its version, so it offers to install again. Neither one deletes data &mdash;
		to remove a plugin for good, delete its folder and drop its tables yourself.
	</p>
	<p>
		Writing one is a folder, a <code>plugin.json</code>, and nothing else required:
		see <code>plugins/README.md</code>, with <code>plugins/shop_coupons/</code> as a
		working example.
	</p>
<?php acp_card_close(); ?>
