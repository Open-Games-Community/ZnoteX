<?php
/**
 * Layout (theme) system.
 *
 * A theme is a folder under layouts/. It owns the chrome and, optionally, the
 * markup of any page it wants to restyle. It never contains logic: the root
 * pages keep doing the queries, the theme only decides how the result looks.
 *
 *   layouts/<name>/
 *     theme.json          name, author, version, description
 *     screenshot.png      thumbnail shown in the admin panel
 *     shells/default.php  the page frame: <html>, header, {{content}}, footer
 *     views/<page>.php    the middle block of one root page
 *     pages/<name>.php    a page this theme adds, at page.php?p=<name>
 *     assets/css|js|img   the theme's own CSS/JS/images
 *
 * Anything a theme leaves out falls back to layouts/default/, so a theme can
 * be nothing more than a shell and a stylesheet and the whole site still works.
 *
 * How a request renders
 * ---------------------
 *   highscores.php
 *     theme_open()   -> opens an output buffer
 *     ... page logic and output, view('highscores') ...
 *     theme_close()  -> closes it, renders the shell
 *
 * Everything the page emits is captured, so the shell can wrap it even though
 * the root pages include the header and footer sequentially. That is also why
 * a page can pick a different shell halfway through: nothing has been sent yet.
 */

define('ZNOTE_THEME_FALLBACK', 'default');

// ---------------------------------------------------------------------------
// Where things live
// ---------------------------------------------------------------------------

/** Absolute path of the layouts/ directory. */
function theme_root(): string {
	// __DIR__ is engine/function, so two levels up is the project root.
	return dirname(__DIR__, 2) . '/layouts';
}

/** Name of a theme folder, sanitised. Never lets a name escape layouts/. */
function theme_sanitize(string $name): string {
	$name = strtolower(trim($name));
	return preg_match('/^[a-z0-9_-]{1,64}$/', $name) === 1 ? $name : '';
}

/**
 * The theme in use. Reads znote_config, falls back to 'default' when the
 * setting is missing, invalid, or points at a folder that is not there.
 */
function theme_active(): string {
	static $active = null;
	if ($active !== null) {
		return $active;
	}

	$name = theme_sanitize((string)(function_exists('setting') ? setting('layout', '') : ''));

	if ($name !== '' && is_dir(theme_root() . '/' . $name)) {
		$active = $name;
	} else {
		$active = ZNOTE_THEME_FALLBACK;
	}

	return $active;
}

/** Absolute path to a theme folder. */
function theme_path(?string $theme = null): string {
	return theme_root() . '/' . ($theme ?? theme_active());
}

/** Web path to a theme folder, relative to the site root. */
function theme_url(?string $theme = null): string {
	return 'layouts/' . ($theme ?? theme_active());
}

/**
 * The chain a file is looked up along: the theme, then its parent, then that
 * parent's parent, and finally the default theme.
 *
 * A theme becomes a child by naming another in its theme.json:
 *
 *   { "name": "Exodus Dark", "parent": "tibiacom_v1" }
 *
 * It then only has to ship what it changes. A stylesheet and two views on top
 * of a full parent is a complete theme, and a fix in the parent reaches every
 * child without touching them.
 *
 * Cycles and missing parents are ignored rather than fatal: a broken "parent"
 * degrades to the default theme instead of taking the site down.
 */
function theme_chain(?string $theme = null): array {
	static $chains = array();

	$theme = $theme ?? theme_active();
	if (isset($chains[$theme])) {
		return $chains[$theme];
	}

	$chain = array();
	$seen  = array();
	$next  = $theme;

	// 8 is far more nesting than anyone needs, and stops a cycle dead.
	for ($depth = 0; $next !== '' && $depth < 8; $depth++) {
		if (isset($seen[$next]) || !is_dir(theme_root() . '/' . $next)) {
			break;
		}
		$seen[$next] = true;
		$chain[]     = $next;

		$next = theme_sanitize((string)(theme_manifest($next)['parent'] ?? ''));
	}

	if (!in_array(ZNOTE_THEME_FALLBACK, $chain, true)) {
		$chain[] = ZNOTE_THEME_FALLBACK;
	}

	return $chains[$theme] = $chain;
}

