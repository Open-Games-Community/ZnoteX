<?php

function znote_login_guard_config(): array {
	global $config;
	$cfg = (array)($config['login_guard'] ?? array());

	return array(
		'enabled'         => !empty($cfg['enabled']),
		'threshold'       => max(1, (int)($cfg['threshold'] ?? 5)),
		'window_seconds'  => max(60, (int)($cfg['window_minutes'] ?? 15) * 60),
		'lockout_seconds' => max(60, (int)($cfg['lockout_minutes'] ?? 15) * 60),
	);
}

function znote_login_guard_ip(): string {
	$ip = (string)(function_exists('getIP') ? getIP() : ($_SERVER['REMOTE_ADDR'] ?? ''));
	return substr(trim($ip), 0, 45);
}

function znote_login_guard_record(string $ip, string $username, bool $success): void {
	if (!function_exists('znote_table_exists') || !znote_table_exists('znote_login_attempts')) {
		return;
	}

	$now = time();
	db()->execute("
		INSERT INTO `znote_login_attempts` (`ip`, `username`, `success`, `created_at`)
		VALUES (?, ?, ?, ?);
	", [$ip, substr($username, 0, 32), $success ? 1 : 0, $now]);

	if (random_int(1, 20) === 1) {
		db()->execute("DELETE FROM `znote_login_attempts` WHERE `created_at` < ?;", [$now - 86400]);
	}
}

function znote_login_guard_lockout_remaining(string $ip): int {
	$cfg = znote_login_guard_config();
	if (!$cfg['enabled'] || !function_exists('znote_table_exists') || !znote_table_exists('znote_login_attempts')) {
		return 0;
	}

	$now = time();
	$windowStart = $now - $cfg['window_seconds'];

	$row = db()->fetchOne("
		SELECT COUNT(*) AS `failures`, MAX(`created_at`) AS `last_failure`
		FROM `znote_login_attempts`
		WHERE `ip` = ? AND `success` = 0 AND `created_at` >= ?;
	", [$ip, $windowStart]);

	if (!is_array($row) || (int)$row['failures'] < $cfg['threshold']) {
		return 0;
	}

	$unlocksAt = (int)$row['last_failure'] + $cfg['lockout_seconds'];
	return max(0, $unlocksAt - $now);
}

function znote_login_guard_is_locked(string $ip): bool {
	return znote_login_guard_lockout_remaining($ip) > 0;
}
