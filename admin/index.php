<?php
/**
 * ZnoteX Admin Control Panel - single entry point.
 */

define('ACP_ROOT', __DIR__);

chdir(dirname(__DIR__));

require_once 'engine/init.php';

if (user_logged_in() !== true) {
	header('Location: ../protected.php');
	exit;
}
if (!is_admin($user_data ?? null)) {
	header('Location: ../myaccount.php');
	exit;
}

require_once ACP_ROOT . '/bootstrap.php';

$acp_modules = acp_modules();

$acp_page = (string)($_GET['p'] ?? 'dashboard');
if (!isset($acp_modules[$acp_page])) {
	$acp_page = isset($acp_modules['dashboard'])
		? 'dashboard'
		: (string)(array_key_first($acp_modules) ?? '');
}

$acp_module = $acp_modules[$acp_page] ?? null;

// A nav entry that points somewhere else on the site is a link, not a page.
if ($acp_module !== null && !empty($acp_module['url'])) {
	header('Location: ' . $acp_module['url']);
	exit;
}

// A POST larger than post_max_size reaches PHP with $_POST and $_FILES both
// emptied, which would otherwise look like a forged request.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
	&& !$_POST && !$_FILES
	&& (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
	http_response_code(413);
	die('That upload was larger than post_max_size in php.ini, so PHP discarded it. Raise post_max_size and upload_max_filesize, then try again.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !acp_verify_csrf()) {
	http_response_code(400);
	die('Invalid CSRF token. Reload the page and try again.');
}

ob_start();
if ($acp_module !== null) {
	include $acp_module['file'];
} else {
	acp_empty('No admin modules are installed in admin/modules/.', 'fa-plug');
}
$acp_content = ob_get_clean();

include ACP_ROOT . '/layout/shell.php';
