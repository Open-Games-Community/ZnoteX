<?php
require_once 'engine/init.php';
protect_page();
theme_open();
require_once('config.countries.php');

if (empty($_POST) === false) {
	// $_POST['']
	/* Token used for cross site scripting security */
	if (!Token::isValid($_POST['token'])) {
		$errors[] = t('login.token_invalid');
	}
	$required_fields = array('new_email', 'new_flag');
	foreach($_POST as $key=>$value) {
		if (empty($value) && in_array($key, $required_fields) === true) {
			$errors[] = t('reg.fill_all');
			break 1;
		}
	}

	if (empty($errors) === true) {
		if (filter_var($_POST['new_email'], FILTER_VALIDATE_EMAIL) === false) {
			$errors[] = t('reg.email_invalid');
		} else if (user_email_exist($_POST['new_email']) === true && $user_data['email'] !== $_POST['new_email']) {
			$errors[] = t('reg.email_taken');
		}
	}
}

/**
 * What the view has to render: 'success', 'errors' or 'form'.
 * The account write stays here - a theme must never carry it.
 */
$formState = 'form';

if (isset($_GET['success']) === true && empty($_GET['success']) === true) {
	$formState = 'success';

} elseif (empty($_POST) === false && empty($errors) === true) {

	$update_data = array(
		'email' => $_POST['new_email']
	);

	$update_znote_data = array(
		'flag' => getValue($_POST['new_flag'] ?? null),
		'active_email' => '0'
	);

	// If the address was previously verified, take back the bonus points.
	if ($user_znote_data['active_email'] > 0) {
		$update_znote_data['points'] = $user_znote_data['points'] - $config['mailserver']['verify_email_points'];
	}

	user_update_account($update_data);
	user_update_znote_account($update_znote_data);

	header('Location: settings.php?success');
	exit;

} elseif (empty($errors) === false) {
	$formState = 'errors';
}

view('settings');

theme_close();
