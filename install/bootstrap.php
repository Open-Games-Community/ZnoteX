<?php
/**
 * ZnoteX installer - shared runtime.
 *
 * The installer deliberately does NOT boot engine/init.php: at step 1 there is
 * no database, no config, possibly no schema. It talks to mysqli directly and
 * only loads the engine once there is something to load.
 *
 * It is also the most dangerous file in the project - it writes configuration
 * and grants admin rights - so it refuses to run once the site is installed.
 * See install_is_locked().
 */

if (!defined('ZNOTE_INSTALL')) {
	http_response_code(403);
	die('Direct access denied.');
}

/**
 * Since PHP 8.1 mysqli throws on error instead of returning false, and "@" does
 * not suppress an exception. The installer queries tables that are expected to
 * be missing - that is the whole point of the checks - so it asks for the old
 * behaviour explicitly rather than wrapping every call in try/catch.
 */
mysqli_report(MYSQLI_REPORT_OFF);

const INSTALL_LOCK  = 'installed.lock';
const INSTALL_STEPS = array(
	1 => 'Requirements',
	2 => 'Database',
	3 => 'Server',
	4 => 'Schema',
	5 => 'Administrator',
	6 => 'Finish',
);

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------
function install_root(): string {
	return dirname(__DIR__);
}

function install_lock_file(): string {
	return __DIR__ . '/' . INSTALL_LOCK;
}

function install_config_file(): string {
	return install_root() . '/config.local.php';
}

// ---------------------------------------------------------------------------
// The lock
// ---------------------------------------------------------------------------

/**
 * Why two conditions rather than one:
 *
 * The lock file alone is not enough - someone restoring a backup or unpacking
 * a fresh copy over an installed site would drop it and the installer would
 * happily reset the admin account. The database is the second opinion: if the
 * znote table already holds a row, this site is installed regardless of what
 * the filesystem says.
 *
 * Returns '' when the installer may run, or a reason when it may not.
 */
function install_locked_reason(): string {
	if (is_file(install_lock_file())) {
		return 'This site is already installed. Delete <code>install/' . INSTALL_LOCK
			. '</code> if you really mean to run the installer again.';
	}

	// A wizard already in progress must not be locked out by its own work:
	// step 4 creates the znote table, which is exactly what the check below
	// looks for. Step 2 has already warned if the database was not empty.
	if (!empty($_SESSION['install']) || install_max_step() > 1) {
		return '';
	}

	$config = install_saved_config();
	if (!$config) {
		return '';
	}

	$link = @new mysqli($config['sqlHost'], $config['sqlUser'], $config['sqlPassword'], $config['sqlDatabase']);
	if ($link->connect_errno) {
		return '';
	}

	$result = @$link->query('SELECT `id` FROM `znote` LIMIT 1');
	$rows   = ($result !== false) ? $result->num_rows : 0;
	$link->close();

	if ($rows > 0) {
		return 'The database already contains a ZnoteX installation. The installer will not'
			. ' overwrite it. Delete the <code>znote</code> table first if that is really what you want.';
	}

	return '';
}

// ---------------------------------------------------------------------------
// Wizard state
//
// Kept in the session, so a refresh does not lose the credentials typed two
// steps ago. Nothing is written to disk until the final step.
// ---------------------------------------------------------------------------
function install_state(?array $merge = null): array {
	if (!isset($_SESSION['install'])) {
		$_SESSION['install'] = array();
	}

	if ($merge !== null) {
		$_SESSION['install'] = array_merge($_SESSION['install'], $merge);
	}

	return $_SESSION['install'];
}

function install_get(string $key, $default = '') {
	$state = install_state();
	return $state[$key] ?? $default;
}

function install_reset(): void {
	unset($_SESSION['install']);
}

/** Highest step reached, so someone cannot skip ahead by editing the URL. */
function install_max_step(?int $reached = null): int {
	if ($reached !== null && $reached > (int)($_SESSION['install_max'] ?? 1)) {
		$_SESSION['install_max'] = $reached;
	}

	return (int)($_SESSION['install_max'] ?? 1);
}

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

/** Read config.local.php if it exists, else fall back to config.php values. */
function install_saved_config(): array {
	$keys = array('sqlHost', 'sqlUser', 'sqlPassword', 'sqlDatabase');
	$out  = array();

	foreach (array(install_config_file(), install_root() . '/config.php') as $file) {
		if (!is_file($file)) {
			continue;
		}

		$config = array();
		// Included in a function so it cannot pollute anything.
		@include $file;

		foreach ($keys as $key) {
			if (!isset($out[$key]) && isset($config[$key])) {
				$out[$key] = (string)$config[$key];
			}
		}
	}

	return (count($out) === count($keys)) ? $out : array();
}

/** A connection using the values collected so far, or null. */
function install_connect(?string &$error = null): ?mysqli {
	$link = @new mysqli(
		(string)install_get('sqlHost', '127.0.0.1'),
		(string)install_get('sqlUser'),
		(string)install_get('sqlPassword'),
		(string)install_get('sqlDatabase')
	);

	if ($link->connect_errno) {
		$error = $link->connect_error;
		return null;
	}

	$link->set_charset('utf8mb4');

	return $link;
}

