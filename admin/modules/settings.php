<?php
/**
 * Title: Settings
 * Icon: fa-cogs
 * Group: Overview
 * Order: 20
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
function acp_setting_cast(string $type, $raw): string {
	switch ($type) {
		case 'bool': return empty($raw) ? '0' : '1';
		case 'int':  return (string)intv($raw);
		default:     return trim((string)$raw);
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
			$value = acp_setting_cast($field['type'], $_POST['set'][$key] ?? '');
			setting_set('config:' . $key, $value) ? $saved++ : $failed++;
		}
	}

	if ($failed > 0) {
		acp_flash_error($failed . ' setting(s) could not be saved. Is the <code>znote_config</code> table present?');
	} else {
		acp_flash_success($saved . ' settings saved. They take effect immediately.');
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
			The <code>znote_config</code> table is missing, so nothing can be saved. Run
			<code>SQL/migrations/2.0.0_znote_config.sql</code> against your database.
		</span>
	</div>
<?php endif; ?>

<div class="acp-flash acp-flash--info">
	<i class="fa fa-info-circle"></i>
	<span>
		These are stored in the database and applied over <code>config.php</code>, which stays the
		fallback. Your file is never rewritten, so an update to ZnoteX cannot lose your settings &mdash;
		and a value you have never touched here keeps following <code>config.php</code>.
	</span>
</div>

<form method="post">
	<?= acp_csrf_field() ?>

	<div class="acp-grid acp-grid--2">
		<?php foreach ($schema as $section => $fields): ?>
			<section class="acp-card">
				<header class="acp-card-head"><h2><?= h($section) ?></h2></header>
				<div class="acp-card-body">
					<?php foreach ($fields as $key => $field):
						$stored  = setting('config:' . $key, null);
						$fromFile = znote_config_path($config, $key, '');
						if (is_bool($fromFile)) {
							$fromFile = $fromFile ? '1' : '0';
						} elseif (is_array($fromFile)) {
							$fromFile = '';
						}
						$current = ($stored !== null) ? $stored : (string)$fromFile;
						$fromDb  = ($stored !== null);
					?>
						<div class="acp-field">
							<label class="acp-label" for="set_<?= h($key) ?>">
								<?= h($field['label']) ?>
								<?php if (!$fromDb): ?>
									<span class="acp-pill acp-pill--grey" title="Currently following config.php">file</span>
								<?php endif; ?>
							</label>

							<?php if ($field['type'] === 'bool'): ?>
								<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
									<input type="checkbox" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" value="1"
										   <?= !empty($current) && $current !== '0' ? 'checked' : '' ?>>
									<span class="is-muted">Enabled</span>
								</label>
							<?php elseif ($field['type'] === 'textarea'): ?>
								<textarea class="acp-textarea" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" rows="3"><?= h($current) ?></textarea>
							<?php else: ?>
								<input class="acp-input" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]"
									   type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>"
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
		<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> Save settings</button>
		<span class="acp-hint">Marked <span class="acp-pill acp-pill--grey">file</span> means the value still comes from config.php.</span>
	</div>
</form>
