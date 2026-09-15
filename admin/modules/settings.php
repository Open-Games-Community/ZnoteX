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

/**
 * Every grantable admin module, grouped the same way the sidebar groups them,
 * so the permissions table reads like a shrunken copy of the sidebar instead
 * of an abstract role name. 'settings' IS included - an owner can hand out
 * general Settings access - but the Permissions section within it stays
 * owner-only no matter what (see acp_is_owner() checks below), so granting
 * 'settings' can never let that account edit its own access.
 */
function acp_permission_module_groups(): array {
	$groups = array();
	foreach (acp_modules() as $key => $module) {
		if (!empty($module['hidden'])) continue;
		$groups[$module['group']][$key] = $module['title'];
	}
	return $groups;
}

/**
 * page_admin_access (a flat list of owner account names) and page_admin_roles
 * (account name => list of granted module keys) are two separate config
 * arrays, but the panel edits them as one table: an "Owner" checkbox plus one
 * checkbox per module. Owner wins if an account somehow ended up in both.
 */
function acp_permissions_current(): array {
	global $config;

	$accessStored = setting('config:page_admin_access', null);
	$access = $accessStored !== null
		? (json_decode($accessStored, true) ?: array())
		: (array)znote_config_path($config, 'page_admin_access', array());

	$rolesStored = setting('config:page_admin_roles', null);
	$roles = $rolesStored !== null
		? (json_decode($rolesStored, true) ?: array())
		: (array)znote_config_path($config, 'page_admin_roles', array());

	$rows = array();
	foreach ($access as $name) {
		$name = trim((string)$name);
		if ($name === '') continue;
		$rows[$name] = array('account' => $name, 'owner' => true, 'modules' => array());
	}
	foreach ($roles as $name => $assigned) {
		$name = trim((string)$name);
		if ($name === '' || isset($rows[$name])) continue;
		$rows[$name] = array('account' => $name, 'owner' => false, 'modules' => array_values((array)$assigned));
	}

	return array_values($rows);
}

/** One account's editable block: name + Owner toggle + a module checklist grouped like the sidebar. */
function acp_render_permission_account(string $key, $rowIndex, array $row, array $moduleGroups): string {
	$prefix = 'set[' . $key . '][rows][' . $rowIndex . ']';

	$html = '<div class="acp-perm-account">';
	$html .= '<div class="acp-perm-account-head">';
	$html .= '<input class="acp-input" type="text" name="' . h($prefix) . '[account]" value="' . h($row['account']) . '" placeholder="' . h(t_default('acp.perm.account_placeholder', 'Account name')) . '">';
	$html .= '<label class="acp-perm-owner-toggle"><input type="checkbox" class="acp-perm-owner-check" name="' . h($prefix) . '[owner]" value="1" ' . ($row['owner'] ? 'checked' : '') . '> ' . h(t_default('acp.perm.col_owner', 'Owner')) . '</label>';
	$html .= '<button type="button" class="acp-btn acp-btn--sm acp-btn--red" data-acp-table-remove>' . h(t_default('acp.perm.remove_account', 'Remove')) . '</button>';
	$html .= '</div>';
	$html .= '<div class="acp-perm-modules" ' . ($row['owner'] ? 'hidden' : '') . '>';
	foreach ($moduleGroups as $groupLabel => $modules) {
		$html .= '<div class="acp-perm-group"><h4>' . h($groupLabel) . '</h4>';
		foreach ($modules as $modKey => $modTitle) {
			$checked = in_array($modKey, $row['modules'], true) ? 'checked' : '';
			$html .= '<label class="acp-perm-mod"><input type="checkbox" name="' . h($prefix) . '[modules][]" value="' . h($modKey) . '" ' . $checked . '> ' . h($modTitle) . '</label>';
		}
		$html .= '</div>';
	}
	$html .= '</div>';
	$html .= '</div>';

	return $html;
}

/** Turn a section label into a stable, URL/hash-friendly tab id. */
function acp_settings_tab_slug(string $label): string {
	$slug = strtolower($label);
	$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
	$slug = trim((string)$slug, '-');
	return $slug !== '' ? $slug : 'section';
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

		case 'json':
			$decoded = json_decode(is_string($raw) ? $raw : '', true);
			return is_array($decoded)
				? (string)json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
				: '';

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

			if (!empty($field['int_values'])) {
				$kept = array_values(array_map('intval', $kept));
			}

			return (string)json_encode($kept);

		default: return trim((string)$raw);
	}
}