/**
 * Resolve a file along the theme chain.
 * Returns the absolute path, or null when no theme in the chain has it.
 */
function theme_file(string $relative): ?string {
	$relative = ltrim($relative, '/');

	foreach (theme_chain() as $theme) {
		$candidate = theme_root() . '/' . $theme . '/' . $relative;
		if (is_file($candidate)) {
			return $candidate;
		}
	}

	return null;
}

/**
 * URL of a theme asset, with the same fallback as theme_file().
 * Use it for every stylesheet, script and image in a shell or view:
 *
 *   <link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
 */
function theme_asset(string $relative): string {
	$relative = ltrim($relative, '/');

	foreach (theme_chain() as $theme) {
		if (is_file(theme_root() . '/' . $theme . '/assets/' . $relative)) {
			return 'layouts/' . $theme . '/assets/' . $relative;
		}
	}

	// Return the active theme's path anyway so a missing file is visible as a
	// 404 in the browser console rather than silently resolving elsewhere.
	return theme_url() . '/assets/' . $relative;
}

// ---------------------------------------------------------------------------
// Theme registry (used by the admin panel)
// ---------------------------------------------------------------------------

/** Read theme.json, tolerating a missing or malformed file. */
function theme_manifest(string $name): array {
	// Read once per request: theme_list() and theme_options() both want it,
	// and with many themes installed that is a lot of redundant disk reads.
	static $cache = array();
	if (isset($cache[$name])) {
		return $cache[$name];
	}

	$defaults = array(
		'key'         => $name,
		'name'        => ucfirst(str_replace(array('-', '_'), ' ', $name)),
		'author'      => '',
		'version'     => '',
		'description' => '',
		'url'         => '',
	);

	$file = theme_root() . '/' . $name . '/theme.json';
	if (!is_file($file)) {
		return $cache[$name] = $defaults;
	}

	$data = json_decode((string)file_get_contents($file), true);

	return $cache[$name] = is_array($data)
		? array_merge($defaults, $data, array('key' => $name))
		: $defaults;
}

/**
 * Every installed theme, keyed by folder name.
 * Folders starting with "_" are templates/examples and are listed but flagged.
 */
function theme_list(): array {
	$themes = array();

	foreach (glob(theme_root() . '/*', GLOB_ONLYDIR) ?: array() as $dir) {
		$name = basename($dir);
		if (theme_sanitize(ltrim($name, '_')) === '') {
			continue;
		}

		$manifest = theme_manifest($name);
		$manifest['path']       = $dir;
		$manifest['is_example'] = ($name[0] === '_');
		$manifest['screenshot'] = is_file($dir . '/screenshot.png')
			? 'layouts/' . $name . '/screenshot.png'
			: null;

		$themes[$name] = $manifest;
	}

	ksort($themes);

	return $themes;
}

// ---------------------------------------------------------------------------
// Theme options
//
// A theme declares the settings it wants an admin to be able to change, in the
// "options" array of its theme.json:
//
//   "options": [
//     { "key": "discord_url", "label": "Discord invite", "type": "url" },
//     { "key": "whatsapp_text", "label": "Opening message", "type": "textarea",
//       "default": "Hello!", "help": "Shown in the chat bubble" }
//   ]
//
// The admin panel renders a form from that, and the theme reads a value with
// theme_option('discord_url'). Values live in znote_config under
// "theme:<theme>:<key>", so a theme keeps its settings when you switch away
// and back, and nothing is ever written into the theme's files.
//
// Supported types: text, url, textarea, checkbox.
// ---------------------------------------------------------------------------

