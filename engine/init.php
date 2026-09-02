<?php
if (PHP_VERSION_ID < 80100) {
    die('PHP 8.1 or higher is required.');
}

if (!isset($GLOBALS['__znote_start_time'])) {
    $GLOBALS['__znote_start_time'] = microtime(true);
}
$l_start = $GLOBALS['__znote_start_time'];
$start = $GLOBALS['__znote_start_time'];

$time = time();
$version = '2.0.0';

$aacQueries = 0;
$accQueriesData = array();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
ob_start();

require_once 'config.php';
$sessionPrefix = $config['session_prefix'];
if ($config['paypal']['enabled'] || $config['use_captcha']) {
	$curlcheck = extension_loaded('curl');
	if (!$curlcheck) die("php cURL is not enabled. It is required to for paypal or captcha services.<br>1. Find your php.ini file.<br>2. Uncomment extension=php_curl<br>Restart web server.<br><br><b>If you don't want this then disable paypal & use_captcha in config.php.</b>");
}
if ($config['use_captcha'] && !extension_loaded('openssl')) {
	die("php openSSL is not enabled. It is required to for captcha services.<br>1. Find your php.ini file.<br>2. Uncomment extension=php_openssl<br>Restart web server.<br><br><b>If you don't want this then disable use_captcha in config.php.</b>");
}

// References ( & ) works as an alias for a variable,
// they point to the same memmory, instead of duplicating it.
if (!isset($config['TFSVersion'])) $config['TFSVersion'] = &$config['ServerEngine'];
if (!isset($config['ServerEngine'])) $config['ServerEngine'] = &$config['TFSVersion'];

$config['ServerEngineReal'] = $config['ServerEngine'];
if (in_array($config['ServerEngineReal'], array('TFS_16', 'CANARY'), true)) {
    $config['ServerEngine'] = 'TFS_10';
    $config['TFSVersion'] = 'TFS_10';
}
if ($config['ServerEngineReal'] === 'CANARY') {
    $config['twoFactorAuthenticator'] = false;
}

require_once 'database/connect.php';
require_once 'function/general.php';
require_once 'function/users.php';
require_once 'function/cache.php';
require_once 'function/mail.php';
require_once 'function/token.php';
require_once 'function/itemparser/itemlistparser.php';
require_once 'function/settings.php';
require_once 'function/theme.php';
require_once 'function/menus.php';
require_once 'function/plugins.php';

// Settings saved from the admin panel override the values in config.php.
znote_apply_settings();

// Enabled plugins register their hooks here, once the database and settings
// are available and before any page has done anything.
znote_plugins_load();

// The active theme's own config file (defines $follow, the countdown, ...).
// Loaded here, at global scope, so shells, menus, widgets and views all see
// those variables through $GLOBALS.
$themeConfigFile = theme_file('layout_config.php');
if ($themeConfigFile !== null) {
	require_once $themeConfigFile;
}


if (!isset($_SESSION['token'])) {
    Token::generate();
}

