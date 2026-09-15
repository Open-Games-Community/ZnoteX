<?php

function znote2fa_v2_config(): array {
	global $config;
	$cfg = (array)($config['twoFactorV2'] ?? array());

	return array(
		'enabled' => !empty($cfg['enabled']),
		'email_otp_enabled' => !array_key_exists('email_otp_enabled', $cfg) || $cfg['email_otp_enabled'] !== false,
		'force_admins' => !empty($cfg['force_admins']),
		'recovery_codes_count' => max(1, (int)($cfg['recovery_codes_count'] ?? 10)),
		'trusted_device_days' => max(0, (int)($cfg['trusted_device_days'] ?? 30)),
	);
}

function znote2fa_v2_enabled(): bool {
	return znote2fa_v2_config()['enabled'];
}

function znote2fa_row(int $accountId): array {
	$row = db()->fetchOne("SELECT * FROM `znote_2fa` WHERE `account_id` = ? LIMIT 1;", [$accountId]);

	if (!is_array($row)) {
		return array(
			'account_id' => $accountId,
			'totp_secret' => null,
			'totp_enabled' => 0,
			'email_otp_enabled' => 0,
			'recovery_codes' => null,
			'session_version' => 1,
			'updated_at' => 0,
		);
	}

	return $row;
}