/** The options a theme declares, normalised. Keyed by option key. */
function theme_options(?string $theme = null): array {
	$theme    = $theme ?? theme_active();
	$manifest = theme_manifest($theme);
	$declared = $manifest['options'] ?? null;

	if (!is_array($declared)) {
		return array();
	}

	$options = array();
	foreach ($declared as $option) {
		if (!is_array($option) || empty($option['key'])) {
			continue;
		}
		$key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$option['key']));
		if ($key === '') {
			continue;
		}

		$type = strtolower((string)($option['type'] ?? 'text'));
		if (!in_array($type, array('text', 'url', 'textarea', 'checkbox'), true)) {
			$type = 'text';
		}

		$options[$key] = array(
			'key'     => $key,
			'label'   => (string)($option['label'] ?? $key),
			'type'    => $type,
			'default' => (string)($option['default'] ?? ''),
			'help'    => (string)($option['help'] ?? ''),
		);
	}

	return $options;
}

/** Storage key for one theme option. */
function theme_option_key(string $theme, string $key): string {
	return 'theme:' . $theme . ':' . $key;
}

/**
 * The value of a theme option: what the admin saved, else the declared
 * default, else $fallback. Always a string - checkboxes give '1' or ''.
 */
function theme_option(string $key, string $fallback = '', ?string $theme = null): string {
	$theme   = $theme ?? theme_active();
	$options = theme_options($theme);

	if (!isset($options[$key])) {
		return $fallback;
	}

	$stored = function_exists('setting') ? setting(theme_option_key($theme, $key), null) : null;

	if ($stored !== null && $stored !== '') {
		return $stored;
	}

	$default = $options[$key]['default'];

	return ($default !== '') ? $default : $fallback;
}

/** True when the option holds something usable - handy for optional blocks. */
function theme_option_set(string $key, ?string $theme = null): bool {
	return trim(theme_option($key, '', $theme)) !== '';
}

// ---------------------------------------------------------------------------
// Theme repository
//
// Lists themes hosted somewhere else and installs them into layouts/.
//
// A theme is PHP that runs on this server, so installing one means running code
// written elsewhere. Three things are therefore enforced here rather than left
// to whoever calls this:
//
//   1. https only, and the host must appear in $config['layout_repository']
//      ['allowed_hosts']. A catalogue pointing anywhere else is ignored, so a
//      tampered catalogue still cannot make this download from a random site.
//   2. Every entry in the archive is checked before a single byte is written:
//      no absolute path, no "..", nothing outside the theme's own folder.
//   3. An installed theme is never silently replaced, and a failed swap rolls
//      back to the previous copy.
//
// The catalogue is a JSON array; layouts/README.md documents its shape.
// ---------------------------------------------------------------------------

function theme_repository_config(): array {
	global $config;
	$cfg = $config['layout_repository'] ?? array();

	return array(
		'enabled'       => !empty($cfg['enabled']),
		'index'         => trim((string)($cfg['index'] ?? '')),
		'allowed_hosts' => array_map('strtolower', (array)($cfg['allowed_hosts'] ?? array())),
		'cache_time'    => max(60, (int)($cfg['cache_time'] ?? 3600)),
		'max_size'      => max(1, (int)($cfg['max_size_mb'] ?? 64)) * 1024 * 1024,
	);
}

/** True when a URL is https and points at a host on the allow list. */
function theme_repository_url_allowed(string $url): bool {
	$cfg   = theme_repository_config();
	$parts = parse_url($url);

	if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
		return false;
	}

	return in_array(strtolower($parts['host']), $cfg['allowed_hosts'], true);
}

/**
 * GET a URL, with the guards above applied.
 * Returns the body, or true when written to $toFile, or false with $error set.
 */
