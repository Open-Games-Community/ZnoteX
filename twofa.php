<?php require_once 'engine/init.php';
znote_csrf_protect_public_post();
protect_page();
theme_open();

$twofa2Enabled = znote2fa_v2_enabled();

if ($config['twoFactorAuthenticator'] === false && !$twofa2Enabled) {
	die("Two-factor authentication is disabled in config.php");
}

if ($config['twoFactorAuthenticator'] === false) {
	// Only 2FA v2 is enabled - skip straight to it, the legacy TFS section below
	// has nothing to offer here.
} else if ($config['ServerEngine'] !== 'TFS_10') {
	view('twofa_legacy_incompatible', ['twofa2Enabled' => $twofa2Enabled]);
} else {
	// If user wishes to disable Two-Factor Authentication
	if (isset($_POST['disable_2fa'])) {
		db()->execute("UPDATE `accounts` SET `secret` = NULL WHERE `id` = ? LIMIT 1;", [(int)$session_user_id]);
		db()->execute("UPDATE `znote_accounts` SET `secret` = NULL WHERE `account_id` = ? LIMIT 1;", [(int)$session_user_id]);
	}

	// General init
	require_once("engine/function/rfc6238.php");

	// Fetch the secret data from accounts and znote_accounts table
	$query = db()->fetchOne("SELECT `a`.`secret` AS `secret`, `za`.`secret` AS `znote_secret` FROM `accounts` AS `a` INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id` WHERE `a`.`id` = ? LIMIT 1;", [(int)$session_user_id]);

	// If secret column returns NULL on the regular accounts table, then it means the system is not active.
	$status = ($query['secret'] === NULL) ? false : true;

	// If secret column returns NULL on the znote_accounts table, then it means we havent generated a secret for it yet.
	if ($query['znote_secret'] === NULL) {
		$scrtString = ($query['secret'] === NULL) ? generateRandomString(16) : $query['secret'];
		// Add secret to znote_accounts table
		db()->execute("UPDATE `znote_accounts` SET `secret` = ? WHERE `account_id` = ?;", [$scrtString, (int)$session_user_id]);
		$query['znote_secret'] = $scrtString;
	}

	view('twofa_legacy', ['status' => $status, 'query' => $query]);
}

// ---------------------------------------------------------------------------
// 2FA v2 - independent of the game engine.
// ---------------------------------------------------------------------------
if ($twofa2Enabled) {

	$accountId = (int)$session_user_id;
	$revealedRecoveryCodes = array();

	if (empty($_POST) === false) {
		if (isset($_POST['tfa2_totp_start'])) {
			$secret = znote2fa_totp_start($accountId);

		} else if (isset($_POST['tfa2_totp_confirm'])) {
			$code = getValue($_POST['tfa2_totp_code'] ?? null);
			if ($code !== false && znote2fa_totp_confirm($accountId, $code)) {
				$errors[] = t_default('twofa2.totp_confirmed', 'Authenticator app enabled.');
			} else {
				$errors[] = t_default('twofa2.totp_confirm_failed', 'That code did not match. Scan the QR code again and try once more.');
			}

		} else if (isset($_POST['tfa2_totp_disable'])) {
			znote2fa_totp_disable($accountId);

		} else if (isset($_POST['tfa2_email_toggle'])) {
			$enableEmailOtp = !empty($_POST['tfa2_email_enabled']);
			$email = trim((string)($user_data['email'] ?? ''));
			if ($enableEmailOtp && !znote2fa_v2_config()['email_otp_enabled']) {
				$errors[] = t_default('twofa2.email_unavailable', 'E-mail codes are disabled by the site administrator.');
			} else if ($enableEmailOtp && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$errors[] = t_default('twofa2.email_invalid', 'Add a valid e-mail address to your account before enabling e-mail codes.');
			} else {
				znote2fa_email_otp_set($accountId, $enableEmailOtp);
			}

		} else if (isset($_POST['tfa2_recovery_generate'])) {
			$revealedRecoveryCodes = znote2fa_recovery_generate($accountId);

		} else if (isset($_POST['tfa2_device_revoke'])) {
			znote2fa_trusted_device_revoke($accountId, (int)$_POST['tfa2_device_revoke']);

		} else if (isset($_POST['tfa2_logout_all'])) {
			znote2fa_logout_all_devices($accountId);
			$_SESSION['tfa2_sv'] = znote2fa_session_version($accountId); // keep this session, the one that asked, alive
			znote2fa_trusted_cookie_clear();
			$errors[] = t_default('twofa2.logged_out_all', 'Every other session and trusted device has been signed out.');
		}
	}

	$status = znote2fa_status($accountId);
	$devices = znote2fa_trusted_devices_list($accountId);

	view('twofa2', [
		'status' => $status,
		'devices' => $devices,
		'revealedRecoveryCodes' => $revealedRecoveryCodes,
		'accountId' => $accountId,
	]);
}

theme_close(); ?>
