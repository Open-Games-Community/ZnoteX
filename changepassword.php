<?php require_once 'engine/init.php';
protect_page();

if (empty($_POST) === false) {
	/* Token used for cross site scripting security */
	if (!Token::isValid($_POST['token'] ?? null)) {
		$errors[] = 'Token is invalid.';
	}

	$required_fields = array('current_password', 'new_password', 'new_password_again');

	foreach($required_fields as $key) {
		if (empty($_POST[$key])) {
			$errors[] = 'You need to fill in all fields.';
			break 1;
		}
	}

	$pass_data = user_data($session_user_id, 'password');
	if (!is_array($pass_data)) $pass_data = array('password' => '');

	// .3 compatibility
	if ($config['ServerEngine'] == 'TFS_03' && $config['salt'] === true) {
		$salt = user_data($session_user_id, 'salt');
		if (!is_array($salt)) $salt = array('salt' => '');
	}
	$current_password = (string)($_POST['current_password'] ?? '');
	$new_password = (string)($_POST['new_password'] ?? '');
	$new_password_again = (string)($_POST['new_password_again'] ?? '');
	if (sha1($current_password) === $pass_data['password'] || $config['ServerEngine'] == 'TFS_03' && $config['salt'] === true && sha1($salt['salt'].$current_password) === $pass_data['password']) {
		if (trim($new_password) !== trim($new_password_again)) {
			$errors[] = 'Your new passwords do not match.';
		} else if (strlen($new_password) < 6) {
			$errors[] = 'Your new passwords must be at least 6 characters.';
		} else if (strlen($new_password) > 100) {
			$errors[] = 'Your new passwords must be less than 100 characters.';
		}
	} else {
		$errors[] = 'Your current password is incorrect.';
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
	session_destroy();
	header('refresh:2;url=index.php');

} elseif (empty($_POST) === false && empty($errors) === true) {

	if ($config['ServerEngine'] == 'TFS_02' || $config['ServerEngine'] == 'TFS_10' || $config['ServerEngine'] == 'OTHIRE') {
		user_change_password($session_user_id, $_POST['new_password']);
	} else if ($config['ServerEngine'] == 'TFS_03') {
		user_change_password03($session_user_id, $_POST['new_password']);
	}

	header('Location: changepassword.php?success');
	exit;

} elseif (empty($errors) === false) {
	$formState = 'errors';
}

theme_open();

view('changepassword');

theme_close();