/**
 * acp_table_rows_from_json() / acp_table_cell_to_string() / acp_table_cell_input() /
 * acp_table_cell_from_post() are defined in admin/bootstrap.php (shared with the
 * serverdata single-record editors). acp_table_json_from_rows() stays here because
 * this map-shape branch is specific to this module's numeric-id schema fields
 * (towns/vocations/skills) - it rejects non-digit keys, which a generic reuse
 * elsewhere should not inherit.
 */

/** Convert posted table rows back into the JSON structure used for storage. */
function acp_table_json_from_rows(array $field, array $rowsRaw): array {
	$shape = $field['json_shape'] ?? 'map';

	if ($shape === 'list') {
		$json = array();
		foreach ($rowsRaw as $row) {
			if (!is_array($row)) continue;
			$obj = array();
			foreach ($field['columns'] as $colKey => $colDef) {
				$obj[$colKey] = acp_table_cell_from_post($colDef, $row[$colKey] ?? null);
			}
			// Skip fully blank rows (an "add row" the admin never filled in).
			$hasContent = false;
			foreach ($obj as $v) {
				if ($v !== '' && $v !== false) { $hasContent = true; break; }
			}
			if (!$hasContent) continue;
			$json[] = $obj;
		}
		return $json;
	}

	$rowKey      = $field['row_key'] ?? 'id';
	$valueColumn = $field['value_column'] ?? null;
	$json        = array();

	foreach ($rowsRaw as $row) {
		if (!is_array($row)) continue;
		$keyRaw = trim((string)($row[$rowKey] ?? ''));
		if ($keyRaw === '' || !ctype_digit($keyRaw)) continue;
		$key = (string)(int)$keyRaw;

		if ($valueColumn !== null) {
			$json[$key] = trim((string)($row[$valueColumn] ?? ''));
			continue;
		}

		$obj = array();
		foreach ($field['columns'] as $colKey => $colDef) {
			if ($colKey === $rowKey) continue;
			$obj[$colKey] = acp_table_cell_from_post($colDef, $row[$colKey] ?? null);
		}
		$json[$key] = $obj;
	}

	return $json;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
	$removed = znote_cache_flush();
	acp_log('cache.flush', '', ['entries' => $removed]);
	acp_flash_success(t_default('acp.settings.cache_cleared', 'Cache cleared: {n} entries removed.', ['n' => $removed]));
	acp_redirect('settings');
}

