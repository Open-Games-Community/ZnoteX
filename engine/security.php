<?php

function znote_security_boot(array $options = array()): void {
	$showErrors = !empty($options['display_errors']);
	ini_set('display_errors', $showErrors ? '1' : '0');
	ini_set('display_startup_errors', $showErrors ? '1' : '0');
	ini_set('log_errors', '1');

	if (PHP_SAPI === 'cli' || headers_sent() || ($options['headers_enabled'] ?? true) === false) {
		return;
	}

	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: SAMEORIGIN');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('Permissions-Policy: camera=(), microphone=(), geolocation=(), browsing-topics=()');
	header("Content-Security-Policy: frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
	header('X-Permitted-Cross-Domain-Policies: none');

	if (!empty($options['hsts'])
		&& function_exists('znote_session_request_is_https')
		&& znote_session_request_is_https()
	) {
		$maxAge = max(0, (int)($options['hsts_max_age'] ?? 31536000));
		$value = 'max-age=' . $maxAge;
		if (!empty($options['hsts_include_subdomains'])) {
			$value .= '; includeSubDomains';
		}
		header('Strict-Transport-Security: ' . $value);
	}
}
