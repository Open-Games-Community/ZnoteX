<?php
/**
 * Plugin Settings API.
 *
 * A plugin that ships plugins/<name>/settings.json gets a configuration page
 * in the admin panel for free - Admin Panel > Plugins > Settings - instead of
 * hand-coding a form. Values are stored under the same "plugin:<name>:setting:<key>"
 * namespace $api->setting() already reads from extensions.php, so a plugin.php
 * that calls $api->setting('mode') sees exactly what the generated form saved.
 *
 * settings.json:
 * {
 *   "fields": [
 *     {"key": "api_key", "label": "API key", "type": "text", "default": ""},
 *     {"key": "enabled", "label": "Enabled", "type": "bool", "default": "1"},
 *     {"key": "mode", "label": "Mode", "type": "select", "default": "test",
 *      "options": {"test": "Test", "live": "Live"}},
 *     {"key": "max_items", "label": "Max items", "type": "int", "default": "10", "min": 1, "max": 100},
 *     {"key": "notes", "label": "Notes", "type": "textarea", "default": ""},
 *     {"key": "webhook_secret", "label": "Webhook secret", "type": "password", "default": ""}
 *   ]
 * }
 *
 * Supported types: text, textarea, password, bool, int, select, checklist.
 */

const ZNOTE_PLUGIN_SETTINGS_TYPES = array('text', 'textarea', 'password', 'bool', 'int', 'select', 'checklist');

function znote_plugin_settings_file(string $plugin): string {
	return ZNOTE_PLUGIN_DIR . '/' . $plugin . '/settings.json';
}

function znote_plugin_settings_has(string $plugin): bool {
	$plugin = znote_plugin_sanitize($plugin);
	return $plugin !== '' && is_file(znote_plugin_settings_file($plugin));
}

/**
 * Reads and normalizes settings.json. A malformed file, an unknown type, or a
 * field key that would not survive ZnoteExtensionApi::settingKey() is dropped
 * rather than allowed to reach a form or a query.
 */
function znote_plugin_settings_schema(string $plugin): array {
	$plugin = znote_plugin_sanitize($plugin);
	if ($plugin === '' || !znote_plugin_settings_has($plugin)) {
		return array();
	}

	$data = json_decode((string)file_get_contents(znote_plugin_settings_file($plugin)), true);
	if (!is_array($data) || !isset($data['fields']) || !is_array($data['fields'])) {
		return array();
	}

	$fields = array();
	foreach ($data['fields'] as $field) {
		if (!is_array($field)) {
			continue;
		}

		$key = strtolower(trim((string)($field['key'] ?? '')));
		$type = strtolower(trim((string)($field['type'] ?? 'text')));

		if ($key === '' || !preg_match('/^[a-z0-9_.-]{1,100}$/', $key) || !in_array($type, ZNOTE_PLUGIN_SETTINGS_TYPES, true)) {
			continue;
		}

		$normalized = array(
			'key' => $key,
			'type' => $type,
			'label' => (string)($field['label'] ?? ucwords(str_replace(array('_', '.'), ' ', $key))),
			'help' => (string)($field['help'] ?? ''),
			'default' => (string)($field['default'] ?? ''),
		);

		if (in_array($type, array('select', 'checklist'), true)) {
			$options = array();
			foreach ((array)($field['options'] ?? array()) as $value => $label) {
				$options[(string)$value] = (string)$label;
			}
			$normalized['options'] = $options;
		}

		if ($type === 'int') {
			$normalized['min'] = array_key_exists('min', $field) ? (int)$field['min'] : null;
			$normalized['max'] = array_key_exists('max', $field) ? (int)$field['max'] : null;
		}

		$fields[$key] = $normalized;
	}

	return $fields;
}

function znote_plugin_settings_storage_key(string $plugin, string $field): string {
	return 'plugin:' . $plugin . ':setting:' . $field;
}

/** Every field's current value: the stored one, or the schema default. */
function znote_plugin_settings_get(string $plugin): array {
	$plugin = znote_plugin_sanitize($plugin);
	$values = array();

	foreach (znote_plugin_settings_schema($plugin) as $key => $field) {
		$values[$key] = setting(znote_plugin_settings_storage_key($plugin, $key), $field['default']) ?? $field['default'];
	}

	return $values;
}

/**
 * Sanitizes one submitted value against its field definition. Returns the
 * string to store, or null when the input is invalid for its type - the
 * caller then leaves the previous value untouched and reports the field.
 */
function znote_plugin_settings_sanitize_field(array $field, $raw): ?string {
	switch ($field['type']) {

		case 'bool':
			return ($raw !== null && $raw !== '' && $raw !== '0') ? '1' : '0';

		case 'int':
			if (!is_scalar($raw) || !preg_match('/^-?\d+$/', trim((string)$raw))) {
				return null;
			}
			$value = (int)$raw;
			if ($field['min'] !== null && $value < $field['min']) {
				$value = $field['min'];
			}
			if ($field['max'] !== null && $value > $field['max']) {
				$value = $field['max'];
			}
			return (string)$value;

		case 'select':
			$value = (string)$raw;
			return array_key_exists($value, $field['options']) ? $value : null;

		case 'checklist':
			$chosen = is_array($raw) ? $raw : array();
			$valid = array_values(array_intersect(array_map('strval', $chosen), array_keys($field['options'])));
			return implode(',', $valid);

		case 'textarea':
		case 'password':
		case 'text':
		default:
			return is_scalar($raw) ? (string)$raw : null;
	}
}

/**
 * Validates and stores every field present in $input against the plugin's
 * schema. A field that fails validation keeps its previous value and its key
 * is returned in the 'errors' list; everything else is saved.
 */
function znote_plugin_settings_save(string $plugin, array $input): array {
	$plugin = znote_plugin_sanitize($plugin);
	$schema = znote_plugin_settings_schema($plugin);
	$errors = array();

	foreach ($schema as $key => $field) {
		// A checkbox that is off submits nothing at all - treat absence as
		// false for bool fields, but as "leave alone" for everything else.
		if (!array_key_exists($key, $input) && $field['type'] !== 'bool' && $field['type'] !== 'checklist') {
			continue;
		}

		$sanitized = znote_plugin_settings_sanitize_field($field, $input[$key] ?? null);
		if ($sanitized === null) {
			$errors[] = $key;
			continue;
		}

		setting_set(znote_plugin_settings_storage_key($plugin, $key), $sanitized);
	}

	return $errors;
}
