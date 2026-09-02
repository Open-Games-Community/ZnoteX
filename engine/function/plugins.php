<?php
/**
 * Plugins.
 *
 * A plugin is a folder under plugins/. It can add public pages, admin modules,
 * database tables and behaviour, without a single edit to ZnoteX itself - which
 * is the whole point: someone extending the site should never have to fork it,
 * and should not lose their work on the next update.
 *
 *   plugins/<name>/
 *     plugin.json        name, version, author, description   [required]
 *     plugin.php         registers hooks                      [optional]
 *     pages/<page>.php   public page at page.php?plugin=<name>&p=<page>
 *     admin/<mod>.php    admin module, listed like the built-in ones
 *     install.sql        run once when the plugin is enabled
 *     assets/            anything it needs to serve
 *
 * Enabled state lives in znote_config as "plugin:<name>:enabled", so it
 * survives an update and needs no table of its own.
 *
 * See plugins/README.md for the full contract and the hook list.
 */

define('ZNOTE_PLUGIN_DIR', dirname(__DIR__, 2) . '/plugins');

// ---------------------------------------------------------------------------
// Hooks
//
// A hook is a named point where plugins may run. Three shapes:
//
//   znote_hook('shop.purchased', $data)      - notify, return value ignored
//   $html = znote_hook_collect('page.head')  - gather markup from every plugin
//   $n = znote_hook_filter('shop.price', $n) - pass a value through, changed
//
// A hook that throws is caught and logged: one broken plugin must not take the
// site down. That is the difference between an extension point and a landmine.
// ---------------------------------------------------------------------------

function znote_hook_register(string $hook, callable $callback, int $priority = 10): void {
	$GLOBALS['znote_hooks'][$hook][] = array('fn' => $callback, 'priority' => $priority);
}