if (user_logged_in() === true) {
	$session_user_id = (int)getSession('user_id');
	$user_data = user_data($session_user_id, 'id', 'name', 'password', 'email', 'premium_ends_at');
	if (!is_array($user_data)) $user_data = array();
	$user_data += array('id' => 0, 'name' => '', 'password' => '', 'email' => '', 'premium_ends_at' => 0);

	$premiumLeft = (int)$user_data['premium_ends_at'] - time();
	$user_data['premdays'] = ($premiumLeft > 0) ? floor($premiumLeft / 86400) : 0;

	$user_znote_data = user_znote_account_data($session_user_id, 'ip', 'created', 'points', 'cooldown', 'flag' ,'active_email');
	if (!is_array($user_znote_data)) $user_znote_data = array();
	$user_znote_data += array('ip' => 0, 'created' => 0, 'points' => 0, 'cooldown' => 0, 'flag' => '', 'active_email' => 0);
}
// ---------------------------------------------------------------------------
// Maintenance mode
//
// Checked here, after the session is up, so is_admin() is available: an admin
// browsing a closed site sees it normally and can keep working. Everyone else
// gets the message and nothing else - no queries, no layout, no theme.
// ---------------------------------------------------------------------------
if (!empty($config['maintenance'])) {
	$maintenanceAdmin = (user_logged_in() === true) && isset($user_data) && is_admin($user_data);

	// The admin panel is always reachable, otherwise you could lock yourself
	// out of the switch that turns this off.
	$maintenanceScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
	$maintenanceExempt = in_array($maintenanceScript, array('login.php', 'logout.php'), true)
		|| strpos((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false;

	if (!$maintenanceAdmin && !$maintenanceExempt) {
		http_response_code(503);
		header('Retry-After: 3600');
		?><!DOCTYPE html>
		<html lang="en"><head><meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?= htmlspecialchars($config['site_title'], ENT_QUOTES, 'UTF-8') ?></title>
		<style>
			body{margin:0;min-height:100vh;display:grid;place-items:center;background:#14181f;color:#dfe4ec;
			     font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;padding:24px}
			.box{max-width:520px;text-align:center}
			h1{font-size:22px;margin:0 0 14px}
			p{color:#93a0b2;margin:0 0 18px}
			a{color:#d1a233}
		</style></head><body>
		<div class="box">
			<h1><?= htmlspecialchars($config['site_title'], ENT_QUOTES, 'UTF-8') ?></h1>
			<p><?= nl2br(htmlspecialchars((string)$config['maintenance_message'], ENT_QUOTES, 'UTF-8')) ?></p>
			<p><a href="login.php">Staff login</a></p>
		</div></body></html><?php
		exit;
	}
}

$errors = array();
// Log IP
if ($config['log_ip']) {
	$visitor_config = $config['ip_security'];

	$flush = $config['flush_ip_logs'];
	if ($flush != false) {
		$timef = $time - $flush;
		if (getCache() < $timef) {
			$timef = $time - $visitor_config['time_period'];
			mysql_delete("DELETE FROM znote_visitors_details WHERE time <= '$timef'");
			setCache($time);
		}
	}

	$visitor_data = znote_visitors_get_data();

	znote_visitor_set_data($visitor_data); // update or insert data
	znote_visitor_insert_detailed_data(0); // detailed data

	$visitor_detailed = znote_visitors_get_detailed_data($visitor_config['time_period']);

	// max activity
	$v_activity = 0;
	$v_register = 0;
	$v_highscore = 0;
	$v_c_char = 0;
	$v_s_char = 0;
	$v_form = 0;
	foreach ((array)$visitor_detailed as $v_d) {
		// Activity
		if ($v_d['ip'] == getIPLong()) {
			// count each type of visit
			switch ($v_d['type']) {
				case 0: // max activity
					$v_activity++;
				break;

				case 1: // account registered
					$v_register++;
					$v_form++;
				break;

				case 2: // character creations
					$v_c_char++;
					$v_form++;
				break;

				case 3: // Highscore fetched
					$v_highscore++;
					$v_form++;
				break;

				case 4: // character searched
					$v_s_char++;
					$v_form++;
				break;

				case 5: // Other forms (login.?)
					$v_form++;
				break;
			}

		}
	}

	// Deny access if activity is too high
	if ($v_activity > $visitor_config['max_activity']) die("Chill down. Your web activity is too big. max_activity");
	if ($v_register > $visitor_config['max_account']) die("Chill down. You can't create multiple accounts that fast. max_account");
	if ($v_c_char > $visitor_config['max_character']) die("Chill down. Your web activity is too big. max_character");
	if ($v_form > $visitor_config['max_post']) die("Chill down. Your web activity is too big. max_post");

	//var_dump($v_activity, $v_register, $v_highscore, $v_c_char, $v_s_char, $v_form);
	//echo ' <--- IP logging activity past 10 seconds.';
}

// Sub page override system
$filename = explode('/', $_SERVER['SCRIPT_NAME']);
$filename = $filename[count($filename) - 1];
$page_filename = str_replace('.php', '', $filename);
if ($config['allowSubPages']) {
	$subFile = theme_file('sub.php');
	if ($subFile !== null) require_once $subFile;
	if (isset($subpages) && !empty($subpages)) {
		foreach ($subpages as $page) {
			if ($page['override'] && $page['file'] === $filename) {
				theme_open();
				$subPage = theme_file('sub/'.$page['file']);
				if ($subPage !== null) require_once $subPage;
				theme_close();
				exit;
			}
		}
	} else {
		?>
		<div style="background-color: white; padding: 20px; width: 100%; float:left;">
			<h2 style="color: black;">Old layout!</h2>
			<p style="color: black;">The layout is running an outdated sub system which is not compatible with this version of Znote AAC.</p>
			<p style="color: black;">The file layouts/&lt;theme&gt;/sub.php is outdated.
			<br>Please update it to look like <a style="color: orange;" target="_BLANK" href="https://github.com/Znote/ZnoteAAC/blob/master/layout/sub.php" >THIS.</a> (ZnoteX: layouts/https://github.com/Znote/ZnoteAAC/blob/master/layout/sub.phplt;themehttps://github.com/Znote/ZnoteAAC/blob/master/layout/sub.phpgt;/sub.php)<a href="">THIS.</a>
			</p>
		</div>
		<?php
	}
}
?>
