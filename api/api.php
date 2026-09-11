<?php
// PHP version check
if (PHP_VERSION_ID < 80100) {
    die('PHP 8.1 or higher is required.');
}

if (!isset($filepath)) {
    $filepath = '../';
}

$version = '2.0.1';

ob_start();
require_once $filepath.'config.php';
require_once $filepath.'engine/session.php';
require_once $filepath.'engine/security.php';
znote_session_start((array)($config['session'] ?? array()));
znote_security_boot((array)($config['security'] ?? array()));

$sessionPrefix = $config['session_prefix'];

$config['ServerEngineReal'] = $config['ServerEngine'] ?? 'TFS_10';
if (in_array($config['ServerEngineReal'], array('TFS_16', 'CANARY'), true)) {
	$config['ServerEngine'] = 'TFS_10';
	$config['TFSVersion'] = 'TFS_10';
}
if ($config['ServerEngineReal'] === 'CANARY') {
	$config['twoFactorAuthenticator'] = false;
}

require_once $filepath.'engine/database/connect.php';
require_once $filepath.'engine/function/general.php';
require_once $filepath.'engine/function/cache.php';

// Default API config
$config['api']['debug'] ??= false;

$response = [
	'version' => [
		'znote' => $version,
		'ot'    => $config['ServerEngine'] ?? null
	],
];

if (isset($moduleVersion)) {
	$response['version']['module'] = $moduleVersion;
}

function UseClass($name = false, $module = false, $path = false) {
	if ($name === false) {
		throw new InvalidArgumentException('UseClass(): class parameter is false.');
	}

	$names = is_array($name) ? $name : [$name];

	foreach ($names as $class) {
		$mod = $module ?: $class;
		$file = $path
			? "{$path}/{$class}.php"
			: __DIR__ . "/modules/base/{$mod}/class/{$class}.php";

		if (!file_exists($file)) {
			throw new RuntimeException("Class file not found: {$file}");
		}

		require_once $file;
	}
}

function SendResponse(array $response): void {
	global $config;

	if ($config['api']['debug']) {
		data_dump($response, false, "Response (debug mode)");
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($response);
}
?>