// ---------------------------------------------------------------------------
// Checks
// ---------------------------------------------------------------------------

/** Requirements, as [label, ok, detail, fatal]. */
function install_requirements(): array {
	$checks = array();

	$checks[] = array(
		'PHP 8.1 or newer',
		PHP_VERSION_ID >= 80100,
		'You are on PHP ' . PHP_VERSION,
		true,
	);

	foreach (array('mysqli' => true, 'curl' => false, 'openssl' => false, 'gd' => false, 'zip' => false) as $ext => $fatal) {
		$checks[] = array(
			'Extension: ' . $ext,
			extension_loaded($ext),
			$fatal ? 'Required' : 'Optional',
			$fatal,
		);
	}

	$cache = install_root() . '/engine/cache';
	$checks[] = array(
		'engine/cache/ is writable',
		is_dir($cache) && is_writable($cache),
		$cache,
		true,
	);

	$checks[] = array(
		'The site root is writable',
		is_writable(install_root()),
		'Needed to write config.local.php. You can also create it by hand at the last step.',
		false,
	);

	$schema = install_root() . '/SQL/znote_schema.sql';
	$checks[] = array(
		'SQL/znote_schema.sql is present',
		is_file($schema),
		$schema,
		true,
	);

	return $checks;
}

/**
 * Tables the OT server creates, which ZnoteX reads but never creates itself.
 * Their absence is what makes the installer refuse to go on.
 */
function install_server_tables(mysqli $link): array {
	$required = array('accounts', 'players');
	$optional = array('guilds', 'houses', 'player_deaths', 'players_online');

	$present = array();
	$result  = @$link->query('SHOW TABLES');
	if ($result !== false) {
		while ($row = $result->fetch_array()) {
			$present[strtolower($row[0])] = true;
		}
	}

	$out = array('required' => array(), 'optional' => array(), 'ok' => true);

	foreach ($required as $table) {
		$found = isset($present[$table]);
		$out['required'][$table] = $found;
		if (!$found) {
			$out['ok'] = false;
		}
	}
	foreach ($optional as $table) {
		$out['optional'][$table] = isset($present[$table]);
	}

	$out['znote_installed'] = isset($present['znote']);

	return $out;
}

// ---------------------------------------------------------------------------
// Small view helpers
// ---------------------------------------------------------------------------
function ih($value): string {
	return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function install_url(int $step): string {
	return 'index.php?step=' . $step;
}

function install_error(string $message): void {
	$_SESSION['install_error'] = $message;
}

function install_take_error(): string {
	$error = (string)($_SESSION['install_error'] ?? '');
	unset($_SESSION['install_error']);
	return $error;
}

/**
 * Write the admin character name into config.php's page_admin_access array.
 *
 * Only that one array is touched: it is matched precisely and replaced whole.
 * The rest of the 1000-line file is not parsed, not rewritten, not reformatted.
 * A .bak copy is kept beside it, and the result is checked with php -l before
 * being saved - a broken config.php takes the whole site down, not just the
 * panel, so that check is not optional.
 *
 * The trade-off, and why config.local.php remains the default: a ZnoteX update
 * that ships a new config.php carries this away with it, and you are locked out
 * of the panel until you put the name back. config.local.php survives updates.
 *
 * Returns '' on success, or a message explaining what stopped it.
 */
function install_write_admin_to_config(string $character): string {
	$file = install_root() . '/config.php';

	if (!is_file($file)) {
		return 'config.php not found.';
	}
	if (!is_writable($file)) {
		return 'config.php is not writable by PHP.';
	}

	$source = (string)file_get_contents($file);

	// The array as config.php ships it, whatever the spacing or quoting.
	$pattern = '/\$config\[\s*[\'"]page_admin_access[\'"]\s*\]\s*=\s*array\s*\(.*?\)\s*;/s';

	if (!preg_match($pattern, $source)) {
		return 'Could not find $config[\'page_admin_access\'] in config.php. Add the name by hand.';
	}

	$escaped     = str_replace(array('\\', "'"), array('\\\\', "\\'"), $character);
	$replacement = "\$config['page_admin_access'] = array(\n\t\t'" . $escaped . "',\n\t);";

	// preg_replace treats $ and \ in the replacement as backreferences.
	$replacement = str_replace(array('\\', '$'), array('\\\\', '\\$'), $replacement);

	$updated = preg_replace($pattern, $replacement, $source, 1);

	if ($updated === null || $updated === $source) {
		return 'Could not rewrite the array in config.php.';
	}

	try {
		token_get_all($updated, TOKEN_PARSE);
	} catch (ParseError $e) {
		return 'The edit would have broken config.php, so nothing was changed: ' . $e->getMessage();
	}

	@copy($file, $file . '.bak');

	if (@file_put_contents($file, $updated) === false) {
		return 'Could not write config.php.';
	}

	return '';
}