function theme_repository_get(string $url, ?string $toFile = null, ?string &$error = null) {
	$cfg = theme_repository_config();

	if (!theme_repository_url_allowed($url)) {
		$error = 'Refused: the URL must be https and its host must be listed in $config[\'layout_repository\'][\'allowed_hosts\'].';
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

	// The CA bundle shipped with ZnoteX, so this works on Windows too.
	$ca = znote_cainfo();
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
		// Abort rather than let a wrong or hostile URL fill the disk.
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

/**
 * The catalogue, normalised and cached on disk so opening the page does not
 * hit the network every time.
 *
 * @return array{themes: array, error: string}
 */
function theme_repository_list(bool $refresh = false): array {
	$cfg = theme_repository_config();

	if (!$cfg['enabled'] || $cfg['index'] === '') {
		return array('themes' => array(), 'error' => '');
	}

	$cache = new Cache('engine/cache/layout_repository');
	$cache->useMemory(false);

	if (!$refresh && !$cache->hasExpired()) {
		$cached = $cache->load();
		if (is_array($cached)) {
			return array('themes' => $cached, 'error' => '', 'cached' => true);
		}
	}

	$error = null;
	$body  = theme_repository_get($cfg['index'], null, $error);

	if ($body === false) {
		return array('themes' => array(), 'error' => (string)$error);
	}

	$data = json_decode((string)$body, true);
	if (!is_array($data)) {
		return array('themes' => array(), 'error' => 'The catalogue is not valid JSON.');
	}

	// Accept both a bare array and {"themes": [...]}.
	if (isset($data['themes']) && is_array($data['themes'])) {
		$data = $data['themes'];
	}

	$themes = array();
	foreach ($data as $entry) {
		if (!is_array($entry)) {
			continue;
		}
		$key = theme_sanitize((string)($entry['key'] ?? ''));
		if ($key === '') {
			continue;
		}

		$download   = trim((string)($entry['download'] ?? ''));
		$screenshot = trim((string)($entry['screenshot'] ?? ''));

		$themes[$key] = array(
			'key'         => $key,
			'name'        => (string)($entry['name'] ?? ucfirst($key)),
			'author'      => (string)($entry['author'] ?? ''),
			'version'     => (string)($entry['version'] ?? ''),
			'description' => (string)($entry['description'] ?? ''),
			'url'         => (string)($entry['url'] ?? ''),
			'screenshot'  => theme_repository_url_allowed($screenshot) ? $screenshot : '',
			'download'    => $download,
			'installable' => theme_repository_url_allowed($download),
		);
	}

	ksort($themes);

	$cache->setContent($themes);
	$cache->save();

	return array('themes' => $themes, 'error' => '');
}

/** Recursive delete, used for staging and for rollback. */
function znote_rrmdir(string $dir): void {
	if (!is_dir($dir)) {
		return;
	}
	$items = @scandir($dir);
	if ($items === false) {
		return;
	}
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$path = $dir . '/' . $item;
		is_dir($path) ? znote_rrmdir($path) : @unlink($path);
	}
	@rmdir($dir);
}

/**
 * Open a theme archive and return its entries, whichever extension is around.
 *
 * ZipArchive is not enabled everywhere - UniServer ships it disabled, and some
 * shared hosts leave it out - but PharData reads zip archives too and phar is
 * compiled into PHP rather than being a loadable extension. Falling back to it
 * means installing a theme never asks the admin to edit php.ini.
 *
 * @return array{names: string[], read: callable, close: callable}|string
 *         The listing and two closures, or a message on failure.
 */
function theme_archive_open(string $zipPath) {

	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive();
		if ($zip->open($zipPath) !== true) {
			return 'The downloaded file is not a readable zip archive.';
		}

		$names = array();
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = $zip->getNameIndex($i);
			if ($name !== false && $name !== '') {
				$names[] = str_replace(chr(92), '/', $name);
			}
		}

		return array(
			'names' => $names,
			'read'  => static function (string $name) use ($zip) { return $zip->getStream($name); },
			'close' => static function () use ($zip) { $zip->close(); },
		);
	}

	if (!class_exists('PharData')) {
		return 'Neither the zip extension nor PharData is available, so archives cannot be unpacked.';
	}

	// Note on the two paths differing: PharData silently drops entries whose
	// path escapes the archive root, so with the fallback the "Refused" check
	// below never fires - the bad entry is simply not listed. The security
	// property is the same either way (nothing is written outside layouts/);
	// only the message differs. The check stays because it is the one that
	// speaks when ZipArchive is in use, and ZipArchive does hand those paths
	// over verbatim.
	//
	// PharData insists on a .zip/.tar extension; the download already has one.
	try {
		$phar = new PharData($zipPath);
	} catch (Throwable $e) {
		return 'The downloaded file is not a readable archive (' . $e->getMessage() . ').';
	}

	// PharData reports absolute paths, so the prefix has to be absolute too.
	$realPath = realpath($zipPath);
	$prefix   = 'phar://' . str_replace(chr(92), '/', $realPath !== false ? $realPath : $zipPath) . '/';
	$names  = array();

	try {
		foreach (new RecursiveIteratorIterator($phar, RecursiveIteratorIterator::SELF_FIRST) as $file) {
			$path = str_replace(chr(92), '/', $file->getPathname());
			$rel  = (strpos($path, $prefix) === 0) ? substr($path, strlen($prefix)) : $path;
			if ($rel === '' || $rel === false) {
				continue;
			}
			$names[] = $file->isDir() ? rtrim($rel, '/') . '/' : $rel;
		}
	} catch (Throwable $e) {
		return 'Could not read the archive (' . $e->getMessage() . ').';
	}

	return array(
		'names' => $names,
		'read'  => static function (string $name) use ($prefix) { return @fopen($prefix . $name, 'rb'); },
		'close' => static function () { /* nothing to close */ },
	);
}

