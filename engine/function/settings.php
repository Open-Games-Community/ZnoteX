<?php
/**
 * Settings stored in the database (znote_config), so the admin panel can
 * change them without config.php having to be writable.
 *
 * config.php stays the place for things an admin edits by hand (database
 * credentials, server engine, vocations). This is for things the panel writes.
 *
 * The whole table is read once per request and kept in memory - it is a
 * handful of short rows, so one query covers every lookup on the page.
 */

function znote_settings_all(bool $refresh = false): array {
	static $settings = null;

	if ($settings !== null && !$refresh) {
		return $settings;
	}

	$settings = array();

	$rows = mysql_select_multi("SELECT `key`, `value` FROM `znote_config`;");
	if (is_array($rows)) {
		foreach ($rows as $row) {
			$settings[(string)$row['key']] = (string)$row['value'];
		}
	}
	// A false result means the table is missing (migration not run yet).
	// Callers fall back to their defaults, so the site keeps working.

	return $settings;
}

function setting(string $key, ?string $default = null): ?string {
	$settings = znote_settings_all();
	return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function setting_set(string $key, string $value): bool {
	$k = mysql_znote_escape_string($key);
	$v = mysql_znote_escape_string($value);

	$ok = mysql_insert("
		INSERT INTO `znote_config` (`key`, `value`)
		VALUES ('{$k}', '{$v}')
		ON DUPLICATE KEY UPDATE `value` = '{$v}';
	");

	if ($ok !== false) {
		znote_settings_all(true); // drop the in-memory copy
		return true;
	}

	return false;
}

/**
 * Apply the settings saved from the admin panel over $config.
 *
 * config.php keeps every default; anything an admin has changed in
 * Admin Panel > Settings is stored as "config:<key>" and wins here. That way
 * updating config.php never loses a setting, and a key nobody has touched in
 * the panel keeps following the file.
 *
 * Booleans and integers are cast back, because znote_config stores strings.
 */
function znote_apply_settings(): void {
	global $config;

	$settings = znote_settings_all();
	if (!$settings) {
		return;
	}

	foreach ($settings as $key => $value) {
		if (strpos($key, 'config:') !== 0) {
			continue;
		}

		$name = substr($key, 7);
		if ($name === '' || !array_key_exists($name, $config)) {
			// Never invent a key: only override something config.php declares.
			continue;
		}

		if (is_bool($config[$name])) {
			$config[$name] = ($value !== '' && $value !== '0');
		} elseif (is_int($config[$name])) {
			$config[$name] = (int)$value;
		} else {
			$config[$name] = $value;
		}
	}
}

/**
 * Players-online record.
 *
 * Kept in znote_config rather than a table of its own: it is two integers, and
 * znote_config already exists for exactly this kind of thing.
 *
 * znote_record_update() is called from the pages that already know the current
 * online count, so this costs no extra query on a normal page load.
 */
function znote_record_get(): array {
	return array(
		'players' => (int)setting('record:players', '0'),
		'time'    => (int)setting('record:time', '0'),
	);
}

/**
 * Store $online as the new record if it beats the stored one.
 * Returns true when a new record was set.
 */
function znote_record_update(int $online): bool {
	if ($online <= 0) {
		return false;
	}

	$record = znote_record_get();
	if ($online <= $record['players']) {
		return false;
	}

	setting_set('record:players', (string)$online);
	setting_set('record:time', (string)time());

	return true;
}
