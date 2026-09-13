<?php

function znote_server_adapter(?string $engine = null): ServerAdapterInterface {
	static $cache = array();

	global $config;
	$engine = $engine ?? (string)($config['ServerEngineReal'] ?? $config['ServerEngine'] ?? 'TFS_10');

	if (isset($cache[$engine])) {
		return $cache[$engine];
	}

	$adapter = match ($engine) {
		'OTHIRE' => new OtHireAdapter(),
		'CANARY' => new CanaryAdapter(),
		'BLACKTEK' => new BlackTekAdapter(),
		default => new TFSAdapter($engine), // TFS_02, TFS_03, TFS_10, TFS_16
	};

	return $cache[$engine] = $adapter;
}