/**
 * Download and unpack one catalogue entry into layouts/.
 *
 * Returns '' on success, 'already-installed' when it exists and $overwrite is
 * false, or a message explaining what stopped it.
 */
function theme_repository_install(string $key, bool $overwrite = false): string {
	$key = theme_sanitize($key);
	if ($key === '') {
		return 'Invalid theme name.';
	}
	if ($key === ZNOTE_THEME_FALLBACK) {
		return 'The default theme cannot be replaced from here.';
	}
	$catalogue = theme_repository_list();
	if (!isset($catalogue['themes'][$key])) {
		return 'That theme is not in the catalogue.';
	}

	$entry = $catalogue['themes'][$key];
	if (!$entry['installable']) {
		return 'Its download URL is not https, or its host is not on the allow list.';
	}

	$target = theme_root() . '/' . $key;
	if (is_dir($target) && !$overwrite) {
		return 'already-installed';
	}
	if (!is_writable(theme_root())) {
		return 'The layouts/ directory is not writable by PHP.';
	}

	$tmp = theme_root() . '/.' . $key . '.download.zip';
	$err = null;
	if (theme_repository_get($entry['download'], $tmp, $err) === false) {
		@unlink($tmp);
		return (string)$err;
	}

	$result = theme_archive_install($key, $tmp, $overwrite);
	@unlink($tmp);

	return $result;
}

/**
 * Validate and unpack a theme archive that is already on disk.
 *
 * Split out from the download so the checks below can be exercised on their
 * own, and so a zip that arrived some other way can be installed the same way.
 *
 * Returns '' on success, or a message explaining what stopped it.
 */
