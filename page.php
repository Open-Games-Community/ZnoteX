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
	if ($pluginName === '' && $requested !== '' && function_exists('znote_table_exists') && znote_table_exists('znote_pages')) {
		$dbPage = db()->fetchOne("
			SELECT `slug`, `title`, `body`, `access`
			FROM `znote_pages`
			WHERE `slug` = ?
			AND `active` = 1
			LIMIT 1;
		", [$requested]);

		if (is_array($dbPage)) {
			if ((int)$dbPage['access'] > 0) {
				protect_page();
			}
			$page_filename = 'page_' . $requested;
			theme_open();
			echo '<h1>' . htmlspecialchars((string)$dbPage['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
			echo znote_bbcode_raw((string)$dbPage['body']);
			theme_close();
			exit;
		}
	}

	http_response_code(404);
	$page_filename = 'page_not_found';
	theme_open();
	echo '<h1>'. t('page.not_found') .'</h1>';
	if ($pluginName !== '') {
		echo '<p>The <strong>' . htmlspecialchars($pluginName, ENT_QUOTES, 'UTF-8')
		   . '</strong> plugin has no page called <code>' . htmlspecialchars($requested, ENT_QUOTES, 'UTF-8')
		   . '</code>, or the plugin is disabled.</p>';
	} else {
		echo '<p>'. t('page.no_such_page') .' <code>pages/' . htmlspecialchars($requested, ENT_QUOTES, 'UTF-8')
		   . '.php</code> in the <strong>' . htmlspecialchars(theme_active(), ENT_QUOTES, 'UTF-8')
		   . '</strong> theme.</p>';
	}
	theme_close();
	exit;
}

// Lets a theme style one of its own pages from CSS alone: body.page_wiki
$page_filename = 'page_' . ($pluginName !== '' ? $pluginName . '_' : '') . $requested;

$page_title = ucwords(str_replace(array('-', '_'), ' ', $requested !== '' ? $requested : 'index'));
if (function_exists('znote_hook_filter')) {
	$page_title = (string) znote_hook_filter('page.title', $page_title, array(
		'plugin'   => $pluginName,
		'page'     => $requested,
		'filename' => $page_filename,
	));
}
$GLOBALS['page_title'] = $page_title;

theme_open();
include $file;
theme_close();
