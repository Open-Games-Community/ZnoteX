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

function plugin_repository_config(): array {
	global $config;
	$cfg = $config['plugin_repository'] ?? array();

	return array(
		'enabled'       => !empty($cfg['enabled']),
		'index'         => trim((string)($cfg['index'] ?? '')),
		'allowed_hosts' => array_map('strtolower', (array)($cfg['allowed_hosts'] ?? array())),
		'cache_time'    => max(60, (int)($cfg['cache_time'] ?? 3600)),
		'max_size'      => max(1, (int)($cfg['max_size_mb'] ?? 64)) * 1024 * 1024,
	);
}

function plugin_repository_cache_path(): string {
	return 'engine/cache/plugin_repository' . Cache::EXT;
}

function plugin_repository_clear_cache(): bool {
	$file = plugin_repository_cache_path();

	if (!is_file($file)) {
		return true;
	}

	return @unlink($file);
}

function plugin_repository_url_allowed(string $url): bool {
	$cfg   = plugin_repository_config();
	$parts = parse_url($url);

	if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
		return false;
	}

	return in_array(strtolower($parts['host']), $cfg['allowed_hosts'], true);
}

function plugin_repository_get(string $url, ?string $toFile = null, ?string &$error = null) {
	$cfg = plugin_repository_config();

	if (!plugin_repository_url_allowed($url)) {
		$error = 'Refused: the URL must be https and its host must be listed in $config[\'plugin_repository\'][\'allowed_hosts\'].';
		return false;
	}
	if (!function_exists('curl_init')) {
		$error = 'The curl extension is not loaded.';
		return false;
	}

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, $toFile === null);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
	curl_setopt($ch, CURLOPT_TIMEOUT, 120);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_USERAGENT, 'ZnoteX/' . ($GLOBALS['version'] ?? '2.0.0'));

	$ca = function_exists('znote_cainfo') ? znote_cainfo() : '';
	if ($ca !== '') {
		curl_setopt($ch, CURLOPT_CAINFO, $ca);
	}

	$handle = null;
	if ($toFile !== null) {
		$handle = @fopen($toFile, 'wb');
		if ($handle === false) {
			$error = 'Cannot write to ' . $toFile;
			curl_close($ch);
			return false;
		}
		curl_setopt($ch, CURLOPT_FILE, $handle);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($res, $dlTotal, $dlNow) use ($cfg) {
			return ($dlNow > $cfg['max_size'] || $dlTotal > $cfg['max_size']) ? 1 : 0;
		});
	}

	$body   = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$errNo  = curl_errno($ch);
	$errStr = curl_error($ch);
	curl_close($ch);

	if ($handle !== null) {
		fclose($handle);
	}

	if ($errNo !== 0) {
		$error = ($errNo === 42 || $errNo === 23)
			? 'Download aborted: the file is larger than the configured limit.'
			: 'Download failed (curl ' . $errNo . '): ' . $errStr;
		return false;
	}
	if ($status < 200 || $status >= 300) {
		$error = 'The server answered HTTP ' . $status . '.';
		return false;
	}

	return $toFile === null ? $body : true;
}

function plugin_repository_notes($value): string {
	return function_exists('theme_repository_notes')
		? theme_repository_notes($value)
		: (is_string($value) || is_numeric($value) ? trim((string)$value) : '');
}

function plugin_repository_list(bool $refresh = false): array {
	$cfg = plugin_repository_config();

	if (!$cfg['enabled'] || $cfg['index'] === '') {
		return array('plugins' => array(), 'error' => '');
	}

	$cache = new Cache('engine/cache/plugin_repository');
	$cache->useMemory(false);

	if (!$refresh && !$cache->hasExpired()) {
		$cached = $cache->load();
		if (is_array($cached)) {
			return array('plugins' => $cached, 'error' => '', 'cached' => true);
		}
	}

	$error = null;
	$body  = plugin_repository_get($cfg['index'], null, $error);

	if ($body === false) {
		return array('plugins' => array(), 'error' => (string)$error);
	}

	$data = json_decode((string)$body, true);
	if (!is_array($data)) {
		return array('plugins' => array(), 'error' => 'The catalogue is not valid JSON.');
	}

	if (isset($data['plugins']) && is_array($data['plugins'])) {
		$data = $data['plugins'];
	}

	$plugins = array();
	foreach ($data as $entry) {
		if (!is_array($entry)) {
			continue;
		}
		$key = znote_plugin_sanitize((string)($entry['key'] ?? ''));
		if ($key === '') {
			continue;
		}

		$download   = trim((string)($entry['download'] ?? ''));
		$screenshot = trim((string)($entry['screenshot'] ?? ''));
		$changelog  = '';
		foreach (array('changelog', 'changes', 'release_notes', 'update') as $notesKey) {
			if (array_key_exists($notesKey, $entry)) {
				$changelog = plugin_repository_notes($entry[$notesKey]);
				break;
			}
		}

		$plugins[$key] = array(
			'key'         => $key,
			'name'        => (string)($entry['name'] ?? ucfirst($key)),
			'author'      => (string)($entry['author'] ?? ''),
			'version'     => (string)($entry['version'] ?? ''),
			'description' => (string)($entry['description'] ?? ''),
			'changelog'   => $changelog,
			'url'         => (string)($entry['url'] ?? ''),
			'screenshot'  => plugin_repository_url_allowed($screenshot) ? $screenshot : '',
			'download'    => $download,
			'installable' => plugin_repository_url_allowed($download),
		);
	}

	ksort($plugins);

	$cache->setContent($plugins);
	$cache->save();

	return array('plugins' => $plugins, 'error' => '');
}