/** Callbacks for one hook, lowest priority first. */
function znote_hook_callbacks(string $hook): array {
	$list = $GLOBALS['znote_hooks'][$hook] ?? array();
	if (!$list) {
		return array();
	}

	usort($list, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

	return array_column($list, 'fn');
}

/** Fire a hook. Return values are ignored; use it to notify. */
function znote_hook(string $hook, array $data = array()): void {
	foreach (znote_hook_callbacks($hook) as $callback) {
		try {
			$callback($data);
		} catch (Throwable $e) {
			error_log('[ZnoteX plugin] hook ' . $hook . ' failed: ' . $e->getMessage());
		}
	}
}

/** Fire a hook and concatenate what the callbacks return. For markup. */
function znote_hook_collect(string $hook, array $data = array()): string {
	$out = '';

	foreach (znote_hook_callbacks($hook) as $callback) {
		try {
			$out .= (string)$callback($data);
		} catch (Throwable $e) {
			error_log('[ZnoteX plugin] hook ' . $hook . ' failed: ' . $e->getMessage());
		}
	}

	return $out;
}

/**
 * Pass a value through every callback and return what comes back.
 *
 * This is how a plugin changes something rather than merely reacting to it:
 * a discount on a shop price, a modified welcome message. Each callback
 * receives the current value and $data, and returns the new value; one that
 * throws is skipped and the value it was given survives untouched.
 */
function znote_hook_filter(string $hook, $value, array $data = array()) {
	foreach (znote_hook_callbacks($hook) as $callback) {
		try {
			$value = $callback($value, $data);
		} catch (Throwable $e) {
			error_log('[ZnoteX plugin] filter ' . $hook . ' failed: ' . $e->getMessage());
		}
	}

	return $value;
}

/**
 * Fire a hook that can veto. Any callback returning false stops the action.
 * Used where a plugin must be able to say "no" - a purchase, a registration.
 */
function znote_hook_allows(string $hook, array $data = array()): bool {
	foreach (znote_hook_callbacks($hook) as $callback) {
		try {
			if ($callback($data) === false) {
				return false;
			}
		} catch (Throwable $e) {
			error_log('[ZnoteX plugin] hook ' . $hook . ' failed: ' . $e->getMessage());
		}
	}

	return true;
}

// ---------------------------------------------------------------------------
// Registry
// ---------------------------------------------------------------------------

function znote_plugin_sanitize(string $name): string {
	$name = strtolower(trim($name));
	return preg_match('/^[a-z0-9_-]{1,64}$/', $name) === 1 ? $name : '';
}

/** Read plugin.json, tolerating a missing or malformed file. */
function znote_plugin_manifest(string $name): array {
	$defaults = array(
		'key'         => $name,
		'name'        => ucwords(str_replace(array('-', '_'), ' ', $name)),
		'version'     => '',
		'author'      => '',
		'description' => '',
		'url'         => '',
		'requires'    => '',
	);

	$file = ZNOTE_PLUGIN_DIR . '/' . $name . '/plugin.json';
	if (!is_file($file)) {
		return $defaults;
	}

	$data = json_decode((string)file_get_contents($file), true);

	return is_array($data) ? array_merge($defaults, $data, array('key' => $name)) : $defaults;
}

/** Every plugin on disk, keyed by folder name. */
function znote_plugins(bool $refresh = false): array {
	static $plugins = null;
	if ($plugins !== null && !$refresh) {
		return $plugins;
	}

	$plugins = array();

	foreach (glob(ZNOTE_PLUGIN_DIR . '/*', GLOB_ONLYDIR) ?: array() as $dir) {
		$name = basename($dir);
		if (znote_plugin_sanitize($name) === '' || $name[0] === '_') {
			continue;
		}

		$manifest = znote_plugin_manifest($name);
		$manifest['path']      = $dir;
		$manifest['enabled']   = znote_plugin_enabled($name);
		$manifest['installed_version'] = znote_plugin_installed_version($name);
		$manifest['installed'] = ($manifest['installed_version'] !== '');
		$manifest['update']    = znote_plugin_update_available($name, (string)$manifest['version']);
		$manifest['page_list'] = array_map(
			static fn(string $f): string => basename($f, '.php'),
			glob($dir . '/pages/*.php') ?: array()
		);
		$manifest['pages']   = count($manifest['page_list']);
		$manifest['admin']   = count(glob($dir . '/admin/*.php') ?: array());
		$manifest['sql']     = is_file($dir . '/install.sql');

		$plugins[$name] = $manifest;
	}

	ksort($plugins);

	return $plugins;
}

function znote_plugin_enabled(string $name): bool {
	return function_exists('setting') && setting('plugin:' . $name . ':enabled', '0') === '1';
}

/**
 * The version that was installed, or '' if this plugin has never been installed.
 *
 * This is what separates "a folder someone uploaded" from "a plugin whose
 * tables exist". It is the version recorded at install time, not the one in
 * plugin.json - comparing the two is how an update is noticed.
 */
function znote_plugin_installed_version(string $name): string {
	return function_exists('setting') ? (string)setting('plugin:' . $name . ':version', '') : '';
}

/** True when the folder holds a newer version than the one installed. */
function znote_plugin_update_available(string $name, string $folderVersion): bool {
	$installed = znote_plugin_installed_version($name);

	if ($installed === '' || $folderVersion === '') {
		return false;
	}

	return version_compare($folderVersion, $installed, '>');
}

/**
 * Install or update a plugin: run its install.sql and record its version.
 *
 * The same call does both. install.sql is required to be idempotent, so
 * re-running it on an update creates whatever tables the new version has grown
 * and leaves the existing ones alone. Returns '' on success, or the error.
 */
function znote_plugin_install(string $name): string {
	$manifest = znote_plugin_manifest($name);
	$error    = znote_plugin_install_sql($name);

	if ($error !== '') {
		return $error;
	}

	// Recorded last: a failed install.sql must not leave the plugin looking
	// installed, or the admin loses the button that would retry it.
	setting_set('plugin:' . $name . ':version', (string)($manifest['version'] ?: '0'));

	return '';
}

/** Forget that a plugin was installed. Its tables are deliberately left alone. */
function znote_plugin_uninstall(string $name): void {
	znote_plugin_set_enabled($name, false);
	setting_set('plugin:' . $name . ':version', '');
}

function znote_plugin_set_enabled(string $name, bool $enabled): bool {
	return setting_set('plugin:' . $name . ':enabled', $enabled ? '1' : '0');
}

/**
 * Run a plugin's install.sql, once.
 *
 * Statements must be idempotent - CREATE TABLE IF NOT EXISTS and the like -
 * because a plugin can be disabled and re-enabled, and we do not track which
 * statements already ran. A plugin that needs real migrations should ship them
 * under SQL/ and say so in its description.
 */
function znote_plugin_install_sql(string $name): string {
	$file = ZNOTE_PLUGIN_DIR . '/' . $name . '/install.sql';
	if (!is_file($file)) {
		return '';
	}

	$sql = preg_replace('/^--.*$/m', '', (string)file_get_contents($file));
	$failed = array();

	foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
		if ($statement === '') {
			continue;
		}
		if (mysql_insert($statement) === false && mysql_update($statement) === false) {
			$failed[] = substr($statement, 0, 60);
		}
	}

	return $failed ? count($failed) . ' statement(s) failed, first: ' . $failed[0] : '';
}

