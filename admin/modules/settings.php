<?php
/**
 * Title: Settings
 * Icon: fa-cogs
 * Group: Settings
 * Order: 30
 * Description: Change the settings that used to mean editing config.php by FTP.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

/**
 * What this page can change.
 *
 * Each entry names a config key, and the value is stored in znote_config so it
 * survives an update to config.php. engine/init.php applies them over the file
 * values, so config.php stays the fallback and nothing here is ever written
 * back into a PHP file.
 *
 *   type: text | textarea | bool | int | select
 */
function acp_settings_schema(): array {
	return require __DIR__ . '/_partials/settings_schema.php';
}

/** Cast a submitted value for storage. */
function acp_setting_cast(array $field, $raw): string {
	switch ($field['type']) {
		case 'bool': return empty($raw) ? '0' : '1';
		case 'int':
			$value = intv($raw);
			if (isset($field['min'])) $value = max((int)$field['min'], $value);
			if (isset($field['max'])) $value = min((int)$field['max'], $value);
			return (string)$value;

		case 'select':
			$options = $field['options'] ?? array();
			$value   = trim((string)$raw);
			return isset($options[$value]) ? $value : (string)array_key_first($options);

		case 'checklist':
			$options = $field['options'] ?? array();
			$picked  = is_array($raw) ? $raw : array();
			$kept    = array();

			foreach (array_keys($options) as $option) {
				if (in_array((string)$option, array_map('strval', $picked), true)) {
					$kept[] = (string)$option;
				}
			}

			if (!$kept && $options) {
				$kept[] = (string)array_key_first($options);
			}

			return (string)json_encode($kept);

		default: return trim((string)$raw);
	}
}

// ---------------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$schema = acp_settings_schema();
	$saved  = 0;
	$failed = 0;

	foreach ($schema as $fields) {
		foreach ($fields as $key => $field) {
			$value = acp_setting_cast($field, $_POST['set'][$key] ?? '');
			setting_set('config:' . $key, $value) ? $saved++ : $failed++;
		}
	}

	if ($failed > 0) {
		acp_flash_error(t('acp.settings.save_failed', ['n' => $failed, 'table' => '<code>znote_config</code>']));
	} else {
		acp_log('settings.save', '', ['fields_saved' => $saved]);
		acp_flash_success(t('acp.settings.save_success', ['n' => $saved]));
	}

	acp_redirect('settings');
}

$schema   = acp_settings_schema();
$hasTable = znote_table_exists('znote_config');
?>

<?php if (!$hasTable): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			<?= t('acp.settings.table_missing', [
				'table' => '<code>znote_config</code>',
				'file'  => '<code>SQL/migrations/2.0.0_znote_config.sql</code>',
			]) ?>
		</span>
	</div>
<?php endif; ?>

<div class="acp-flash acp-flash--info">
	<i class="fa fa-info-circle"></i>
	<span>
		<?= t('acp.settings.stored_note', [
			'configphp'  => '<code>config.php</code>',
			'configphp2' => '<code>config.php</code>',
		]) ?>
	</span>
</div>

<form method="post">
	<?= acp_csrf_field() ?>

	<div class="acp-grid acp-grid--2">
		<?php foreach ($schema as $section => $fields): ?>
			<section class="acp-card">
				<header class="acp-card-head">
					<h2><?= h($section) ?></h2>
					<button class="acp-btn acp-btn--green acp-btn--sm" type="submit" style="margin-left:auto;">
						<i class="fa fa-check"></i> <?= t('acp.settings.save_btn') ?>
					</button>
				</header>
				<div class="acp-card-body">
					<?php foreach ($fields as $key => $field):
						$stored  = setting('config:' . $key, null);
						$fromFile = znote_config_path($config, $key, '');
						if (is_bool($fromFile)) {
							$fromFile = $fromFile ? '1' : '0';
						} elseif (is_array($fromFile)) {
							$fromFile = ($field['type'] === 'checklist') ? (string)json_encode(array_values($fromFile)) : '';
						}
						$current = ($stored !== null) ? $stored : (string)$fromFile;
						$fromDb  = ($stored !== null);
					?>
						<div class="acp-field">
							<label class="acp-label" for="set_<?= h($key) ?>">
								<?= h($field['label']) ?>
								<?php if (!$fromDb): ?>
									<span class="acp-pill acp-pill--grey" title="<?= h(t('acp.settings.following_title')) ?>"><?= t('acp.settings.file_pill') ?></span>
								<?php endif; ?>
							</label>

							<?php if ($field['type'] === 'bool'): ?>
								<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
									<input type="checkbox" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" value="1"
										   <?= !empty($current) && $current !== '0' ? 'checked' : '' ?>>
									<span class="is-muted"><?= t('acp.settings.enabled') ?></span>
								</label>
							<?php elseif ($field['type'] === 'textarea'): ?>
								<textarea class="acp-textarea" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" rows="3"><?= h($current) ?></textarea>
							<?php elseif ($field['type'] === 'checklist'):
								$picked = json_decode($current, true);
								if (!is_array($picked)) {
									$picked = array_filter(array_map('trim', explode(',', $current)), 'strlen');
								}
								$picked = array_map('strval', $picked);
							?>
								<div class="acp-checklist">
									<?php foreach (($field['options'] ?? array()) as $value => $caption): ?>
										<label style="display:flex;align-items:center;gap:8px;font-weight:400;padding:3px 0;">
											<input type="checkbox" name="set[<?= h($key) ?>][]" value="<?= h((string)$value) ?>"
												   <?= in_array((string)$value, $picked, true) ? 'checked' : '' ?>>
											<span><?= h($caption) ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php elseif ($field['type'] === 'select'): ?>
								<select class="acp-input" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]">
									<?php foreach (($field['options'] ?? array()) as $value => $caption): ?>
										<option value="<?= h((string)$value) ?>" <?= ((string)$value === $current) ? 'selected' : '' ?>>
											<?= h($caption) ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else: ?>
								<input class="acp-input" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]"
									   type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>"
									   <?php if ($field['type'] === 'int' && isset($field['min'])): ?>min="<?= (int)$field['min'] ?>"<?php endif; ?>
									   <?php if ($field['type'] === 'int' && isset($field['max'])): ?>max="<?= (int)$field['max'] ?>"<?php endif; ?>
									   value="<?= h($current) ?>">
							<?php endif; ?>

							<?php if (!empty($field['help'])): ?>
								<p class="acp-hint"><?= h($field['help']) ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<div class="acp-actions">
		<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= t('acp.settings.save_btn') ?></button>
		<span class="acp-hint"><?= t('acp.settings.legend_pill', ['pill' => '<span class="acp-pill acp-pill--grey">' . t('acp.settings.file_pill') . '</span>']) ?></span>
	</div>
</form>
