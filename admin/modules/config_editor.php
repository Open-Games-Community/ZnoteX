<?php
/**
 * Title: Config values
 * Icon: fa-wrench
 * Group: Server Info
 * Order: 11
 * Description: Edit a single config.lua value without re-uploading the whole file.
 * Hidden: true
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$whitelist = serverdata_config_whitelist();
$current   = serverdata_load('config');
$current   = is_array($current) ? $current : array();

if (!$current) {
	acp_flash_error(t_default('acp.cfged.no_data', 'Upload a config.lua on Server Info first - there is nothing published to edit yet.'));
	acp_redirect('serverinfo');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$adminName = (string)($GLOBALS['user_data']['name'] ?? '');
	$changed   = array();
	$failed    = false;

	foreach ($whitelist as $key) {
		if (!array_key_exists($key, $current)) {
			continue;
		}

		$existing = $current[$key];
		$posted   = $_POST['cfg'][$key] ?? null;

		if (is_bool($existing)) {
			$value = !empty($posted);
		} elseif (is_int($existing)) {
			$value = intv($posted ?? 0);
		} elseif (is_float($existing)) {
			$value = (float)str_replace(',', '.', (string)($posted ?? 0));
		} else {
			$value = trim((string)($posted ?? ''));
		}

		if ($value !== $existing) {
			if (serverdata_override_set('config', $key, ['value' => $value], $adminName)) {
				$changed[] = $key;
			} else {
				$failed = true;
			}
		}
	}

	if ($changed) {
		acp_log('serverdata.config_edit', implode(', ', $changed), ['keys' => $changed]);
		acp_flash_success(t_default('acp.cfged.saved', '{n} value(s) updated.', ['n' => count($changed)]));
	} elseif (!$failed) {
		acp_flash_info(t_default('acp.cfged.nothing_changed', 'Nothing changed.'));
	}

	if ($failed) {
		acp_flash_error(t_default('acp.cfged.save_failed', 'Some values could not be saved - run the pending database migration first (Admin Panel > Migrations).'));
	}

	acp_redirect('config_editor');
}
?>

<?php acp_card_open(t_default('acp.cfged.title', 'Config values'), t_default('acp.cfged.sub', 'These change what is shown on the public server info page. Your OT server\'s own config.lua is never touched.')); ?>

	<form method="post">
		<?= acp_csrf_field() ?>
		<div class="acp-table-wrap">
			<table class="acp-table">
				<thead>
					<tr>
						<th><?= t_default('acp.cfged.col_key', 'Key') ?></th>
						<th><?= t_default('acp.cfged.col_value', 'Value') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($whitelist as $key): ?>
						<?php if (!array_key_exists($key, $current)) continue; ?>
						<?php $value = $current[$key]; ?>
						<tr>
							<td><code><?= h($key) ?></code></td>
							<td>
								<?php if (is_bool($value)): ?>
									<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
										<input type="checkbox" name="cfg[<?= h($key) ?>]" value="1" <?= $value ? 'checked' : '' ?>>
										<span class="is-muted"><?= t_default('acp.cfged.enabled', 'Enabled') ?></span>
									</label>
								<?php elseif (is_int($value) || is_float($value)): ?>
									<input class="acp-input" type="number" step="<?= is_float($value) ? 'any' : '1' ?>" name="cfg[<?= h($key) ?>]" value="<?= h((string)$value) ?>">
								<?php else: ?>
									<input class="acp-input" type="text" name="cfg[<?= h($key) ?>]" value="<?= h((string)$value) ?>">
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="acp-actions">
			<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= t_default('acp.cfged.save_btn', 'Save') ?></button>
			<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('serverinfo')) ?>"><?= t_default('acp.cfged.back', 'Back to Server Info') ?></a>
		</div>
	</form>

<?php acp_card_close(); ?>
