<?php
/**
 * Front controller for pages a theme adds.
 *
 * Any .php file dropped in layouts/<theme>/pages/ is live at
 * page.php?p=<filename> - nothing to register anywhere.
 *
 * The name is checked against the files that actually exist in the theme, so
 * ?p= can never reach anything outside pages/.
 *
 * Pretty URLs are a rewrite away, if you want them:
 *   RewriteRule ^([a-z0-9_-]+)\.html$ page.php?p=$1 [L,QSA]
 */

require_once 'engine/init.php';

$requested = theme_sanitize((string)($_GET['p'] ?? ''));

// A plugin page: page.php?plugin=shop_coupons&p=redeem
// Checked first, and only for an enabled plugin - a disabled one is invisible.
$pluginName = znote_plugin_sanitize((string)($_GET['plugin'] ?? ''));
$file = ($pluginName !== '')
	? znote_plugin_page($pluginName, $requested)
	: (($requested !== '') ? theme_file('pages/' . $requested . '.php') : null);

if ($file === null) {
	http_response_code(404);
	$page_filename = 'page_not_found';

	theme_open();
	echo '<h1>Page not found</h1>';
	if ($pluginName !== '') {
		echo '<p>The <strong>' . htmlspecialchars($pluginName, ENT_QUOTES, 'UTF-8')
		   . '</strong> plugin has no page called <code>' . htmlspecialchars($requested, ENT_QUOTES, 'UTF-8')
		   . '</code>, or the plugin is disabled.</p>';
	} else {
		echo '<p>There is no <code>pages/' . htmlspecialchars($requested, ENT_QUOTES, 'UTF-8')
		   . '.php</code> in the <strong>' . htmlspecialchars(theme_active(), ENT_QUOTES, 'UTF-8')
		   . '</strong> theme.</p>';
	}
	theme_close();
	exit;
}

// Lets a theme style one of its own pages from CSS alone: body.page_wiki
$page_filename = 'page_' . ($pluginName !== '' ? $pluginName . '_' : '') . $requested;

theme_open();
include $file;
theme_close();
