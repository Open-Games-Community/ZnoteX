<?php

function znote_security_bool(array $options, string $key, bool $default): bool {
	if (!array_key_exists($key, $options)) {
		return $default;
	}

	$value = $options[$key];
	if (is_bool($value)) {
		return $value;
	}
	if (is_int($value)) {
		return $value !== 0;
	}

	$value = strtolower(trim((string)$value));
	if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {
		return true;
	}
	if (in_array($value, array('0', 'false', 'no', 'off', ''), true)) {
		return false;
	}

	return $default;
}

function znote_security_header_value($value): string {
	return trim(str_replace(array("\r", "\n"), '', (string)$value));
}

function znote_security_send_header(string $name, $value): void {
	$value = znote_security_header_value($value);
	if ($value === '') {
		header_remove($name);
		return;
	}

	header($name . ': ' . $value);
}

function znote_security_remove_browser_headers(): void {
	foreach (array(
		'X-Content-Type-Options',
		'X-Frame-Options',
		'Referrer-Policy',
		'Permissions-Policy',
		'Content-Security-Policy',
		'X-Permitted-Cross-Domain-Policies',
		'Strict-Transport-Security',
	) as $header) {
		header_remove($header);
	}
}

function znote_security_boot(array $options = array()): void {
	$showErrors = znote_security_bool($options, 'display_errors', false);
	ini_set('display_errors', $showErrors ? '1' : '0');
	ini_set('display_startup_errors', $showErrors ? '1' : '0');
	ini_set('log_errors', '1');

	if (PHP_SAPI === 'cli' || headers_sent()) {
		return;
	}

	if (!znote_security_bool($options, 'headers_enabled', true)) {
		znote_security_remove_browser_headers();
		return;
	}

	if (znote_security_bool($options, 'content_type_options', true)) {
		znote_security_send_header('X-Content-Type-Options', 'nosniff');
	} else {
		header_remove('X-Content-Type-Options');
	}

	znote_security_send_header('X-Frame-Options', $options['frame_options'] ?? 'SAMEORIGIN');
	znote_security_send_header('Referrer-Policy', $options['referrer_policy'] ?? 'strict-origin-when-cross-origin');
	znote_security_send_header('Permissions-Policy', $options['permissions_policy'] ?? 'camera=(), microphone=(), geolocation=(), browsing-topics=()');
	znote_security_send_header('Content-Security-Policy', $options['content_security_policy'] ?? "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");

	if (znote_security_bool($options, 'cross_domain_policy', true)) {
		znote_security_send_header('X-Permitted-Cross-Domain-Policies', 'none');
	} else {
		header_remove('X-Permitted-Cross-Domain-Policies');
	}

	if (znote_security_bool($options, 'hsts', false)
		&& function_exists('znote_session_request_is_https')
		&& znote_session_request_is_https()
	) {
		$maxAge = max(0, (int)($options['hsts_max_age'] ?? 31536000));
		$value = 'max-age=' . $maxAge;
		if (znote_security_bool($options, 'hsts_include_subdomains', false)) {
			$value .= '; includeSubDomains';
		}
		znote_security_send_header('Strict-Transport-Security', $value);
	} else {
		header_remove('Strict-Transport-Security');
	}
}