function theme_archive_install(string $key, string $zipPath, bool $overwrite = false): string {
	$key = theme_sanitize($key);
	if ($key === '' || $key === ZNOTE_THEME_FALLBACK) {
		return 'Invalid theme name.';
	}
	$target = theme_root() . '/' . $key;
	if (is_dir($target) && !$overwrite) {
		return 'already-installed';
	}

	$tmp = $zipPath;

	$archive = theme_archive_open($tmp);
	if (is_string($archive)) {
		return $archive;
	}

	// ---- validate every entry BEFORE writing anything --------------------
	$files  = array();
	$prefix = null;

	foreach ($archive['names'] as $name) {
		if ($name === '') {
			continue;
		}

		if ($name[0] === '/' || strpos($name, '../') !== false || strpos($name, ':') !== false) {
			$archive['close']();
			return 'Refused: the archive contains a path that would write outside layouts/ (' . $name . ').';
		}

		$files[] = $name;

		// GitHub's "Download ZIP" wraps everything in one top folder; strip it.
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

	$hasShell = false;
	foreach ($files as $name) {
		if (substr($name, $strip) === 'shells/default.php') {
			$hasShell = true;
			break;
		}
	}
	if (!$hasShell) {
		$archive['close']();
		return 'Refused: no shells/default.php in the archive, so this is not a usable theme.';
	}

	// ---- unpack into staging --------------------------------------------
	$staging = theme_root() . '/.' . $key . '.staging';
	znote_rrmdir($staging);
	if (!@mkdir($staging, 0775, true)) {
		$archive['close']();
		return 'Could not create a staging directory inside layouts/.';
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

	if (!is_file($staging . '/shells/default.php')) {
		znote_rrmdir($staging);
		return 'The archive unpacked without a shells/default.php. Nothing was installed.';
	}

	// ---- swap in, with rollback -----------------------------------------
	if (is_dir($target)) {
		$backup = theme_root() . '/.' . $key . '.previous';
		znote_rrmdir($backup);

		if (!@rename($target, $backup)) {
			znote_rrmdir($staging);
			return 'Could not move the existing theme aside. Check permissions on layouts/' . $key . '.';
		}
		if (!@rename($staging, $target)) {
			@rename($backup, $target);
			znote_rrmdir($staging);
			return 'Could not put the new theme in place. The previous one was restored.';
		}
		znote_rrmdir($backup);
	} elseif (!@rename($staging, $target)) {
		znote_rrmdir($staging);
		return 'Could not create layouts/' . $key . '.';
	}

	return '';
}

/** Delete an installed theme. Returns '' on success. */
function theme_uninstall(string $key): string {
	$key = theme_sanitize($key);
	if ($key === '' || $key === ZNOTE_THEME_FALLBACK) {
		return 'That theme cannot be removed.';
	}
	if ($key === theme_active()) {
		return 'That theme is active. Switch to another one first.';
	}

	$dir = theme_root() . '/' . $key;
	if (!is_dir($dir)) {
		return 'That theme is not installed.';
	}

	znote_rrmdir($dir);

	return is_dir($dir) ? 'Could not remove layouts/' . $key . '. Check permissions.' : '';
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

/**
 * Which shell wraps this page. A view or a page can change it before the
 * footer runs; after that it is too late and the call is ignored.
 */
function theme_shell(?string $name = null): string {
	static $shell = 'default';

	if ($name !== null) {
		$clean = theme_sanitize($name);
		if ($clean !== '') {
			$shell = $clean;
		}
	}

	return $shell;
}

/** Start capturing page output. Called by the root pages, via theme_open(). */
function theme_open(): void {
	if (theme_is_open()) {
		return;
	}

	theme_is_open(true);
	ob_start();

	// A page that redirects or die()s between the header and the footer would
	// otherwise leave the buffer unflushed and show a blank page.
	register_shutdown_function('theme_close');
}

/** Internal open/closed flag. */
function theme_is_open(?bool $set = null): bool {
	static $open = false;
	if ($set !== null) {
		$open = $set;
	}
	return $open;
}

/**
 * Stop capturing, render the shell around what was captured.
 * Called by the root pages, and again on shutdown as a safety net.
 */
function theme_close(): void {
	if (!theme_is_open()) {
		return;
	}
	theme_is_open(false);

	$content = ob_get_clean();
	if ($content === false) {
		$content = '';
	}

	$shell = theme_file('shells/' . theme_shell() . '.php')
		?? theme_file('shells/default.php');

	if ($shell === null) {
		// No shell anywhere: emit the content bare rather than a blank page.
		echo $content;
		return;
	}

	// $content is what the shell echoes where the page body goes.
	theme_content($content);

	// Plugins may add markup to the head and the end of the body. Done here by
	// rewriting the shell's output rather than by asking every theme to call a
	// function, so a plugin works with themes written before it existed.
	$injectHead = function_exists('znote_hook_collect') ? znote_hook_collect('page.head') : '';
	$injectFoot = function_exists('znote_hook_collect') ? znote_hook_collect('page.footer') : '';

	// A shell is included from inside this function, so without this it would
	// see none of the page's variables - unlike views and widgets, which do.
	extract($GLOBALS, EXTR_SKIP);

	if ($injectHead === '' && $injectFoot === '') {
		include $shell;
		return;
	}

	ob_start();
	include $shell;
	$page = (string)ob_get_clean();

	if ($injectHead !== '') {
		$page = preg_replace('#</head>#i', $injectHead . '</head>', $page, 1) ?? $page;
	}
	if ($injectFoot !== '') {
		$page = preg_replace('#</body>#i', $injectFoot . '</body>', $page, 1) ?? $page;
	}

	echo $page;
}

/**
 * Inside a shell, prints the page body.
 * (Called with an argument by theme_close() to store it - shells call it with
 * no argument.)
 */
function theme_content(?string $set = null): void {
	static $content = '';

	if ($set !== null) {
		$content = $set;
		return;
	}

	echo $content;
}

/**
 * Render the view for a page: the markup that goes between the header and the
 * footer. Root pages call this instead of holding their own HTML.
 *
 *   view('highscores');
 *
 * Looks for layouts/<active>/views/highscores.php, then the default theme's.
 * Variables in scope where view() was called are visible inside the view, plus
 * anything passed in $vars.
 */
function view(string $name, array $vars = array()): void {
	$clean = theme_sanitize($name);
	if ($clean === '') {
		return;
	}

	$file = theme_file('views/' . $clean . '.php');
	if ($file === null) {
		error_log("[ZnoteX theme] no view found for '{$clean}' in layouts/" . theme_active() . '/views/');
		return;
	}

	// The caller's variables, so a view can use $players, $config and friends
	// exactly as the page's own inline HTML used to.
	extract($GLOBALS, EXTR_SKIP);
	extract($vars, EXTR_OVERWRITE);

	include $file;
}

/**
 * Include a file from the theme chain, from inside a shell, view or page.
 *
 * Always use this rather than theme_path() . '/parts/x.php': theme_path()
 * points at the ACTIVE theme only, so a child theme that does not ship the
 * file would include nothing and the page would render without its frame.
 * theme_include() walks child -> parent -> default like everything else.
 *
 *   <?php theme_include('parts/head.php'); ?>
 *   <?php theme_include('parts/box.php', ['title' => $title]); ?>
 *
 * Returns false when no theme in the chain has the file.
 */
function theme_include(string $relative, array $vars = array()): bool {
	$file = theme_file($relative);
	if ($file === null) {
		error_log('[ZnoteX theme] no ' . $relative . ' anywhere in the chain: ' . implode(' -> ', theme_chain()));
		return false;
	}

	extract($GLOBALS, EXTR_SKIP);
	// Anything the caller needs to hand over, since an include from inside a
	// function does not see the caller's local variables.
	extract($vars, EXTR_OVERWRITE);

	include $file;

	return true;
}

/**
 * Optional helpers a shell may call. A theme that writes its own menu in
 * header markup simply never calls them, and they cost nothing.
 */
function theme_menu(): void {
	$file = theme_file('menu.php');
	if ($file !== null) {
		extract($GLOBALS, EXTR_SKIP);
		include $file;
	}
}

function theme_sidebar(): void {
	$file = theme_file('aside.php');
	if ($file !== null) {
		extract($GLOBALS, EXTR_SKIP);
		include $file;
	}
}

/** One widget by name, from the theme or the default theme. */
function widget(string $name): void {
	$clean = theme_sanitize($name);
	if ($clean === '') {
		return;
	}

	$file = theme_file('widgets/' . $clean . '.php');
	if ($file !== null) {
		extract($GLOBALS, EXTR_SKIP);
		include $file;
	}
}

// ---------------------------------------------------------------------------
// Small conveniences for shells
// ---------------------------------------------------------------------------

function theme_title(): string {
	global $config;
	return htmlspecialchars((string)($config['site_title'] ?? 'ZnoteX'), ENT_QUOTES, 'UTF-8');
}

/** e.g. "page_highscores" - lets a theme restyle one page from CSS alone. */
function theme_body_class(): string {
	global $page_filename;

	$classes = array('theme-' . theme_active());
	if (!empty($page_filename)) {
		$classes[] = 'page_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$page_filename);
	}

	return htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8');
}