// ---------------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$schema = acp_settings_schema();
	$saved  = 0;
	$failed = 0;
	$savedKeys = array();

	foreach ($schema as $fields) {
		foreach ($fields as $key => $field) {
			$raw = $_POST['set'][$key] ?? '';

			if (($field['type'] ?? '') === 'permissions') {
				// Whoever is submitting this form, only a literal Owner may change
				// who has access. Without this check, an account merely granted
				// the 'settings' module could open this same tab and grant itself
				// Owner - the module-level check alone cannot catch that, since
				// this whole page is one form and 'settings' access already lets
				// the request reach this branch.
				if (!acp_is_owner()) {
					$failed++;
					acp_flash_error(t_default('acp.perm.owner_only', 'Only an Owner can change permissions.'));
					continue;
				}

				$rowsRaw = (is_array($raw) && isset($raw['rows']) && is_array($raw['rows'])) ? $raw['rows'] : array();
				$validModules = array_keys(acp_modules());
				$access = array();
				$roles  = array();

				foreach ($rowsRaw as $row) {
					if (!is_array($row)) continue;
					$account = trim((string)($row['account'] ?? ''));
					if ($account === '') continue;

					if (!empty($row['owner'])) {
						$access[] = $account;
						continue;
					}

					$picked = array_values(array_intersect($validModules, array_map('strval', (array)($row['modules'] ?? array()))));
					if ($picked) {
						$roles[$account] = $picked;
					}
				}

				$access = array_values(array_unique($access));

				// Never save a state with zero owners - that would lock every
				// admin, including the one submitting this form, out of the
				// panel with no way back in short of editing the database by hand.
				if (!$access) {
					$failed++;
					acp_flash_error(t_default('acp.perm.no_owner', 'At least one account must stay an Owner. Permissions were not saved.'));
					continue;
				}

				$accessOk = setting_set('config:page_admin_access', (string)json_encode($access));
				$rolesOk  = setting_set('config:page_admin_roles', (string)json_encode($roles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

				if ($accessOk && $rolesOk) {
					$saved++;
					$savedKeys[] = 'page_admin_access';
					$savedKeys[] = 'page_admin_roles';
				} else {
					$failed++;
				}
				continue;
			}

			if (($field['type'] ?? '') === 'table') {
				$rowsRaw = (is_array($raw) && isset($raw['rows']) && is_array($raw['rows'])) ? $raw['rows'] : array();
				$jsonArr = acp_table_json_from_rows($field, $rowsRaw);
				if (setting_set('config:' . $key, (string)json_encode($jsonArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
					$saved++;
					$savedKeys[] = $key;
				} else $failed++;
				continue;
			}

			if (($field['type'] ?? '') === 'json') {
				$decoded = json_decode(is_string($raw) ? $raw : '', true);
				if (!is_array($decoded)) { $failed++; continue; }
				if (setting_set('config:' . $key, (string)json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
					$saved++;
					$savedKeys[] = $key;
				} else $failed++;
				continue;
			}

			$value = acp_setting_cast($field, $raw);
			if (setting_set('config:' . $key, $value)) {
				$saved++;
				$savedKeys[] = $key;
			} else $failed++;
		}
	}

	if ($saved > 0) {
		acp_log('settings.save', '', ['fields' => $savedKeys, 'failed' => $failed]);
	}
	if ($failed > 0) {
		acp_flash_error(t('acp.settings.save_failed', ['n' => $failed, 'table' => '<code>znote_config</code>']));
	} else {
		acp_flash_success(t('acp.settings.save_success', ['n' => $saved]));
	}

	acp_redirect('settings');
}

$schema   = acp_settings_schema();
$hasTable = znote_table_exists('znote_config');
$cacheStats = znote_cache_stats();
$cacheSize = $cacheStats['bytes'] >= 1048576
	? number_format($cacheStats['bytes'] / 1048576, 1) . ' MB'
	: number_format($cacheStats['bytes'] / 1024, 1) . ' KB';
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

<section class="acp-card" style="margin-bottom:20px;">
	<header class="acp-card-head">
		<h2><?= h(t_default('acp.settings.cache_status', 'Cache status')) ?></h2>
		<form method="post" style="margin-left:auto;">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="clear_cache" value="1">
			<button class="acp-btn acp-btn--red acp-btn--sm" type="submit">
				<i class="fa fa-trash"></i> <?= h(t_default('acp.settings.cache_clear', 'Clear cache')) ?>
			</button>
		</form>
	</header>
	<div class="acp-card-body">
		<dl class="acp-dl">
			<dt><?= h(t_default('acp.settings.cache_driver', 'Active driver')) ?></dt>
			<dd><span class="acp-pill acp-pill--blue"><?= h($cacheStats['driver']) ?></span></dd>
			<dt><?= h(t_default('acp.settings.cache_namespace', 'Namespace')) ?></dt>
			<dd><code><?= h($cacheStats['prefix']) ?></code></dd>
			<dt><?= h(t_default('acp.settings.cache_files', 'File entries')) ?></dt>
			<dd><?= number_format($cacheStats['files']) ?> · <?= h($cacheSize) ?></dd>
			<dt>APCu</dt>
			<dd>
				<span class="acp-pill acp-pill--<?= $cacheStats['apcu_available'] ? 'green' : 'grey' ?>">
					<?= h($cacheStats['apcu_available']
						? t_default('acp.settings.cache_available', 'Available')
						: t_default('acp.settings.cache_unavailable', 'Unavailable')) ?>
				</span>
				<?php if ($cacheStats['requested_memory'] && !$cacheStats['apcu_available']): ?>
					<span class="acp-hint"><?= h(t_default('acp.settings.cache_fallback', 'File fallback is active.')) ?></span>
				<?php endif; ?>
			</dd>
		</dl>
	</div>
</section>

<form method="post">
	<?= acp_csrf_field() ?>

	<div class="acp-settings-tabs" role="tablist">
		<?php $acpTabIndex = 0; foreach ($schema as $section => $fields): $acpTabSlug = acp_settings_tab_slug((string)$section); ?>
			<button type="button" class="acp-settings-tab <?= $acpTabIndex === 0 ? 'is-active' : '' ?>"
					role="tab" data-acp-settings-tab="<?= h($acpTabSlug) ?>"><?= h($section) ?></button>
		<?php $acpTabIndex++; endforeach; ?>
	</div>

	<div class="acp-settings-panels">
		<?php $acpTabIndex = 0; foreach ($schema as $section => $fields): $acpTabSlug = acp_settings_tab_slug((string)$section); ?>
			<div class="acp-settings-panel" data-acp-settings-panel="<?= h($acpTabSlug) ?>" <?= $acpTabIndex === 0 ? '' : 'hidden' ?>>
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
						$fromFile = znote_config_path($config, $key, $field['default'] ?? '');
						if (is_bool($fromFile)) {
							$fromFile = $fromFile ? '1' : '0';
						} elseif (is_array($fromFile)) {
							if ($field['type'] === 'checklist') {
								$fromFile = (string)json_encode(array_values($fromFile));
							} elseif ($field['type'] === 'json' || $field['type'] === 'table') {
								$fromFile = (string)json_encode($fromFile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
							} else {
								$fromFile = '';
							}
						}
						$current = ($stored !== null) ? $stored : (string)$fromFile;
						if (($field['type'] === 'json' || $field['type'] === 'table') && $stored !== null) {
							$decoded = json_decode($stored, true);
							if (is_array($decoded)) {
								$current = (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
							}
						}
						$fromDb  = ($stored !== null);
					?>
						<div class="acp-field">
							<label class="acp-label" for="set_<?= h($key) ?>">
								<?= h($field['label']) ?>
								<?php if (!$fromDb): ?>
									<span class="acp-pill acp-pill--grey" title="<?= h(t('acp.settings.following_title')) ?>"><?= t('acp.settings.file_pill') ?></span>
								<?php endif; ?>
							</label>

							<?php if ($field['type'] === 'permissions'): ?>
								<?php if (!acp_is_owner()): ?>
									<p class="acp-hint"><?= t_default('acp.perm.owner_only_view', 'Only an Owner can view or change this.') ?></p>
								<?php else:
									$permRows   = acp_permissions_current();
									$permGroups = acp_permission_module_groups();
								?>
									<div class="acp-perm-list" data-acp-table="<?= h($key) ?>">
										<div data-acp-table-body>
											<?php foreach ($permRows as $i => $row): ?>
												<?= acp_render_permission_account($key, (int)$i, $row, $permGroups) ?>
											<?php endforeach; ?>
										</div>
									</div>
									<button type="button" class="acp-btn acp-btn--sm" data-acp-table-add="<?= h($key) ?>">
										<i class="fa fa-plus"></i> <?= t_default('acp.perm.add_account', 'Add account') ?>
									</button>
									<template data-acp-table-template="<?= h($key) ?>">
										<?= acp_render_permission_account($key, '__ROWIDX__', array('account' => '', 'owner' => false, 'modules' => array()), $permGroups) ?>
									</template>
								<?php endif; ?>
							<?php elseif ($field['type'] === 'bool'): ?>
								<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
									<input type="checkbox" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" value="1"
										   <?= !empty($current) && $current !== '0' ? 'checked' : '' ?>>
									<span class="is-muted"><?= t('acp.settings.enabled') ?></span>
								</label>
							<?php elseif ($field['type'] === 'textarea'): ?>
								<textarea class="acp-textarea" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" rows="3"><?= h($current) ?></textarea>
							<?php elseif ($field['type'] === 'json'): ?>
								<textarea class="acp-textarea" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" rows="12" spellcheck="false"
										  style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre;min-height:200px;"><?= h($current) ?></textarea>
							<?php elseif ($field['type'] === 'table'):
								$tableDecoded = json_decode($current, true);
								if (!is_array($tableDecoded)) $tableDecoded = array();
								$tableRows = acp_table_rows_from_json($field, $tableDecoded);
							?>
								<table class="acp-table acp-table--editable" data-acp-table="<?= h($key) ?>">
									<thead>
										<tr>
											<?php foreach ($field['columns'] as $colDef): ?>
												<th><?= h($colDef['label']) ?></th>
											<?php endforeach; ?>
											<th></th>
										</tr>
									</thead>
									<tbody data-acp-table-body>
										<?php foreach ($tableRows as $i => $row): ?>
											<tr>
												<?php foreach ($field['columns'] as $colKey => $colDef): ?>
													<td><?= acp_table_cell_input($colDef, 'set[' . $key . '][rows][' . (int)$i . '][' . $colKey . ']', (string)($row[$colKey] ?? '')) ?></td>
												<?php endforeach; ?>
												<td><button type="button" class="acp-btn acp-btn--sm acp-btn--red" data-acp-table-remove>&times;</button></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<button type="button" class="acp-btn acp-btn--sm" data-acp-table-add="<?= h($key) ?>">
									<i class="fa fa-plus"></i> <?= t_default('acp.settings.table_add_row', 'Add row') ?>
								</button>
								<template data-acp-table-template="<?= h($key) ?>">
									<tr>
										<?php foreach ($field['columns'] as $colKey => $colDef): ?>
											<td><?= acp_table_cell_input($colDef, 'set[' . $key . '][rows][__ROWIDX__][' . $colKey . ']', '') ?></td>
										<?php endforeach; ?>
										<td><button type="button" class="acp-btn acp-btn--sm acp-btn--red" data-acp-table-remove>&times;</button></td>
									</tr>
								</template>
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
			</div>
		<?php $acpTabIndex++; endforeach; ?>
	</div>

	<div class="acp-actions">
		<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= t('acp.settings.save_btn') ?></button>
		<span class="acp-hint"><?= t('acp.settings.legend_pill', ['pill' => '<span class="acp-pill acp-pill--grey">' . t('acp.settings.file_pill') . '</span>']) ?></span>
	</div>
</form>

<script>
document.addEventListener('click', function (e) {
	var tabBtn = e.target.closest('[data-acp-settings-tab]');
	if (tabBtn) {
		var tabId = tabBtn.getAttribute('data-acp-settings-tab');
		document.querySelectorAll('.acp-settings-tab').forEach(function (t) {
			t.classList.toggle('is-active', t === tabBtn);
		});
		document.querySelectorAll('.acp-settings-panel').forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-acp-settings-panel') !== tabId;
		});
		try { sessionStorage.setItem('acp_settings_tab', tabId); } catch (err) {}
		return;
	}
	// Add/remove-row table handling (data-acp-table-add/-remove) lives in acp.js, shared with other modules.
});

document.addEventListener('change', function (e) {
	if (!e.target.classList.contains('acp-perm-owner-check')) return;
	var account = e.target.closest('.acp-perm-account');
	var modules = account ? account.querySelector('.acp-perm-modules') : null;
	if (modules) modules.hidden = e.target.checked;
});

document.addEventListener('DOMContentLoaded', function () {
	var wanted = (location.hash || '').replace(/^#/, '');
	var btn = null;

	// Search results link to the field itself (for example #set_serverName).
	// Resolve that field's panel so the correct tab is visible before the
	// generic search-arrival script highlights and scrolls to it.
	if (wanted) {
		var target = document.getElementById(wanted);
		var panel = target && target.closest ? target.closest('[data-acp-settings-panel]') : null;
		if (panel) {
			btn = document.querySelector('[data-acp-settings-tab="' + panel.getAttribute('data-acp-settings-panel') + '"]');
		} else {
			btn = document.querySelector('[data-acp-settings-tab="' + wanted + '"]');
		}
	}
	if (!wanted) {
		try { wanted = sessionStorage.getItem('acp_settings_tab') || ''; } catch (err) {}
		if (wanted) btn = document.querySelector('[data-acp-settings-tab="' + wanted + '"]');
	}
	if (btn) btn.click();
});
</script>
