<?php


function serverdata_override_sources(): array
{
	return array('config', 'items', 'creatures');
}

function serverdata_override_table_exists(): bool
{
	return znote_table_exists('znote_serverdata_overrides');
}

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

function serverdata_apply_overrides(string $source, $baseData)
{
	if (!in_array($source, serverdata_override_sources(), true)) {
		return $baseData;
	}

	$overrides = serverdata_override_all($source);
	if (!$overrides) {
		return $baseData;
	}

	if ($source === 'config') {
		$result = is_array($baseData) ? $baseData : array();
		foreach ($overrides as $key => $row) {
			if ($row['deleted'] || !array_key_exists('value', $row['data'])) {
				continue;
			}
			$result[$key] = $row['data']['value'];
		}
		return $result;
	}

	if ($source === 'creatures') {
		$list   = is_array($baseData) ? $baseData : array();
		$byName = array();
		foreach ($list as $i => $row) {
			if (isset($row['name'])) {
				$byName[$row['name']] = $i;
			}
		}

		foreach ($overrides as $key => $row) {
			if ($row['deleted']) {
				if (isset($byName[$key])) {
					unset($list[$byName[$key]]);
				}
				continue;
			}

			$record         = $row['data'];
			$record['name'] = $key;

			if (isset($byName[$key])) {
				$list[$byName[$key]] = $record;
			} else {
				$list[] = $record;
			}
		}

		$list = array_values($list);
		usort($list, static function (array $a, array $b): int {
			return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
		});

		return $list;
	}

	if ($source === 'items') {
		$result = is_array($baseData) ? $baseData : array();
		foreach ($overrides as $key => $row) {
			$parts = explode(':', $key, 2);
			if (count($parts) !== 2) {
				continue;
			}
			[$type, $id] = $parts;

			if ($row['deleted']) {
				unset($result[$type][$id]);
				continue;
			}

			$result[$type][$id] = $row['data'];
		}
		return $result;
	}

	return $baseData;
}
