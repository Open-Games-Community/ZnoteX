<?php require_once 'engine/init.php';
protect_page();

if (empty($_POST) === false) {
	/* Token used for cross site scripting security */
	if (!Token::isValid($_POST['token'] ?? null)) {
		$errors[] = t('login.token_invalid');
	}

	$required_fields = array('current_password', 'new_password', 'new_password_again');

	foreach($required_fields as $key) {
		if (empty($_POST[$key])) {
			$errors[] = t('reg.fill_all');
			break 1;
		}
	}

	$pass_data = user_data($session_user_id, 'password');
	if (!is_array($pass_data)) $pass_data = array('password' => '');

	// .3 compatibility
	$salt = array('salt' => '');
	if (znote_server_adapter()->normalizedEngine() === 'TFS_03' && $config['salt'] === true) {
		$salt = user_data($session_user_id, 'salt');
		if (!is_array($salt)) $salt = array('salt' => '');
	}
	$current_password = (string)($_POST['current_password'] ?? '');
	$new_password = (string)($_POST['new_password'] ?? '');
	$new_password_again = (string)($_POST['new_password_again'] ?? '');
	if (user_verify_login_password((int)$session_user_id, $current_password, (string)$pass_data['password'], (string)($salt['salt'] ?? ''))) {
		if (trim($new_password) !== trim($new_password_again)) {
			$errors[] = t('changepw.mismatch');
		} else if (strlen($new_password) < 6) {
			$errors[] = t('changepw.too_short');
		} else if (strlen($new_password) > 100) {
			$errors[] = t('changepw.too_long');
		}
	} else {
		$errors[] = t('changepw.wrong');
	}
}

/**
 * What the view has to render: 'success', 'errors' or 'form'.
 * The password write itself stays here - a theme must never carry it.
 */
$formState = 'form';

if (isset($_GET['success']) && empty($_GET['success'])) {
	$formState = 'success';

	// The password changed, so this session is no longer valid.
	znote_session_destroy();
	header('refresh:2;url=index.php');

} elseif (empty($_POST) === false && empty($errors) === true) {

	if (znote_server_adapter()->normalizedEngine() === 'TFS_03') {
		user_change_password03($session_user_id, $_POST['new_password']);
	} else {
		user_change_password($session_user_id, $_POST['new_password']);
	}

	header('Location: changepassword.php?success');
	exit;

} elseif (empty($errors) === false) {
	$formState = 'errors';
}

theme_open();

view('changepassword');

theme_close();
