<?php

function znote_download_entries(bool $includeDisabled = false): array {
	global $config;

	$entries = $config['downloads']['entries'] ?? array();
	if (!is_array($entries)) {
		$entries = array();
	}

	$clientVersion = isset($config['client']) ? ((int)$config['client'] / 100) : '';
	$legacy = array(
		'windows_client' => array(
			'label' => t('downloads.win', ['version' => $clientVersion]),
			'url' => (string)($config['client_download'] ?? ''),
			'section' => 'official',
			'image' => '',
			'description' => '',
			'enabled' => !empty($config['client_download']),
		),
		'linux_client' => array(
			'label' => t('downloads.linux', ['version' => $clientVersion]),
			'url' => (string)($config['client_download_linux'] ?? ''),
			'section' => 'unsupported',
			'image' => '',
			'description' => '',
			'enabled' => !empty($config['client_download_linux']),
		),
	);

	$normalized = array();
	foreach ($entries as $entry) {
		if (!is_array($entry)) {
			continue;
		}

		$key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($entry['key'] ?? '')));
		if ($key === '') {
			continue;
		}

		$item = array(
			'key' => $key,
			'label' => trim((string)($entry['label'] ?? '')),
			'url' => trim((string)($entry['url'] ?? '')),
			'section' => trim((string)($entry['section'] ?? 'custom')),
			'image' => trim((string)($entry['image'] ?? '')),
			'description' => trim((string)($entry['description'] ?? '')),
			'enabled' => !empty($entry['enabled']) && (string)$entry['enabled'] !== '0',
		);

		if (isset($legacy[$key])) {
			$item = array_merge($item, $legacy[$key]);
		}
		if ($item['label'] === '') {
			$item['label'] = ucwords(str_replace('_', ' ', $key));
		}
		if ($item['section'] === '') {
			$item['section'] = 'custom';
		}

		if ($includeDisabled || ($item['enabled'] && $item['url'] !== '')) {
			$normalized[] = $item;
		}
	}

	foreach ($legacy as $key => $entry) {
		$exists = false;
		foreach ($normalized as $item) {
			if ($item['key'] === $key) {
				$exists = true;
				break;
			}
		}
		if (!$exists && ($includeDisabled || ($entry['enabled'] && $entry['url'] !== ''))) {
			$normalized[] = array_merge(array('key' => $key), $entry);
		}
	}

	return $normalized;
}

function znote_download_entries_by_section(bool $includeDisabled = false): array {
	$sections = array();
	foreach (znote_download_entries($includeDisabled) as $entry) {
		$section = (string)$entry['section'];
		if (!isset($sections[$section])) {
			$sections[$section] = array();
		}
		$sections[$section][] = $entry;
	}
	return $sections;
}
