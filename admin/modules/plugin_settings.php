<?php
/**
 * Title: Plugin settings
 * Icon: fa-sliders
 * Group: Settings
 * Order: 21
 * Description: Auto-generated configuration page for a plugin's settings.json.
 * Hidden: true
 */

/*
 * Not linked from the sidebar - reached from the "Settings" button on a
 * plugin's row in Admin Panel > Plugins. A plugin gets this page for free by
 * shipping plugins/<name>/settings.json instead of hand-coding a form; see
 * engine/function/plugin_settings.php for the schema.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$plugin = znote_plugin_sanitize((string)($_GET['plugin'] ?? $_POST['plugin'] ?? ''));
$known = znote_plugins();

if ($plugin === '' || !isset($known[$plugin]) || !znote_plugin_settings_has($plugin)) {
	acp_flash_error(t_default('acp.plgset.no_such', 'That plugin has no settings.json.'));
	acp_redirect('plugins');
}

$manifest = $known[$plugin];
$schema = znote_plugin_settings_schema($plugin);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!acp_verify_csrf()) {
		acp_flash_error(t_default('acp.csrf_failed', 'Your session expired, please try again.'));
		acp_redirect('plugin_settings', array('plugin' => $plugin));
	}

	$errors = znote_plugin_settings_save($plugin, $_POST);

	if ($errors) {
		acp_flash_error(t_default('acp.plgset.save_failed', 'Some fields were not valid and kept their previous value: {fields}.', ['fields' => implode(', ', $errors)]));
	} else {
		acp_log('plugin.settings_save', $plugin);
		acp_flash_success(t_default('acp.plgset.saved', 'Settings saved.'));
	}

	acp_redirect('plugin_settings', array('plugin' => $plugin));
}

$values = znote_plugin_settings_get($plugin);
?>

<?php acp_card_open(t_default('acp.plgset.title', '{plugin} settings', ['plugin' => h($manifest['name'])]), h($manifest['description'])); ?>

	<form method="post">
		<?= acp_csrf_field() ?>
		<input type="hidden" name="plugin" value="<?= h($plugin) ?>">

		<?php if (!$schema): ?>
			<?php acp_empty(t_default('acp.plgset.empty', 'settings.json has no valid fields.'), 'fa-sliders'); ?>
		<?php endif; ?>

		<?php foreach ($schema as $key => $field):
			$value = $values[$key] ?? $field['default'];
			$id = 'plgset_' . $key;
		?>
			<div class="acp-field">
				<label for="<?= h($id) ?>"><?= h($field['label']) ?></label>

				<?php if ($field['type'] === 'bool'): ?>
					<label class="acp-inline">
						<input type="checkbox" id="<?= h($id) ?>" name="<?= h($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
					</label>

				<?php elseif ($field['type'] === 'textarea'): ?>
					<textarea id="<?= h($id) ?>" name="<?= h($key) ?>" rows="4" class="acp-input"><?= h($value) ?></textarea>

				<?php elseif ($field['type'] === 'password'): ?>
					<input type="password" id="<?= h($id) ?>" name="<?= h($key) ?>" value="<?= h($value) ?>" class="acp-input" autocomplete="off">

				<?php elseif ($field['type'] === 'int'): ?>
					<input type="number" id="<?= h($id) ?>" name="<?= h($key) ?>" value="<?= h($value) ?>" class="acp-input"
						<?= $field['min'] !== null ? 'min="' . (int)$field['min'] . '"' : '' ?>
						<?= $field['max'] !== null ? 'max="' . (int)$field['max'] . '"' : '' ?>>

				<?php elseif ($field['type'] === 'select'): ?>
					<select id="<?= h($id) ?>" name="<?= h($key) ?>" class="acp-input">
						<?php foreach ($field['options'] as $optValue => $optLabel): ?>
							<option value="<?= h($optValue) ?>" <?= $value === $optValue ? 'selected' : '' ?>><?= h($optLabel) ?></option>
						<?php endforeach; ?>
					</select>

				<?php elseif ($field['type'] === 'checklist'):
					$chosen = array_flip(array_filter(explode(',', $value)));
				?>
					<?php foreach ($field['options'] as $optValue => $optLabel): ?>
						<label class="acp-inline">
							<input type="checkbox" name="<?= h($key) ?>[]" value="<?= h($optValue) ?>" <?= isset($chosen[$optValue]) ? 'checked' : '' ?>>
							<?= h($optLabel) ?>
						</label>
					<?php endforeach; ?>

				<?php else: ?>
					<input type="text" id="<?= h($id) ?>" name="<?= h($key) ?>" value="<?= h($value) ?>" class="acp-input">
				<?php endif; ?>

				<?php if ($field['help'] !== ''): ?>
					<p class="acp-hint"><?= h($field['help']) ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<?php if ($schema): ?>
			<button type="submit" class="acp-btn acp-btn--green"><?= t_default('acp.plgset.save', 'Save') ?></button>
		<?php endif; ?>
	</form>

<?php acp_card_close(); ?>
