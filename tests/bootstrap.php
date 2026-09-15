<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('ACP_ROOT', dirname(__DIR__) . '/admin');

require_once dirname(__DIR__) . '/engine/function/general.php';
require_once dirname(__DIR__) . '/engine/function/extensions.php';
require_once dirname(__DIR__) . '/engine/function/plugins.php';
require_once dirname(__DIR__) . '/engine/function/migrations.php';
require_once dirname(__DIR__) . '/engine/function/updater.php';
require_once dirname(__DIR__) . '/engine/function/payments.php';
require_once dirname(__DIR__) . '/admin/bootstrap.php';

if (!function_exists('t_default')) {
	function t_default(string $key, string $default = ''): string {
		return $default;
	}
}

if (!function_exists('theme_sanitize')) {
	function theme_sanitize(string $name): string {
		$name = strtolower(trim($name));
		return preg_match('/^[a-z0-9_-]{1,64}$/', $name) === 1 ? $name : '';
	}
}