// ---------------------------------------------------------------------------
// Loading
// ---------------------------------------------------------------------------

/**
 * Load every enabled plugin's plugin.php.
 *
 * Called from engine/init.php once the database and settings are up, because a
 * plugin may want either. A plugin that throws on load is skipped and logged
 * rather than allowed to break the request.
 */
function znote_plugins_load(): void {
	foreach (znote_plugins() as $name => $plugin) {
		// Enabled is not enough: a plugin that was never installed has no
		// tables, and loading it would only produce SQL errors on every page.
		if (!$plugin['enabled'] || !$plugin['installed']) {
			continue;
		}

		$file = $plugin['path'] . '/plugin.php';
		if (!is_file($file)) {
			continue;
		}

		try {
			require_once $file;
		} catch (Throwable $e) {
			error_log('[ZnoteX plugin] ' . $name . ' failed to load: ' . $e->getMessage());
		}
	}

	znote_hook('plugins.loaded');
}

/** Installed and enabled. What every entry point actually checks. */
function znote_plugin_active(string $name): bool {
	return znote_plugin_enabled($name) && znote_plugin_installed_version($name) !== '';
}

/** Admin modules contributed by active plugins, as key => file path. */
function znote_plugin_admin_modules(): array {
	$modules = array();

	foreach (znote_plugins() as $name => $plugin) {
		if (!$plugin['enabled'] || !$plugin['installed']) {
			continue;
		}

		foreach (glob($plugin['path'] . '/admin/*.php') ?: array() as $file) {
			$module = basename($file, '.php');
			if ($module === '' || $module[0] === '_') {
				continue;
			}
			// Namespaced so a plugin cannot shadow a built-in module.
			$modules[$name . '__' . $module] = $file;
		}
	}

	return $modules;
}

/** Resolve a plugin page, or null. */
function znote_plugin_page(string $plugin, string $page): ?string {
	$plugin = znote_plugin_sanitize($plugin);
	$page   = znote_plugin_sanitize($page);

	if ($plugin === '' || $page === '' || !znote_plugin_active($plugin)) {
		return null;
	}

	$file = ZNOTE_PLUGIN_DIR . '/' . $plugin . '/pages/' . $page . '.php';

	return is_file($file) ? $file : null;
}

/** URL of a plugin's public page. */
function znote_plugin_url(string $plugin, string $page): string {
	return 'page.php?' . http_build_query(array('plugin' => $plugin, 'p' => $page));
}

/** URL of a file in a plugin's assets/ folder. */
function znote_plugin_asset(string $plugin, string $file): string {
	return 'plugins/' . znote_plugin_sanitize($plugin) . '/assets/' . ltrim($file, '/');
}
