<?php
require_once 'engine/init.php';

if (lws_is_request()) {
	lws_handle();
}

logged_in_redirect();
theme_open();

if (empty($_POST) === false) {

	if ($config['log_ip']) {
		znote_visitor_insert_detailed_data(5);
	}

	$username = $_POST['username'];
	$password = $_POST['password'];

	if (empty($username) || empty($password)) {
		$errors[] = t('login.empty_fields');
	} else if (strlen($username) > 32 || strlen($password) > 64) {
			$errors[] = t('login.too_long');
	} else if (user_exist($username) === false) {
		$errors[] = t('login.not_found');
	} /*else if (user_activated($username) === false) {
		$errors[] = t('login.not_activated');
	} */else if ($config['use_token'] && !Token::isValid($_POST['token'] ?? null)) {
		$errors[] = t('login.token_invalid');
	} else {

		// Starting login. TFS_16 and CANARY are normalised to TFS_10 by engine/init.php.
		if (in_array($config['ServerEngine'], array('TFS_02', 'OTHIRE', 'TFS_10'), true)) $login = user_login($username, $password);
		else if ($config['ServerEngine'] == 'TFS_03') $login = user_login_03($username, $password);
		else $login = false;
		if ($login === false) {
			$errors[] = t('login.wrong_combo');
		} else {
			// Check if user have access to login
			$status = false;
			if ($config['mailserver']['register']) {
				$authenticate = mysql_select_single("SELECT `id` FROM `znote_accounts` WHERE `account_id`='$login' AND `active`='1' LIMIT 1;");
				if ($authenticate !== false) {
					$status = true;
				} else {
					$errors[] = t('login.not_activated');
				}
			} else $status = true;

			if ($status) {
				// Regular login success, now lets check authentication token code
				if ($config['ServerEngine'] == 'TFS_10' && $config['twoFactorAuthenticator']) {
					require_once("engine/function/rfc6238.php");

					// Two factor authentication code / token
					$authcode = (isset($_POST['authcode'])) ? getValue($_POST['authcode'] ?? null) : false;

					// Load secret values from db
					$query = mysql_select_single("SELECT `a`.`secret` AS `secret`, `za`.`secret` AS `znote_secret` FROM `accounts` AS `a` INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id` WHERE `a`.`id`='".(int)$login."' LIMIT 1;");

					// If account table HAS a secret, we need to validate it
					if ($query['secret'] !== NULL) {

						// Validate the secret first to make sure all is good.
						if (TokenAuth6238::verify($query['secret'], $authcode) !== true) {
							$errors[] = t('login.2fa_wrong');
							$errors[] = t('login.2fa_hint');
							$status = false;
						}

					} else {

						// secret from accounts table is null/not set. Perhaps we can activate it:
						if ($query['znote_secret'] !== NULL && $authcode !== false && !empty($authcode)) {

							// Validate the secret first to make sure all is good.
							if (TokenAuth6238::verify($query['znote_secret'], $authcode)) {
								// Success, enable the 2FA system
								mysql_update("UPDATE `accounts` SET `secret`= '".$query['znote_secret']."' WHERE `id`='$login';");
							} else {
								$errors[] = t('login.2fa_activate_failed');
								$errors[] = t('login.2fa_wrong');
								$errors[] = t('login.2fa_hint');
								$status = false;
							}
						}
					}
				} // End tfs 1.0+ with 2FA auth

				if ($status) {
					setSession('user_id', $login);

					// if IP is not set (etc acc created before Znote AAC was in use)
					$znote_data = user_znote_account_data($login, 'ip');
					if ($znote_data['ip'] == 0) {
						$update_data = array(
						'ip' => getIPLong(),
						);
						user_update_znote_account($update_data);
					}

					// Send them to myaccount.php
					header('Location: myaccount.php');
					exit();
				}
			}
		}
	}
}

if (empty($errors) === false) {
	?>
	<h2><?= t('login.failed_title') ?></h2>
	<?php
	header("HTTP/1.1 401 Not Found");
	echo output_errors($errors);
}

if (empty($_POST) === true || empty($errors) === false) {
	?>
	<form class="loginForm" action="login.php" method="post">
		<ul>
			<li>
				<?= t('widget.login.username') ?><br>
				<input type="text" name="username" id="login_username">
			</li>
			<li>
				<?= t('widget.login.password') ?><br>
				<input type="password" name="password" id="login_password">
			</li>
			<?php if ($config['twoFactorAuthenticator']): ?>
				<li>
					<?= t('widget.login.token') ?><br>
					<input type="password" name="authcode">
				</li>
			<?php endif; ?>
			<?php Token::create(); ?>
			<li>
				<input type="submit" value="<?= t('widget.login.submit') ?>">
			</li>
		</ul>
	</form>
	<?php
}

theme_close(); ?>
