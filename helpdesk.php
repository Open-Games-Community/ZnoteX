<?php
require_once 'engine/init.php';
protect_page();
theme_open();

$view = (isset($_GET['view']) && (int)$_GET['view'] > 0) ? (int)$_GET['view'] : false;
if ($view !== false) {
	if (!empty($_POST['reply_text'])) {

		// Save ticket reply on database
		$query = array(
			'tid'   =>	$view,
			'username'=>	getValue($_POST['username'] ?? null),
			'message' =>	getValue($_POST['reply_text'] ?? null),
			'created' =>	time(),
		);
		$fields = '`'. implode('`, `', array_keys($query)) .'`';
		$placeholders = implode(', ', array_fill(0, count($query), '?'));
		db()->execute("INSERT INTO `znote_tickets_replies` ($fields) VALUES ($placeholders)", array_values($query));
		db()->execute("UPDATE `znote_tickets` SET `status` = 'Player-Reply' WHERE `id` = ? LIMIT 1;", [$view]);
	}
	$ticketData = db()->fetchOne("SELECT * FROM znote_tickets WHERE id = ? LIMIT 1;", [$view]);

	if(!$ticketData || $ticketData['owner'] != $session_user_id) {
		echo t('helpdesk.no_access');
		theme_close();
		die;
	}
	$replies = db()->fetchAll("SELECT * FROM znote_tickets_replies WHERE tid = ? ORDER BY `created`;", [$view]);
	view('helpdesk_ticket');
} else {

	$account = db()->fetchOne("SELECT name,email FROM accounts WHERE id = ?", [$session_user_id]);
	if (!is_array($account)) $account = array();
	$account += array('name' => '', 'email' => '');
	if (!empty($_POST)) {
		$required_fields = array('username', 'email', 'subject', 'message');
		foreach($_POST as $key=>$value) {
			if (empty($value) && in_array($key, $required_fields) === true) {
				$errors[] = t('reg.fill_all');
				break 1;
			}
		}

		// check errors (= user exist, pass long enough
		if (empty($errors) === true) {
			/* Token used for cross site scripting security */
			if (!Token::isValid($_POST['token'])) {
				$errors[] = t('login.token_invalid');
			}
			if ($config['use_captcha']) {
				if(!verifyGoogleReCaptcha($_POST['g-recaptcha-response'])) {
					$errors[] = t('reg.captcha');
				}
			}
			// Reversed this if, so: first check if you need to validate, then validate.
			if ($config['validate_IP'] === true && validate_ip(getIP()) === false) {
				$errors[] = t('reg.bad_ip');
			}
		}
	}
	$tickets = db()->fetchAll("SELECT id,subject,creation,status FROM znote_tickets WHERE owner = ? ORDER BY creation DESC", [$session_user_id]);

	$helpdeskCreated = isset($_GET['success']) && empty($_GET['success']);

	if (!$helpdeskCreated && empty($_POST) === false && empty($errors) === true) {
		if ($config['log_ip']) {
			znote_visitor_insert_detailed_data(1);
		}

		//Save ticket on database
		$query = array(
			'owner'   =>	$session_user_id,
			'username'=>	getValue($_POST['username'] ?? null),
			'subject' =>	getValue($_POST['subject'] ?? null),
			'message' =>	getValue($_POST['message'] ?? null),
			'ip'	  =>	getIPLong(),
			'creation' =>	time(),
			'status'  =>	'Open'
		);

		$fields = '`'. implode('`, `', array_keys($query)) .'`';
		$placeholders = implode(', ', array_fill(0, count($query), '?'));
		db()->execute("INSERT INTO `znote_tickets` ($fields) VALUES ($placeholders)", array_values($query));

		header('Location: helpdesk.php?success');
		exit();
	}

	view('helpdesk_list', ['helpdeskCreated' => $helpdeskCreated]);
}
theme_close();
?>
