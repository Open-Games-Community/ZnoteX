<?php

/**
 * config.php's old single client_download/client_download_linux keys, in the
 * same shape as a downloads.entries row - so they can be merged/normalized
 * through the exact same code path as any custom entry an admin adds.
 */
function znote_download_legacy_entries(array $config): array {
	$clientVersion = isset($config['client']) ? ((int)$config['client'] / 100) : '';

	return array(
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
}

/** One config.php downloads.entries row, normalized - or null if it has no usable key. */
function znote_download_normalize_entry($entry, array $legacy): ?array {
	if (!is_array($entry)) {
		return null;
	}

	$key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($entry['key'] ?? '')));
	if ($key === '') {
		return null;
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

	return $item;
}

/** Windows/Linux client links an admin never added to downloads.entries at all - so they still show up if config.php sets a URL for them. */
function znote_download_append_missing_legacy(array $normalized, array $legacy, bool $includeDisabled): array {
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

function znote_download_entries(bool $includeDisabled = false): array {
	global $config;

	$entries = $config['downloads']['entries'] ?? array();
	if (!is_array($entries)) {
		$entries = array();
	}

	$legacy = znote_download_legacy_entries($config);

	$normalized = array();
	foreach ($entries as $entry) {
		$item = znote_download_normalize_entry($entry, $legacy);
		if ($item === null) {
			continue;
		}
		if ($includeDisabled || ($item['enabled'] && $item['url'] !== '')) {
			$normalized[] = $item;
		}
	}

	return znote_download_append_missing_legacy($normalized, $legacy, $includeDisabled);
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