function znote2fa_ensure_row(int $accountId): void {
	db()->execute("
		INSERT INTO `znote_2fa` (`account_id`, `session_version`, `updated_at`)
		VALUES (?, 1, ?)
		ON DUPLICATE KEY UPDATE `account_id` = `account_id`;
	", [$accountId, time()]);
}

function znote2fa_status(int $accountId): array {
	$row = znote2fa_row($accountId);
	$recoveryCodes = znote2fa_recovery_decode($row['recovery_codes'] ?? null);
	$emailOtpEnabled = znote2fa_v2_config()['email_otp_enabled'] && !empty($row['email_otp_enabled']);

	return array(
		'totp_enabled' => !empty($row['totp_enabled']),
		'totp_pending' => empty($row['totp_enabled']) && !empty($row['totp_secret']),
		'email_otp_enabled' => $emailOtpEnabled,
		'any_enabled' => !empty($row['totp_enabled']) || $emailOtpEnabled,
		'recovery_remaining' => count($recoveryCodes),
	);
}

function znote2fa_totp_start(int $accountId): string {
	$secret = TokenAuth6238::generateRandomClue(20);
	znote2fa_ensure_row($accountId);
	db()->execute("UPDATE `znote_2fa` SET `totp_secret` = ?, `totp_enabled` = 0, `updated_at` = ? WHERE `account_id` = ?;", [$secret, time(), $accountId]);

	return $secret;
}

function znote2fa_totp_confirm(int $accountId, string $code): bool {
	$row = znote2fa_row($accountId);
	if (empty($row['totp_secret']) || !TokenAuth6238::verify($row['totp_secret'], $code)) {
		return false;
	}

	return db()->execute("UPDATE `znote_2fa` SET `totp_enabled` = 1, `updated_at` = ? WHERE `account_id` = ?;", [time(), $accountId]) !== false;
}

function znote2fa_totp_disable(int $accountId): void {
	db()->execute("UPDATE `znote_2fa` SET `totp_secret` = NULL, `totp_enabled` = 0, `updated_at` = ? WHERE `account_id` = ?;", [time(), $accountId]);
}

function znote2fa_totp_verify(int $accountId, string $code): bool {
	$row = znote2fa_row($accountId);
	return !empty($row['totp_enabled']) && !empty($row['totp_secret']) && TokenAuth6238::verify($row['totp_secret'], $code);
}

function znote2fa_email_otp_set(int $accountId, bool $enabled): void {
	$enabled = $enabled && znote2fa_v2_config()['email_otp_enabled'];
	znote2fa_ensure_row($accountId);
	db()->execute("UPDATE `znote_2fa` SET `email_otp_enabled` = ?, `updated_at` = ? WHERE `account_id` = ?;", [$enabled ? 1 : 0, time(), $accountId]);
}

function znote2fa_email_send_code(int $accountId, string $email, string $accountName = ''): bool {
	global $config;

	$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
	$hash = hash('sha256', $code);
	$expires = time() + 600;

	$stored = db()->execute("
		INSERT INTO `znote_2fa_email_codes` (`account_id`, `code_hash`, `expires_at`, `attempts`)
		VALUES (?, ?, ?, 0)
		ON DUPLICATE KEY UPDATE `code_hash` = VALUES(`code_hash`), `expires_at` = VALUES(`expires_at`), `attempts` = 0;
	", [$accountId, $hash, $expires]);

	if ($stored === false) {
		return false;
	}

	$mail = new Mail((array)($config['mailserver'] ?? array()));
	$title = ($config['site_title'] ?? 'ZnoteX') . ' - Login verification code';
	$html = '<p>Your login verification code is:</p><h2 style="letter-spacing:4px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</h2>'
		. '<p>It expires in 10 minutes. If you did not try to log in, you can ignore this e-mail.</p>';

	return $mail->sendMail($email, $title, $html, $accountName);
}

function znote2fa_email_verify_code(int $accountId, string $code): bool {
	$row = db()->fetchOne("SELECT * FROM `znote_2fa_email_codes` WHERE `account_id` = ? LIMIT 1;", [$accountId]);
	if (!is_array($row)) {
		return false;
	}

	if ((int)$row['expires_at'] < time() || (int)$row['attempts'] >= 5) {
		db()->execute("DELETE FROM `znote_2fa_email_codes` WHERE `account_id` = ?;", [$accountId]);
		return false;
	}

	if (!hash_equals((string)$row['code_hash'], hash('sha256', $code))) {
		db()->execute("UPDATE `znote_2fa_email_codes` SET `attempts` = `attempts` + 1 WHERE `account_id` = ?;", [$accountId]);
		return false;
	}

	db()->execute("DELETE FROM `znote_2fa_email_codes` WHERE `account_id` = ?;", [$accountId]);
	return true;
}

function znote2fa_recovery_decode($stored): array {
	if (!is_string($stored) || $stored === '') {
		return array();
	}

	$decoded = json_decode($stored, true);
	return is_array($decoded) ? $decoded : array();
}

function znote2fa_recovery_format(string $code): string {
	$code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
	return $code;
}

function znote2fa_recovery_generate(int $accountId, ?int $count = null): array {
	$count = $count ?? znote2fa_v2_config()['recovery_codes_count'];
	$plain = array();
	$hashed = array();

	for ($i = 0; $i < $count; $i++) {
		$code = strtoupper(bin2hex(random_bytes(5))); // 10 hex chars
		$plain[] = substr($code, 0, 5) . '-' . substr($code, 5, 5);
		$hashed[] = password_hash($code, PASSWORD_DEFAULT);
	}

	znote2fa_ensure_row($accountId);
	db()->execute("UPDATE `znote_2fa` SET `recovery_codes` = ?, `updated_at` = ? WHERE `account_id` = ?;", [json_encode($hashed), time(), $accountId]);

	return $plain;
}

function znote2fa_recovery_clear(int $accountId): void {
	db()->execute("UPDATE `znote_2fa` SET `recovery_codes` = NULL, `updated_at` = ? WHERE `account_id` = ?;", [time(), $accountId]);
}

function znote2fa_recovery_consume(int $accountId, string $code): bool {
	$formatted = znote2fa_recovery_format($code);
	if ($formatted === '') {
		return false;
	}

	$row = znote2fa_row($accountId);
	$hashes = znote2fa_recovery_decode($row['recovery_codes'] ?? null);

	foreach ($hashes as $index => $hash) {
		if (is_string($hash) && password_verify($formatted, $hash)) {
			unset($hashes[$index]);
			db()->execute("UPDATE `znote_2fa` SET `recovery_codes` = ?, `updated_at` = ? WHERE `account_id` = ?;", [json_encode(array_values($hashes)), time(), $accountId]);
			return true;
		}
	}

	return false;
}

function znote2fa_session_version(int $accountId): int {
	return (int)(znote2fa_row($accountId)['session_version'] ?? 1);
}

function znote2fa_logout_all_devices(int $accountId): void {
	znote2fa_ensure_row($accountId);
	db()->execute("UPDATE `znote_2fa` SET `session_version` = `session_version` + 1, `updated_at` = ? WHERE `account_id` = ?;", [time(), $accountId]);
	db()->execute("DELETE FROM `znote_2fa_trusted_devices` WHERE `account_id` = ?;", [$accountId]);

	if (function_exists('znote_hook')) {
		znote_hook('security.logout_all_devices', array('account_id' => $accountId));
	}
}

function znote2fa_trusted_cookie_name(): string {
	global $config;
	return (string)($config['session_prefix'] ?? 'znote_') . '2fa_trust';
}

function znote2fa_trusted_cookie_clear(): void {
	global $config;

	setcookie(znote2fa_trusted_cookie_name(), '', array(
		'expires' => time() - 3600,
		'path' => (string)($config['session']['cookie_path'] ?? '/'),
		'domain' => (string)($config['session']['cookie_domain'] ?? ''),
		'secure' => (bool)znote_session_request_is_https(),
		'httponly' => true,
		'samesite' => (string)($config['session']['cookie_samesite'] ?? 'Lax'),
	));
	unset($_COOKIE[znote2fa_trusted_cookie_name()]);
}

function znote2fa_trusted_device_check(int $accountId): bool {
	$cookie = $_COOKIE[znote2fa_trusted_cookie_name()] ?? '';
	if (!is_string($cookie) || $cookie === '') {
		return false;
	}

	$hash = hash('sha256', $cookie);
	$row = db()->fetchOne("
		SELECT `id` FROM `znote_2fa_trusted_devices`
		WHERE `account_id` = ? AND `token_hash` = ? AND `expires_at` > ?
		LIMIT 1;
	", [$accountId, $hash, time()]);

	if (!is_array($row)) {
		return false;
	}

	db()->execute("UPDATE `znote_2fa_trusted_devices` SET `last_used_at` = ? WHERE `id` = ?;", [time(), (int)$row['id']]);
	return true;
}

function znote2fa_trusted_device_issue(int $accountId): void {
	$days = znote2fa_v2_config()['trusted_device_days'];
	if ($days <= 0) {
		return;
	}

	global $config;
	$token = bin2hex(random_bytes(32));
	$hash = hash('sha256', $token);
	$expires = time() + ($days * 86400);
	$label = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device'), 0, 255);

	db()->execute("
		INSERT INTO `znote_2fa_trusted_devices` (`account_id`, `token_hash`, `label`, `ip`, `created_at`, `expires_at`, `last_used_at`)
		VALUES (?, ?, ?, ?, ?, ?, ?);
	", [$accountId, $hash, $label, (string)($_SERVER['REMOTE_ADDR'] ?? ''), time(), $expires, time()]);

	$secure = (bool)znote_session_request_is_https();
	setcookie(znote2fa_trusted_cookie_name(), $token, array(
		'expires' => $expires,
		'path' => (string)($config['session']['cookie_path'] ?? '/'),
		'domain' => (string)($config['session']['cookie_domain'] ?? ''),
		'secure' => $secure,
		'httponly' => true,
		'samesite' => (string)($config['session']['cookie_samesite'] ?? 'Lax'),
	));
}

function znote2fa_trusted_devices_list(int $accountId): array {
	$rows = db()->fetchAll("
		SELECT `id`, `label`, `ip`, `created_at`, `expires_at`, `last_used_at`
		FROM `znote_2fa_trusted_devices`
		WHERE `account_id` = ?
		ORDER BY `last_used_at` DESC;
	", [$accountId]);

	return is_array($rows) ? $rows : array();
}

function znote2fa_trusted_device_revoke(int $accountId, int $deviceId): void {
	db()->execute("DELETE FROM `znote_2fa_trusted_devices` WHERE `id` = ? AND `account_id` = ?;", [$deviceId, $accountId]);
}

function znote2fa_login_challenge_required(bool $siteEnabled, array $status): bool {
	return $siteEnabled && !empty($status['any_enabled']);
}

function znote2fa_required(int $accountId, bool $isAdmin = false): bool {
	if (!znote2fa_v2_enabled()) {
		return false;
	}

	return znote2fa_login_challenge_required(true, znote2fa_status($accountId));
}

function znote2fa_setup_incomplete(int $accountId, bool $isAdmin): bool {
	return znote2fa_v2_enabled() && $isAdmin && znote2fa_v2_config()['force_admins'] && !znote2fa_status($accountId)['any_enabled'];
}

function znote2fa_verify_login_input(int $accountId, string $input): bool {
	$input = trim($input);
	if ($input === '') {
		return false;
	}

	if (preg_match('/^\d{6}$/', $input)) {
		if (znote2fa_totp_verify($accountId, $input)) {
			return true;
		}
		if (znote2fa_email_verify_code($accountId, $input)) {
			return true;
		}
		return false;
	}

	return znote2fa_recovery_consume($accountId, $input);
}
