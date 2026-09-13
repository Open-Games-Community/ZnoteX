<?php
require_once 'engine/init.php';

if (lws_is_request()) {
	lws_handle();
}

logged_in_redirect();
theme_open();

// ---------------------------------------------------------------------------
// Step 2 of login: website 2FA v2 (independent of the game engine).
//
// Reached only after user_login() + the legacy TFS 2FA already succeeded, see
// below. The pending account id lives in the session, never in the form, so a
// visitor cannot skip straight here with an arbitrary account id.
// ---------------------------------------------------------------------------
if (isset($_POST['tfa2_code']) && isset($_SESSION['tfa2_pending']['id'])) {
	$pending = $_SESSION['tfa2_pending'];

	if (!Token::isValid($_POST['token'] ?? null)) {
		$errors[] = t('login.token_invalid');
	} else if ((int)$pending['until'] < time()) {
		unset($_SESSION['tfa2_pending']);
		$errors[] = t_default('twofa2.expired', 'That verification step expired. Please log in again.');
	} else {
		$accountId = (int)$pending['id'];
		$code = getValue($_POST['tfa2_code'] ?? null);

		if ($code !== false && znote2fa_verify_login_input($accountId, $code)) {
			unset($_SESSION['tfa2_pending']);
			setSession('user_id', $accountId);
			$_SESSION['tfa2_sv'] = znote2fa_session_version($accountId);
			Token::generate();

			if (!empty($_POST['tfa2_trust']) && (int)znote2fa_v2_config()['trusted_device_days'] > 0) {
				znote2fa_trusted_device_issue($accountId);
			}

			header('Location: myaccount.php');
			exit();
		}

		$errors[] = t_default('twofa2.wrong_code', 'That code is not valid. It may have expired, or you may have mistyped it.');
	}
}

if (isset($_SESSION['tfa2_pending']['id'])) {
	$pendingStatus = znote2fa_status((int)$_SESSION['tfa2_pending']['id']);

	if (empty($_POST['tfa2_email_sent']) && $pendingStatus['email_otp_enabled'] && !$pendingStatus['totp_enabled']) {
		$pendingUser = user_data((int)$_SESSION['tfa2_pending']['id'], 'email', 'name');
		if (is_array($pendingUser) && !empty($pendingUser['email'])) {
			if (!znote2fa_email_send_code((int)$_SESSION['tfa2_pending']['id'], (string)$pendingUser['email'], (string)($pendingUser['name'] ?? ''))) {
				$errors[] = t_default('twofa2.email_delivery_failed', 'The verification e-mail could not be sent. Try again later or use a recovery code.');
			}
		} else {
			$errors[] = t_default('twofa2.email_missing', 'This account has no valid e-mail address. Use a recovery code or contact an administrator.');
		}
	}
	?>
	<h2><?= t('twofa.title') ?></h2>
	<?php if (empty($errors) === false): ?>
		<?= output_errors($errors) ?>
	<?php endif; ?>
	<p>
		<?php if ($pendingStatus['totp_enabled']): ?>
			<?= t_default('twofa2.enter_app_code', 'Enter the code from your authenticator app, or a recovery code.') ?>
		<?php else: ?>
			<?= t_default('twofa2.enter_email_code', 'We emailed you a verification code. Enter it below, or use a recovery code.') ?>
		<?php endif; ?>
	</p>
	<form class="loginForm" method="post" action="login.php">
		<ul>
			<li>
				<input type="text" name="tfa2_code" autocomplete="one-time-code" autofocus>
			</li>
			<?php if ((int)znote2fa_v2_config()['trusted_device_days'] > 0): ?>
				<li>
					<label><input type="checkbox" name="tfa2_trust" value="1"> <?= t_default('twofa2.trust_device', 'Remember this device') ?></label>
				</li>
			<?php endif; ?>
			<input type="hidden" name="tfa2_email_sent" value="1">
			<?php Token::create(); ?>
			<li>
				<input type="submit" value="<?= t('widget.login.submit') ?>">
			</li>
		</ul>
	</form>
	<?php
	theme_close();
	exit();
}

if (empty($_POST) === false && !isset($_POST['tfa2_code'])) {

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
	} */else if (!Token::isValid($_POST['token'] ?? null)) {
		$errors[] = t('login.token_invalid');
	} else {

		// Starting login. Delegated to the server adapter, which knows whether
		// this engine identifies an account by name or id and which password
		// scheme it uses (see engine/adapter/).
		$login = znote_server_adapter()->login($username, $password);
		if ($login === false) {
			$errors[] = t('login.wrong_combo');
		} else {
			// Check if user have access to login
			$status = false;
			if ($config['mailserver']['register']) {
				$authenticate = db()->fetchOne(
					"SELECT `id` FROM `znote_accounts` WHERE `account_id` = ? AND `active` = 1 LIMIT 1;",
					[(int)$login]
				);
				if ($authenticate !== false) {
					$status = true;
				} else {
					$errors[] = t('login.not_activated');
				}
			} else $status = true;

			if ($status) {
				// Regular login success, now lets check authentication token code
				if (znote_server_adapter()->supportsLegacyTwoFactor() && $config['twoFactorAuthenticator']) {
					require_once("engine/function/rfc6238.php");

					// Two factor authentication code / token
					$authcode = (isset($_POST['authcode'])) ? getValue($_POST['authcode'] ?? null) : false;

					// Load secret values from db
					$query = db()->fetchOne(
						"SELECT `a`.`secret` AS `secret`, `za`.`secret` AS `znote_secret`
						FROM `accounts` AS `a`
						INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id`
						WHERE `a`.`id` = ?
						LIMIT 1;",
						[(int)$login]
					);

					if ($query === false) {
						$errors[] = t('login.failed_title');
						$status = false;

					// If account table HAS a secret, we need to validate it
					} else if ($query['secret'] !== NULL) {

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
								db()->execute(
									"UPDATE `accounts` SET `secret` = ? WHERE `id` = ?;",
									[$query['znote_secret'], (int)$login]
								);
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
					if (!znote_session_regenerate()) {
						$errors[] = t('login.failed_title');
						$status = false;
					}
				}

				if ($status) {
					$loginNameRow = user_data($login, 'id', 'name');
					$isAdminAccount = has_admin_panel_access(is_array($loginNameRow) ? $loginNameRow : array());

					if (znote2fa_required((int)$login, $isAdminAccount) && !znote2fa_trusted_device_check((int)$login)) {
						$_SESSION['tfa2_pending'] = array('id' => (int)$login, 'until' => time() + 300);
						header('Location: login.php');
						exit();
					}

					setSession('user_id', $login);
					$_SESSION['tfa2_sv'] = znote2fa_session_version((int)$login);
					Token::generate();

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
