<?php

function znote_session_request_is_https(): bool {
	$https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
	return ($https !== '' && $https !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function znote_session_start(array $options = array()): void {
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}

	ini_set('session.use_strict_mode', '1');
	ini_set('session.use_only_cookies', '1');
	ini_set('session.use_trans_sid', '0');
	ini_set('session.cookie_httponly', '1');

	$secureOption = $options['cookie_secure'] ?? null;
	$secure = is_bool($secureOption) ? $secureOption : znote_session_request_is_https();
	$sameSite = ucfirst(strtolower((string)($options['cookie_samesite'] ?? 'Lax')));
	if (!in_array($sameSite, array('Lax', 'Strict', 'None'), true)) {
		$sameSite = 'Lax';
	}

	if ($sameSite === 'None' && !$secure) {
		$sameSite = 'Lax';
	}

	$path = (string)($options['cookie_path'] ?? '/');
	if ($path === '') {
		$path = '/';
	}

	session_set_cookie_params(array(
		'lifetime' => max(0, (int)($options['cookie_lifetime'] ?? 0)),
		'path' => $path,
		'domain' => (string)($options['cookie_domain'] ?? ''),
		'secure' => $secure,
		'httponly' => true,
		'samesite' => $sameSite,
	));

	if (!session_start()) {
		throw new RuntimeException('Unable to start a secure PHP session.');
	}
}

function znote_session_regenerate(): bool {
	return session_status() === PHP_SESSION_ACTIVE && session_regenerate_id(true);
}

function znote_session_destroy(): void {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return;
	}

	$_SESSION = array();
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', array(
			'expires' => time() - 42000,
			'path' => $params['path'],
			'domain' => $params['domain'],
			'secure' => $params['secure'],
			'httponly' => $params['httponly'],
			'samesite' => $params['samesite'] ?? 'Lax',
		));
	}

	session_destroy();
}
