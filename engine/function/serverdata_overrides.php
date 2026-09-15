<?php

/**
 * Single-record edits layered on top of the bulk-uploaded server data handled
 * by serverdata.php (config.lua / items.xml / monster files). Overrides live
 * in their own table instead of being written into the parsed cache blob, so
 * re-uploading a source file (serverdata_rebuild()/serverdata_store_upload())
 * never discards a manual correction - serverdata_apply_overrides() merges
 * this table over the parsed array every time serverdata_load() is called.
 */

function serverdata_override_sources(): array
{
	return array('config', 'items', 'creatures');
}

function serverdata_override_table_exists(): bool
{
	return znote_table_exists('znote_serverdata_overrides');
}

/** record_key => ['data' => array, 'deleted' => bool] for one source. */
function serverdata_override_all(string $source): array
{
	if (!serverdata_override_table_exists()) {
		return array();
	}

	$rows = db()->fetchAll(
		"SELECT `record_key`, `data`, `deleted` FROM `znote_serverdata_overrides` WHERE `source` = ? ORDER BY `record_key` ASC;",
		array($source)
	);

	$out = array();
	if (is_array($rows)) {
		foreach ($rows as $row) {
			$decoded = json_decode((string)($row['data'] ?? ''), true);
			$out[(string)$row['record_key']] = array(
				'data'    => is_array($decoded) ? $decoded : array(),
				'deleted' => !empty($row['deleted']),
			);
		}
	}

	return $out;
}

function serverdata_override_get(string $source, string $key)
{
	$all = serverdata_override_all($source);

	return $all[$key] ?? null;
}

/** Edit an existing record or add a brand-new one. Also un-deletes a previously removed key. */
function serverdata_override_set(string $source, string $key, array $data, string $updatedBy = ''): bool
{
	if (!serverdata_override_table_exists() || !in_array($source, serverdata_override_sources(), true) || $key === '') {
		return false;
	}

	$json = (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	return db()->execute("
		INSERT INTO `znote_serverdata_overrides` (`source`, `record_key`, `data`, `deleted`, `updated_by`, `updated_at`)
		VALUES (?, ?, ?, 0, ?, ?)
		ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `deleted` = 0, `updated_by` = VALUES(`updated_by`), `updated_at` = VALUES(`updated_at`);
	", array($source, $key, $json, $updatedBy, time()));
}

/**
 * Tombstones a record instead of deleting the row, so it keeps winning over
 * whatever the base cache still has for that key (e.g. it came from the
 * uploaded XML too). serverdata_override_set() on the same key clears the
 * tombstone again.
 */
function serverdata_override_delete(string $source, string $key, string $updatedBy = ''): bool
{
	if (!serverdata_override_table_exists() || $key === '') {
		return false;
	}

	return db()->execute("
		INSERT INTO `znote_serverdata_overrides` (`source`, `record_key`, `data`, `deleted`, `updated_by`, `updated_at`)
		VALUES (?, ?, '{}', 1, ?, ?)
		ON DUPLICATE KEY UPDATE `deleted` = 1, `updated_by` = VALUES(`updated_by`), `updated_at` = VALUES(`updated_at`);
	", array($source, $key, $updatedBy, time()));
}

function serverdata_apply_overrides_config(array $baseData, array $overrides): array
{
	foreach ($overrides as $key => $row) {
		if ($row['deleted'] || !array_key_exists('value', $row['data'])) {
			continue;
		}
		$baseData[$key] = $row['data']['value'];
	}
	return $baseData;
}

function serverdata_apply_overrides_creatures(array $baseData, array $overrides): array
{
	$byName = array();
	foreach ($baseData as $i => $row) {
		if (isset($row['name'])) {
			$byName[$row['name']] = $i;
		}
	}

	foreach ($overrides as $key => $row) {
		if ($row['deleted']) {
			if (isset($byName[$key])) {
				unset($baseData[$byName[$key]]);
			}
			continue;
		}

		$record         = $row['data'];
		$record['name'] = $key;

		if (isset($byName[$key])) {
			$baseData[$byName[$key]] = $record;
		} else {
			$baseData[] = $record;
		}
	}

	$baseData = array_values($baseData);
	usort($baseData, static function (array $a, array $b): int {
		return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
	});

	return $baseData;
}

function serverdata_apply_overrides_items(array $baseData, array $overrides): array
{
	foreach ($overrides as $key => $row) {
		$parts = explode(':', $key, 2);
		if (count($parts) !== 2) {
			continue;
		}
		[$type, $id] = $parts;

		if ($row['deleted']) {
			unset($baseData[$type][$id]);
			continue;
		}

		$baseData[$type][$id] = $row['data'];
	}
	return $baseData;
}

/**
 * Merges the overrides table over a parsed serverdata array. Called from
 * serverdata_load() so every existing reader (items.php, creatures.php, the
 * public serverinfo.php, monster_loot.php's derived cache, ...) sees edits
 * for free.
 */
function serverdata_apply_overrides(string $source, $baseData)
{
	if (!in_array($source, serverdata_override_sources(), true)) {
		return $baseData;
	}

	$overrides = serverdata_override_all($source);
	if (!$overrides) {
		return $baseData;
	}

	$baseData = is_array($baseData) ? $baseData : array();

	switch ($source) {
		case 'config':    return serverdata_apply_overrides_config($baseData, $overrides);
		case 'creatures': return serverdata_apply_overrides_creatures($baseData, $overrides);
		case 'items':     return serverdata_apply_overrides_items($baseData, $overrides);
		default:          return $baseData;
	}
}