function plugin_repository_install(string $key, bool $overwrite = false): string {
	$key = znote_plugin_sanitize($key);
	if ($key === '') {
		return 'Invalid plugin name.';
	}

	$catalogue = plugin_repository_list();
	if (!isset($catalogue['plugins'][$key])) {
		return 'That plugin is not in the catalogue.';
	}

	$entry = $catalogue['plugins'][$key];
	if (!$entry['installable']) {
		return 'Its download URL is not https, or its host is not on the allow list.';
	}

	$target = ZNOTE_PLUGIN_DIR . '/' . $key;
	if (is_dir($target) && !$overwrite) {
		return 'already-installed';
	}
	if (!is_writable(ZNOTE_PLUGIN_DIR)) {
		return 'The plugins/ directory is not writable by PHP.';
	}

	$tmp = ZNOTE_PLUGIN_DIR . '/.' . $key . '.download.zip';
	$err = null;
	if (plugin_repository_get($entry['download'], $tmp, $err) === false) {
		@unlink($tmp);
		return (string)$err;
	}

	$result = plugin_archive_install($key, $tmp, $overwrite);
	@unlink($tmp);

	return $result;
}

function plugin_archive_install(string $key, string $zipPath, bool $overwrite = false): string {
	$key = znote_plugin_sanitize($key);
	if ($key === '') {
		return 'Invalid plugin name.';
	}
	$target = ZNOTE_PLUGIN_DIR . '/' . $key;
	if (is_dir($target) && !$overwrite) {
		return 'already-installed';
	}
	if (!function_exists('theme_archive_open')) {
		return 'Archive support is unavailable (engine/function/theme.php not loaded).';
	}

	$archive = theme_archive_open($zipPath);
	if (is_string($archive)) {
		return $archive;
	}

	$files  = array();
	$prefix = null;

	foreach ($archive['names'] as $name) {
		if ($name === '') {
			continue;
		}
		if ($name[0] === '/' || strpos($name, '../') !== false || strpos($name, ':') !== false) {
			$archive['close']();
			return 'Refused: the archive contains a path that would write outside plugins/ (' . $name . ').';
		}

		$files[] = $name;

		$top = explode('/', $name)[0];
		if ($prefix === null) {
			$prefix = $top;
		} elseif ($prefix !== $top) {
			$prefix = '';
		}
	}

	if (!$files) {
		$archive['close']();
		return 'The archive is empty.';
	}

	$strip = ($prefix !== null && $prefix !== '') ? strlen($prefix) + 1 : 0;

	$hasManifest = false;
	foreach ($files as $name) {
		if (substr($name, $strip) === 'plugin.json') {
			$hasManifest = true;
			break;
		}
	}
	if (!$hasManifest) {
		$archive['close']();
		return 'Refused: no plugin.json in the archive, so this is not a usable plugin.';
	}

	$staging = ZNOTE_PLUGIN_DIR . '/.' . $key . '.staging';
	znote_rrmdir($staging);
	if (!@mkdir($staging, 0775, true)) {
		$archive['close']();
		return 'Could not create a staging directory inside plugins/.';
	}

	foreach ($files as $name) {
		$relative = substr($name, $strip);
		if ($relative === '' || $relative === false) {
			continue;
		}

		$dest = $staging . '/' . $relative;

		if (substr($name, -1) === '/') {
			@mkdir($dest, 0775, true);
			continue;
		}

		$dir = dirname($dest);
		if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
			continue;
		}

		$stream = $archive['read']($name);
		if ($stream === false) {
			continue;
		}
		$out = @fopen($dest, 'wb');
		if ($out !== false) {
			stream_copy_to_stream($stream, $out);
			fclose($out);
		}
		fclose($stream);
	}

	$archive['close']();

	if (!is_file($staging . '/plugin.json')) {
		znote_rrmdir($staging);
		return 'The archive unpacked without a plugin.json. Nothing was installed.';
	}

	if (is_dir($target)) {
		$backup = ZNOTE_PLUGIN_DIR . '/.' . $key . '.previous';
		znote_rrmdir($backup);

		if (!@rename($target, $backup)) {
			znote_rrmdir($staging);
			return 'Could not move the existing plugin aside. Check permissions on plugins/' . $key . '.';
		}
		if (!@rename($staging, $target)) {
			@rename($backup, $target);
			znote_rrmdir($staging);
			return 'Could not put the new plugin in place. The previous one was restored.';
		}
		znote_rrmdir($backup);
	} elseif (!@rename($staging, $target)) {
		znote_rrmdir($staging);
		return 'Could not create plugins/' . $key . '.';
	}

	return '';
}
