<?php
/**
 * Title: Convert SQL
 * Icon: fa-exchange
 * Group: Settings
 * Order: 40
 * Description: Upload a MyAAC or Gesior2012 SQL dump and download a ZnoteX conversion SQL.
 * Tested with a clean MyAAC SQL (31 players test) + custom tables inserted 
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_convert_table_exists(string $table): bool {
	return mysql_select_single("SHOW TABLES LIKE '" . esc($table) . "'") !== false;
}

function acp_convert_column_exists(string $table, string $column): bool {
	return mysql_select_single("
		SHOW COLUMNS FROM `" . esc($table) . "`
		LIKE '" . esc($column) . "';
	") !== false;
}

function acp_convert_count(string $table, string $where = '1=1'): int {
	if (!acp_convert_table_exists($table)) {
		return 0;
	}
	return acp_count("SELECT COUNT(*) AS `c` FROM `" . esc($table) . "` WHERE {$where};");
}

function acp_convert_scalar(string $sql, string $key = 'v') {
	$row = mysql_select_single($sql);
	return is_array($row) ? ($row[$key] ?? reset($row)) : null;
}

function acp_convert_insert(string $table, array $data): bool {
	$fields = [];
	$values = [];
	foreach ($data as $key => $value) {
		$fields[] = '`' . esc($key) . '`';
		$values[] = is_int($value) ? (string)$value : "'" . esc((string)$value) . "'";
	}
	return mysql_insert("INSERT INTO `" . esc($table) . "` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ");");
}

function acp_convert_ensure_tables(): void {
	mysql_update("
		CREATE TABLE IF NOT EXISTS `znote_pages` (
		  `id` int NOT NULL AUTO_INCREMENT,
		  `slug` varchar(64) NOT NULL,
		  `title` varchar(100) NOT NULL,
		  `body` mediumtext NOT NULL,
		  `created` int NOT NULL DEFAULT '0',
		  `updated` int NOT NULL DEFAULT '0',
		  `player_id` int NOT NULL DEFAULT '0',
		  `access` tinyint NOT NULL DEFAULT '0',
		  `active` tinyint NOT NULL DEFAULT '1',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `slug` (`slug`)
		) ENGINE=InnoDB;
	");

	mysql_update("
		CREATE TABLE IF NOT EXISTS `znote_convert_map` (
		  `id` int NOT NULL AUTO_INCREMENT,
		  `source` varchar(32) NOT NULL,
		  `source_table` varchar(64) NOT NULL,
		  `source_id` varchar(64) NOT NULL,
		  `target_table` varchar(64) NOT NULL,
		  `target_id` int NOT NULL,
		  `created` int NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `source_row` (`source`, `source_table`, `source_id`, `target_table`)
		) ENGINE=InnoDB;
	");

	mysql_update("
		CREATE TABLE IF NOT EXISTS `znote_legacy_tables` (
		  `id` int NOT NULL AUTO_INCREMENT,
		  `source` varchar(32) NOT NULL,
		  `table_name` varchar(64) NOT NULL,
		  `schema_sql` longtext NOT NULL,
		  `row_count` int NOT NULL DEFAULT '0',
		  `captured` int NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `source_table` (`source`, `table_name`)
		) ENGINE=InnoDB;
	");

	mysql_update("
		CREATE TABLE IF NOT EXISTS `znote_legacy_rows` (
		  `id` bigint NOT NULL AUTO_INCREMENT,
		  `source` varchar(32) NOT NULL,
		  `table_name` varchar(64) NOT NULL,
		  `source_pk` varchar(128) NOT NULL DEFAULT '',
		  `row_json` longtext NOT NULL,
		  `captured` int NOT NULL,
		  PRIMARY KEY (`id`),
		  KEY `source_table` (`source`, `table_name`),
		  KEY `source_pk` (`source`, `table_name`, `source_pk`)
		) ENGINE=InnoDB;
	");

	if (!acp_convert_column_exists('znote_legacy_tables', 'schema_sql')) {
		mysql_update("
			ALTER TABLE `znote_legacy_tables`
			ADD `schema_sql` longtext NULL AFTER `table_name`;
		");
	}
}

function acp_convert_slug(string $value, string $fallback): string {
	$value = strtolower(trim($value));
	$value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
	$value = trim((string)$value, '-_');
	if ($value === '') {
		$value = $fallback;
	}
	return substr($value, 0, 64);
}

function acp_convert_map_get(string $source, string $sourceTable, $sourceId, string $targetTable): int {
	if (!acp_convert_table_exists('znote_convert_map')) {
		return 0;
	}
	$row = mysql_select_single("
		SELECT `target_id`
		FROM `znote_convert_map`
		WHERE `source` = '" . esc($source) . "'
		AND `source_table` = '" . esc($sourceTable) . "'
		AND `source_id` = '" . esc((string)$sourceId) . "'
		AND `target_table` = '" . esc($targetTable) . "'
		LIMIT 1;
	");
	return is_array($row) ? (int)$row['target_id'] : 0;
}

function acp_convert_map_set(string $source, string $sourceTable, $sourceId, string $targetTable, int $targetId): void {
	if ($targetId <= 0 || !acp_convert_table_exists('znote_convert_map')) {
		return;
	}
	mysql_insert("
		INSERT INTO `znote_convert_map`
			(`source`, `source_table`, `source_id`, `target_table`, `target_id`, `created`)
		VALUES (
			'" . esc($source) . "',
			'" . esc($sourceTable) . "',
			'" . esc((string)$sourceId) . "',
			'" . esc($targetTable) . "',
			{$targetId},
			" . time() . "
		)
		ON DUPLICATE KEY UPDATE `target_id` = VALUES(`target_id`);
	");
}

function acp_convert_config_set(string $key, string $value): bool {
	if (!acp_convert_table_exists('znote_config')) {
		return false;
	}
	return mysql_insert("
		INSERT INTO `znote_config` (`key`, `value`)
		VALUES ('" . esc(substr($key, 0, 64)) . "', '" . esc($value) . "')
		ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
	");
}

function acp_convert_legacy_tables(string $source): array {
	$prefix = $source === 'myaac' ? 'myaac_' : 'z_';
	$rows = mysql_select_multi('SHOW TABLES;') ?: [];
	$tables = [];

	foreach ($rows as $row) {
		$table = (string)reset($row);
		if (strpos($table, $prefix) === 0) {
			$tables[] = $table;
		}
	}

	sort($tables);
	return $tables;
}

function acp_convert_row_pk(string $table, array $row): string {
	foreach (['id', 'account_id', 'player_id', 'name', 'key'] as $key) {
		if (array_key_exists($key, $row)) {
			return (string)$row[$key];
		}
	}
	return substr(sha1($table . '|' . json_encode($row)), 0, 40);
}

function acp_convert_archive_legacy(string $source): int {
	acp_convert_ensure_tables();
	$archived = 0;
	$captured = time();

	foreach (acp_convert_legacy_tables($source) as $table) {
		$count = acp_convert_count($table);
		mysql_insert("
			INSERT INTO `znote_legacy_tables` (`source`, `table_name`, `schema_sql`, `row_count`, `captured`)
			VALUES ('" . esc($source) . "', '" . esc($table) . "', '', {$count}, {$captured})
			ON DUPLICATE KEY UPDATE `schema_sql` = VALUES(`schema_sql`), `row_count` = VALUES(`row_count`), `captured` = VALUES(`captured`);
		");
		mysql_delete("
			DELETE FROM `znote_legacy_rows`
			WHERE `source` = '" . esc($source) . "'
			AND `table_name` = '" . esc($table) . "';
		");

		$rows = mysql_select_multi("SELECT * FROM `" . esc($table) . "`;") ?: [];
		foreach ($rows as $row) {
			$pk = acp_convert_row_pk($table, $row);
			$json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($json === false) {
				$json = '{}';
			}
			if (acp_convert_insert('znote_legacy_rows', [
				'source' => $source,
				'table_name' => $table,
				'source_pk' => substr($pk, 0, 128),
				'row_json' => $json,
				'captured' => $captured,
			])) {
				$archived++;
			}
		}
	}

	return $archived;
}

function acp_convert_sql_literal($value): string {
	return $value === null ? 'NULL' : "'" . esc((string)$value) . "'";
}

function acp_convert_menu_category_labels(int $category): array {
	return match ($category) {
		1 => ['Home', 'News', 'Latest News'],
		2 => ['Account'],
		3 => ['Community'],
		4 => ['Community', 'Forum'],
		5 => ['Library'],
		6 => ['Shop'],
		default => [],
	};
}

function acp_convert_dump_menu_parent_sql(int $category): string {
	$labels = acp_convert_menu_category_labels($category);
	if (!$labels) {
		return 'NULL';
	}

	$literals = array_map('acp_convert_sql_literal', $labels);
	return "(SELECT `p`.`id` FROM `znote_menu` AS `p`
  WHERE `p`.`location` = 'main'
  AND `p`.`parent_id` = 0
  AND `p`.`label` IN (" . implode(', ', $literals) . ")
  ORDER BY FIELD(`p`.`label`, " . implode(', ', $literals) . ")
  LIMIT 1)";
}

function acp_convert_menu_parent_id(int $category): int {
	$labels = acp_convert_menu_category_labels($category);
	if (!$labels) {
		return 0;
	}

	$literals = array_map('acp_convert_sql_literal', $labels);
	return (int)acp_convert_scalar("SELECT `id` AS `v`
		FROM `znote_menu`
		WHERE `location` = 'main'
		AND `parent_id` = 0
		AND `label` IN (" . implode(', ', $literals) . ")
		ORDER BY FIELD(`label`, " . implode(', ', $literals) . ")
		LIMIT 1;");
}

function acp_convert_dump_split(string $sql): array {
	$statements = [];
	$buf = '';
	$quote = '';
	$len = strlen($sql);

	for ($i = 0; $i < $len; $i++) {
		$ch = $sql[$i];
		$next = ($i + 1 < $len) ? $sql[$i + 1] : '';

		if ($quote === '' && $ch === '-' && $next === '-') {
			while ($i < $len && $sql[$i] !== "\n") $i++;
			continue;
		}
		if ($quote === '' && $ch === '#') {
			while ($i < $len && $sql[$i] !== "\n") $i++;
			continue;
		}
		if ($quote === '' && $ch === '/' && $next === '*') {
			$i += 2;
			while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++;
			$i++;
			continue;
		}

		$buf .= $ch;

		if ($quote !== '') {
			if ($ch === '\\') {
				if ($i + 1 < $len) {
					$buf .= $sql[++$i];
				}
				continue;
			}
			if ($ch === $quote) {
				if ($quote === "'" && $next === "'") {
					$buf .= $sql[++$i];
					continue;
				}
				$quote = '';
			}
			continue;
		}

		if ($ch === "'" || $ch === '"' || $ch === '`') {
			$quote = $ch;
			continue;
		}

		if ($ch === ';') {
			$statement = trim(substr($buf, 0, -1));
			if ($statement !== '') {
				$statements[] = $statement;
			}
			$buf = '';
		}
	}

	$tail = trim($buf);
	if ($tail !== '') {
		$statements[] = $tail;
	}

	return $statements;
}

function acp_convert_dump_columns(string $statement): array {
	if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*)\)\s*(?:ENGINE|DEFAULT|CHARSET|COLLATE|$)/is', $statement, $m)) {
		return [];
	}

	$columns = [];
	foreach (preg_split('/\R/', $m[2]) ?: [] as $line) {
		if (preg_match('/^\s*`([^`]+)`/', $line, $cm)) {
			$columns[] = $cm[1];
		}
	}

	return [$m[1], $columns];
}

function acp_convert_dump_column_defs(string $statement): array {
	if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*)\)\s*(?:ENGINE|DEFAULT|CHARSET|COLLATE|$)/is', $statement, $m)) {
		return [];
	}

	$defs = [];
	foreach (preg_split('/\R/', $m[2]) ?: [] as $line) {
		$line = trim($line);
		if (!preg_match('/^`([^`]+)`\s+(.+?)(?:,)?$/', $line, $cm)) {
			continue;
		}
		$defs[$cm[1]] = '`' . $cm[1] . '` ' . rtrim($cm[2], ',');
	}

	return [$m[1], $defs];
}

function acp_convert_dump_split_csv(string $text): array {
	$items = [];
	$buf = '';
	$quote = '';
	$depth = 0;
	$len = strlen($text);

	for ($i = 0; $i < $len; $i++) {
		$ch = $text[$i];
		$next = ($i + 1 < $len) ? $text[$i + 1] : '';

		if ($quote !== '') {
			$buf .= $ch;
			if ($ch === '\\') {
				if ($i + 1 < $len) {
					$buf .= $text[++$i];
				}
				continue;
			}
			if ($ch === $quote) {
				if ($quote === "'" && $next === "'") {
					$buf .= $text[++$i];
					continue;
				}
				$quote = '';
			}
			continue;
		}

		if ($ch === "'" || $ch === '"' || $ch === '`') {
			$quote = $ch;
			$buf .= $ch;
			continue;
		}
		if ($ch === '(') $depth++;
		if ($ch === ')') $depth--;

		if ($ch === ',' && $depth === 0) {
			$items[] = trim($buf);
			$buf = '';
			continue;
		}

		$buf .= $ch;
	}

	if (trim($buf) !== '') {
		$items[] = trim($buf);
	}

	return $items;
}

function acp_convert_dump_tuples(string $values): array {
	$tuples = [];
	$buf = '';
	$quote = '';
	$depth = 0;
	$len = strlen($values);

	for ($i = 0; $i < $len; $i++) {
		$ch = $values[$i];
		$next = ($i + 1 < $len) ? $values[$i + 1] : '';

		if ($quote !== '') {
			$buf .= $ch;
			if ($ch === '\\') {
				if ($i + 1 < $len) {
					$buf .= $values[++$i];
				}
				continue;
			}
			if ($ch === $quote) {
				if ($quote === "'" && $next === "'") {
					$buf .= $values[++$i];
					continue;
				}
				$quote = '';
			}
			continue;
		}

		if ($ch === "'" || $ch === '"') {
			$quote = $ch;
			$buf .= $ch;
			continue;
		}
		if ($ch === '(') {
			if ($depth > 0) $buf .= $ch;
			$depth++;
			continue;
		}
		if ($ch === ')') {
			$depth--;
			if ($depth === 0) {
				$tuples[] = $buf;
				$buf = '';
				continue;
			}
			$buf .= $ch;
			continue;
		}
		if ($depth > 0) {
			$buf .= $ch;
		}
	}

	return $tuples;
}

function acp_convert_dump_value(string $value) {
	$value = trim($value);
	if (strcasecmp($value, 'NULL') === 0) {
		return null;
	}
	if (preg_match('/^(?:0x[0-9a-f]+|x\'[0-9a-f]*\'|b\'[01]*\')$/i', $value)) {
		return ['sql' => $value, 'archive' => $value];
	}
	if (preg_match('/^-?\d+$/', $value)) {
		return (int)$value;
	}
	if (
		(strlen($value) >= 2) &&
		(($value[0] === "'" && substr($value, -1) === "'") || ($value[0] === '"' && substr($value, -1) === '"'))
	) {
		$value = substr($value, 1, -1);
		$value = str_replace(["\\r", "\\n", "\\t", "\\0", "\\'", '\\"', "\\\\"], ["\r", "\n", "\t", "\0", "'", '"', "\\"], $value);
		$value = str_replace("''", "'", $value);
		return $value;
	}
	return $value;
}

function acp_convert_dump_model(string $sql): array {
	$model = ['tables' => [], 'columns' => [], 'column_defs' => [], 'creates' => [], 'warnings' => []];

	foreach (acp_convert_dump_split($sql) as $statement) {
		$create = acp_convert_dump_columns($statement);
		if ($create) {
			$model['columns'][$create[0]] = $create[1];
			$defs = acp_convert_dump_column_defs($statement);
			if ($defs) {
				$model['column_defs'][$defs[0]] = $defs[1];
			}
			$model['creates'][$create[0]] = $statement . ';';
			if (!isset($model['tables'][$create[0]])) {
				$model['tables'][$create[0]] = [];
			}
			continue;
		}

		if (!preg_match('/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?\s*(?:\((.*?)\))?\s+VALUES\s*(.*)$/is', $statement, $m)) {
			continue;
		}

		$table = $m[1];
		$insertColumns = (string)($m[2] ?? '');
		$columns = [];
		if (trim($insertColumns) !== '') {
			foreach (acp_convert_dump_split_csv($insertColumns) as $col) {
				$columns[] = trim($col, " `\t\n\r\0\x0B");
			}
		} else {
			$columns = $model['columns'][$table] ?? [];
		}

		if (!$columns) {
			$model['warnings'][] = 'Skipped INSERT for ' . $table . ' because no column list was available.';
			continue;
		}

		foreach (acp_convert_dump_tuples((string)$m[3]) as $tuple) {
			$values = acp_convert_dump_split_csv($tuple);
			$row = [];
			foreach ($columns as $i => $column) {
				$row[$column] = array_key_exists($i, $values) ? acp_convert_dump_value($values[$i]) : null;
			}
			$model['tables'][$table][] = $row;
		}
	}

	return $model;
}

function acp_convert_row_value(array $row, array $keys, $default = '') {
	foreach ($keys as $key) {
		if (array_key_exists($key, $row) && $row[$key] !== null) {
			return $row[$key];
		}
	}
	return $default;
}

function acp_convert_dump_source(array $model, string $requested): string {
	if ($requested === 'myaac' || $requested === 'gesior') {
		return $requested;
	}
	foreach (array_keys($model['tables']) as $table) {
		if (strpos($table, 'myaac_') === 0) return 'myaac';
		if (strpos($table, 'z_') === 0) return 'gesior';
	}
	return 'legacy';
}

function acp_convert_dump_stats(array $model, string $source): array {
	$stats = ['tables' => count($model['tables']), 'rows' => 0, 'known' => 0, 'custom' => 0];
	$known = [
		'accounts', 'players', 'myaac_news', 'myaac_changelog', 'myaac_forum_boards',
		'myaac_forum', 'myaac_pages', 'myaac_gallery', 'myaac_menu', 'myaac_config',
		'myaac_settings', 'z_news_big', 'z_news_tickers', 'z_forum_boards', 'z_forum',
		'z_pages', 'z_config',
	];

	foreach ($model['tables'] as $table => $rows) {
		$count = count($rows);
		$stats['rows'] += $count;
		if (in_array($table, $known, true)) {
			$stats['known'] += $count;
		} else {
			$stats['custom'] += $count;
		}
	}

	return $stats;
}

function acp_convert_dump_player_name(array $model, int $playerId): string {
	if ($playerId <= 0 || empty($model['tables']['players'])) {
		return '';
	}

	foreach ($model['tables']['players'] as $player) {
		if ((int)acp_convert_row_value($player, ['id'], 0) === $playerId) {
			return substr((string)acp_convert_row_value($player, ['name'], ''), 0, 50);
		}
	}

	return '';
}

function acp_convert_dump_sql_values(array $rows, array $columns): string {
	$out = [];
	foreach ($rows as $row) {
		$values = [];
		foreach ($columns as $column) {
			$value = $row[$column] ?? null;
			$values[] = acp_convert_dump_sql_value($value);
		}
		$out[] = '(' . implode(', ', $values) . ')';
	}
	return implode(",\n", $out);
}

function acp_convert_dump_sql_value($value): string {
	if (is_array($value) && isset($value['sql'])) {
		return (string)$value['sql'];
	}
	return is_int($value) ? (string)$value : acp_convert_sql_literal($value);
}

function acp_convert_dump_archive_row(array $row): array {
	foreach ($row as $column => $value) {
		if (is_array($value) && isset($value['sql'])) {
			$row[$column] = (string)($value['archive'] ?? $value['sql']);
		}
	}
	return $row;
}

function acp_convert_dump_integer_definition(string $definition, string $column, array $rows): array {
	$pattern = '/^(`' . preg_quote($column, '/') . '`\s+)(tinyint|smallint|mediumint|int|bigint)(\s+unsigned)?(.*)$/i';
	if (!preg_match($pattern, $definition, $match)) {
		return ['definition' => $definition, 'type' => '', 'widened_from' => []];
	}

	$types = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'];
	$signedMax = [127, 32767, 8388607, 2147483647, PHP_INT_MAX];
	$signedMin = [-128, -32768, -8388608, -2147483648, PHP_INT_MIN];
	$unsignedMax = [255, 65535, 16777215, 4294967295, PHP_INT_MAX];
	$type = strtolower($match[2]);
	$unsigned = trim((string)$match[3]) !== '';
	$index = array_search($type, $types, true);
	if ($index === false) {
		return ['definition' => $definition, 'type' => '', 'widened_from' => []];
	}

	$minimum = 0;
	$maximum = 0;
	foreach ($rows as $row) {
		$value = $row[$column] ?? null;
		if (!is_int($value)) {
			continue;
		}
		$minimum = min($minimum, $value);
		$maximum = max($maximum, $value);
	}

	$required = $index;
	while ($required < count($types) - 1) {
		$fitsMinimum = $unsigned ? $minimum >= 0 : $minimum >= $signedMin[$required];
		$fitsMaximum = $maximum <= ($unsigned ? $unsignedMax[$required] : $signedMax[$required]);
		if ($fitsMinimum && $fitsMaximum) {
			break;
		}
		$required++;
	}

	if ($required === $index) {
		return [
			'definition' => $definition,
			'type' => $type,
			'widened_from' => array_slice($types, 0, $index),
		];
	}

	$replacement = $match[1] . $types[$required] . ($unsigned ? ' unsigned' : '') . $match[4];
	return [
		'definition' => $replacement,
		'type' => $types[$required],
		'widened_from' => array_slice($types, 0, $required),
	];
}

function acp_convert_dump_sql_union(array $rows, array $columns): string {
	$out = [];
	foreach ($rows as $index => $row) {
		$values = [];
		foreach ($columns as $column) {
			$value = $row[$column] ?? null;
			$sqlValue = acp_convert_dump_sql_value($value);
			$values[] = $index === 0 ? $sqlValue . ' AS `' . $column . '`' : $sqlValue;
		}
		$out[] = 'SELECT ' . implode(', ', $values);
	}
	return implode("\nUNION ALL\n", $out);
}

function acp_convert_dump_mapped_id_sql(string $source, string $sourceTable, int $sourceId, string $targetTable): string {
	if ($sourceId <= 0) {
		return '0';
	}

	return "COALESCE((SELECT `target_id` FROM `znote_convert_map`"
		. " WHERE `source` = " . acp_convert_sql_literal($source)
		. " AND `source_table` = " . acp_convert_sql_literal($sourceTable)
		. " AND `source_id` = " . acp_convert_sql_literal((string)$sourceId)
		. " AND `target_table` = " . acp_convert_sql_literal($targetTable)
		. " LIMIT 1), {$sourceId})";
}

function acp_convert_dump_reference_value(string $source, string $table, string $column, $value) {
	if (is_array($value) && isset($value['sql'])) {
		return $value;
	}
	$id = (int)$value;
	if ($id <= 0) {
		return $value;
	}

	$accountColumns = ['account_id', 'author_aid', 'last_edit_aid', 'original_account_id', 'bidder_account_id'];
	$playerColumns = ['player_id', 'author_guid'];
	if ($table === 'players' && $column === 'account_id') {
		return ['sql' => acp_convert_dump_mapped_id_sql($source, 'accounts', $id, 'accounts')];
	}
	if ($table !== 'accounts' && in_array($column, $accountColumns, true)) {
		return ['sql' => acp_convert_dump_mapped_id_sql($source, 'accounts', $id, 'accounts')];
	}
	if ($table !== 'players' && in_array($column, $playerColumns, true)) {
		return ['sql' => acp_convert_dump_mapped_id_sql($source, 'players', $id, 'players')];
	}

	return $value;
}

function acp_convert_dump_restore_sql(array $model, string $source): string {
	$out = [];
	$guard = 0;
	$orderedTables = array_keys($model['tables']);
	usort($orderedTables, static function (string $left, string $right): int {
		$priority = ['accounts' => 0, 'players' => 1];
		return ($priority[$left] ?? 2) <=> ($priority[$right] ?? 2);
	});

	foreach ($orderedTables as $table) {
		$rows = $model['tables'][$table];
		if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
			continue;
		}

		$columns = $model['columns'][$table] ?? [];
		$defs = $model['column_defs'][$table] ?? [];
		$create = trim((string)($model['creates'][$table] ?? ''));
		if ($create === '' || !$columns) {
			continue;
		}

		$out[] = "-- Restore source table: {$table}";
		$out[] = $create;

		foreach ($columns as $column) {
			if (!isset($defs[$column]) || !preg_match('/^[a-zA-Z0-9_]+$/', (string)$column)) {
				continue;
			}
			$definition = acp_convert_dump_integer_definition($defs[$column], $column, $rows);
			$effectiveDef = (string)$definition['definition'];
			$guard++;
			$checkVar = '@znote_restore_col_' . $guard;
			$stmtName = 'znote_restore_col_stmt_' . $guard;
			$out[] = "SET {$checkVar} := IF(
  (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = " . acp_convert_sql_literal($table) . "
    AND COLUMN_NAME = " . acp_convert_sql_literal($column) . "
  ) = 0,
  " . acp_convert_sql_literal('ALTER TABLE `' . $table . '` ADD ' . $effectiveDef) . ",
  'SELECT 1'
);
PREPARE {$stmtName} FROM {$checkVar};
EXECUTE {$stmtName};
DEALLOCATE PREPARE {$stmtName};";

			if (!empty($definition['widened_from'])) {
				$guard++;
				$checkVar = '@znote_restore_widen_' . $guard;
				$stmtName = 'znote_restore_widen_stmt_' . $guard;
				$smallerTypes = implode(', ', array_map('acp_convert_sql_literal', $definition['widened_from']));
				$out[] = "SET {$checkVar} := IF(
  EXISTS(
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = " . acp_convert_sql_literal($table) . "
    AND COLUMN_NAME = " . acp_convert_sql_literal($column) . "
    AND DATA_TYPE IN ({$smallerTypes})
  ),
  " . acp_convert_sql_literal('ALTER TABLE `' . $table . '` MODIFY ' . $effectiveDef) . ",
  'SELECT 1'
);
PREPARE {$stmtName} FROM {$checkVar};
EXECUTE {$stmtName};
DEALLOCATE PREPARE {$stmtName};";
			}
		}

		if (!$rows) {
			continue;
		}

		$quotedColumns = array_map(static fn($column) => '`' . $column . '`', $columns);
		$updates = [];
		foreach ($columns as $column) {
			if (($table === 'accounts' || $table === 'players') && $column === 'id') {
				continue;
			}
			$updates[] = '`' . $column . '` = VALUES(`' . $column . '`)';
		}

		if ($table === 'accounts' || $table === 'players') {
			$mapVariable = $table === 'accounts' ? '@znote_import_account_id' : '@znote_import_player_id';
			foreach ($rows as $row) {
				$sourceId = (int)acp_convert_row_value($row, ['id'], 0);
				if ($sourceId <= 0) {
					continue;
				}
				$sourceName = trim((string)acp_convert_row_value($row, ['name'], ''));
				$sameNameSql = $sourceName !== ''
					? "(SELECT `id` FROM `{$table}` WHERE `name` = " . acp_convert_sql_literal($sourceName) . " LIMIT 1),"
					: '';
				$out[] = "SET {$mapVariable} := COALESCE(
  (SELECT `target_id` FROM `znote_convert_map`
   WHERE `source` = " . acp_convert_sql_literal($source) . "
   AND `source_table` = " . acp_convert_sql_literal($table) . "
   AND `source_id` = " . acp_convert_sql_literal((string)$sourceId) . "
   AND `target_table` = " . acp_convert_sql_literal($table) . " LIMIT 1),
  {$sameNameSql}
  IF(EXISTS(SELECT 1 FROM `{$table}` WHERE `id` = {$sourceId}),
    (SELECT `next_id` FROM (SELECT COALESCE(MAX(`id`), 0) + 1 AS `next_id` FROM `{$table}`) AS `available_id`),
    {$sourceId}
  )
);";
				$out[] = "INSERT INTO `znote_convert_map`
  (`source`, `source_table`, `source_id`, `target_table`, `target_id`, `created`)
VALUES (" . acp_convert_sql_literal($source) . ", " . acp_convert_sql_literal($table) . ", "
					. acp_convert_sql_literal((string)$sourceId) . ", " . acp_convert_sql_literal($table)
					. ", {$mapVariable}, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `target_id` = VALUES(`target_id`);";

				$mappedRow = $row;
				$mappedRow['id'] = ['sql' => $mapVariable];
				foreach ($columns as $column) {
					$mappedRow[$column] = acp_convert_dump_reference_value($source, $table, $column, $mappedRow[$column] ?? null);
				}
				$out[] = "INSERT INTO `{$table}` (" . implode(', ', $quotedColumns) . ") VALUES\n"
					. acp_convert_dump_sql_values([$mappedRow], $columns)
					. "\nON DUPLICATE KEY UPDATE " . implode(', ', $updates) . ';';
			}
			continue;
		}

		$mappedRows = [];
		foreach ($rows as $row) {
			foreach ($columns as $column) {
				$row[$column] = acp_convert_dump_reference_value($source, $table, $column, $row[$column] ?? null);
			}
			$mappedRows[] = $row;
		}
		$out[] = "INSERT INTO `{$table}` (" . implode(', ', $quotedColumns) . ") VALUES\n"
			. acp_convert_dump_sql_values($mappedRows, $columns)
			. "\nON DUPLICATE KEY UPDATE " . implode(', ', $updates) . ';';
	}

	return trim(implode("\n\n", $out));
}

function acp_convert_dump_script(array $model, string $requestedSource): string {
	$source = acp_convert_dump_source($model, $requestedSource);
	$captured = time();
	$sql = [];
	$sql[] = "-- ZnoteX uploaded SQL conversion";
	$sql[] = "-- Source detected: {$source}";
	$sql[] = "-- Generated " . date('Y-m-d H:i:s');
	$sql[] = "START TRANSACTION;";
	$sql[] = "";
	$sql[] = file_get_contents('SQL/migrations/2.0.0_pages_and_convert_map.sql') ?: '';
	$sql[] = file_get_contents('SQL/migrations/2.0.0_gallery_image_url.sql') ?: '';
	$restoreSql = acp_convert_dump_restore_sql($model, $source);
	if ($restoreSql !== '') {
		$sql[] = "";
		$sql[] = "-- Restore original source tables, rows and custom columns";
		$sql[] = $restoreSql;
	}

	if (!empty($model['tables']['accounts'])) {
		$rows = [];
		foreach ($model['tables']['accounts'] as $row) {
			$id = (int)acp_convert_row_value($row, ['id'], 0);
			if ($id <= 0) continue;
			$created = (int)acp_convert_row_value($row, ['creation', 'created'], time());
			$rows[] = [
				'account_id' => ['sql' => acp_convert_dump_mapped_id_sql($source, 'accounts', $id, 'accounts')],
				'ip' => 0,
				'created' => $created > 0 ? $created : time(),
				'flag' => '',
			];
		}
		if ($rows) {
			$sql[] = "-- Account compatibility rows";
			$sql[] = "INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `flag`)
SELECT `v`.`account_id`, `v`.`ip`, `v`.`created`, `v`.`flag`
FROM (
" . acp_convert_dump_sql_union($rows, ['account_id', 'ip', 'created', 'flag']) . "
) AS `v`
WHERE NOT EXISTS (
  SELECT 1 FROM `znote_accounts` AS `z` WHERE `z`.`account_id` = `v`.`account_id`
);";
		}
	}

	if (!empty($model['tables']['players'])) {
		$rows = [];
		foreach ($model['tables']['players'] as $row) {
			$id = (int)acp_convert_row_value($row, ['id'], 0);
			if ($id <= 0) continue;
			$rows[] = [
				'player_id' => ['sql' => acp_convert_dump_mapped_id_sql($source, 'players', $id, 'players')],
				'created' => time(),
				'hide_char' => 0,
				'comment' => '',
			];
		}
		if ($rows) {
			$sql[] = "-- Player compatibility rows";
			$sql[] = "INSERT INTO `znote_players` (`player_id`, `created`, `hide_char`, `comment`)
SELECT `v`.`player_id`, `v`.`created`, `v`.`hide_char`, `v`.`comment`
FROM (
" . acp_convert_dump_sql_union($rows, ['player_id', 'created', 'hide_char', 'comment']) . "
) AS `v`
WHERE NOT EXISTS (
  SELECT 1 FROM `znote_players` AS `z` WHERE `z`.`player_id` = `v`.`player_id`
);";
		}
	}

	$newsTable = $source === 'gesior' ? 'z_news_big' : 'myaac_news';
	if (!empty($model['tables'][$newsTable])) {
		$rows = [];
		foreach ($model['tables'][$newsTable] as $row) {
			$hide = (int)acp_convert_row_value($row, ['hide', 'hide_news'], 0);
			if ($hide !== 0) continue;
			$title = substr(trim((string)acp_convert_row_value($row, ['title', 'topic', 'name'], 'Imported news')), 0, 30);
			$body = (string)acp_convert_row_value($row, ['body', 'text'], '');
			$date = (int)acp_convert_row_value($row, ['date', 'time'], time());
			$pid = (int)acp_convert_row_value($row, ['player_id', 'author_id', 'pid'], 0);
			$rows[] = [
				'title' => $title,
				'text' => acp_convert_news_text($body),
				'date' => $date > 0 ? $date : time(),
				'pid' => ['sql' => acp_convert_dump_mapped_id_sql($source, 'players', $pid, 'players')],
			];
		}
		if ($rows) {
			$sql[] = "-- News";
			$sql[] = "INSERT INTO `znote_news` (`title`, `text`, `date`, `pid`)
SELECT `v`.`title`, `v`.`text`, `v`.`date`, `v`.`pid`
FROM (
" . acp_convert_dump_sql_union($rows, ['title', 'text', 'date', 'pid']) . "
) AS `v`
WHERE NOT EXISTS (
  SELECT 1 FROM `znote_news` AS `z` WHERE `z`.`title` = `v`.`title` AND `z`.`date` = `v`.`date`
);";
		}
	}

	$changeTable = $source === 'gesior' ? 'z_news_tickers' : 'myaac_changelog';
	if (!empty($model['tables'][$changeTable])) {
		$rows = [];
		foreach ($model['tables'][$changeTable] as $row) {
			$hide = (int)acp_convert_row_value($row, ['hide', 'hide_ticker'], 0);
			if ($hide !== 0) continue;
			$body = (string)acp_convert_row_value($row, ['body', 'text'], '');
			$text = substr(acp_convert_strip_html($body), 0, 254);
			if ($text === '') continue;
			$date = (int)acp_convert_row_value($row, ['date', 'time'], time());
			$rows[] = ['text' => $text, 'time' => $date > 0 ? $date : time(), 'report_id' => 0, 'status' => (int)acp_convert_row_value($row, ['type'], 0)];
		}
		if ($rows) {
			$sql[] = "-- Changelog / tickers";
			$sql[] = "INSERT INTO `znote_changelog` (`text`, `time`, `report_id`, `status`)
SELECT `v`.`text`, `v`.`time`, `v`.`report_id`, `v`.`status`
FROM (
" . acp_convert_dump_sql_union($rows, ['text', 'time', 'report_id', 'status']) . "
) AS `v`
WHERE NOT EXISTS (
  SELECT 1 FROM `znote_changelog` AS `z` WHERE `z`.`text` = `v`.`text` AND `z`.`time` = `v`.`time`
);";
		}
	}

	if (!empty($model['tables']['myaac_pages'])) {
		$rows = [];
		foreach ($model['tables']['myaac_pages'] as $row) {
			if ((int)acp_convert_row_value($row, ['hide'], 0) !== 0) continue;
			$id = (int)acp_convert_row_value($row, ['id'], 0);
			$slug = acp_convert_slug((string)acp_convert_row_value($row, ['name', 'slug'], ''), 'imported-page-' . $id);
			$title = substr(trim((string)acp_convert_row_value($row, ['title', 'name'], $slug)), 0, 100);
			$date = (int)acp_convert_row_value($row, ['date'], time());
			$rows[] = [
				'slug' => $slug,
				'title' => $title,
				'body' => acp_convert_news_text((string)acp_convert_row_value($row, ['body', 'text'], '')),
				'created' => $date > 0 ? $date : time(),
				'updated' => $date > 0 ? $date : time(),
				'player_id' => ['sql' => acp_convert_dump_mapped_id_sql(
					$source,
					'players',
					(int)acp_convert_row_value($row, ['player_id'], 0),
					'players'
				)],
				'access' => (int)acp_convert_row_value($row, ['access'], 0),
				'active' => 1,
			];
		}
		if ($rows) {
			$sql[] = "-- Custom pages";
			$sql[] = "INSERT INTO `znote_pages` (`slug`, `title`, `body`, `created`, `updated`, `player_id`, `access`, `active`) VALUES\n"
				. acp_convert_dump_sql_values($rows, ['slug', 'title', 'body', 'created', 'updated', 'player_id', 'access', 'active'])
				. "\nON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `body` = VALUES(`body`), `updated` = VALUES(`updated`);";
		}
	}

	$boardTable = $source === 'gesior' ? 'z_forum_boards' : 'myaac_forum_boards';
	$idOffset = $source === 'gesior' ? 2000000 : 1000000;
	if (!empty($model['tables'][$boardTable])) {
		$rows = [];
		foreach ($model['tables'][$boardTable] as $row) {
			$id = (int)acp_convert_row_value($row, ['id'], 0);
			$name = substr(trim((string)acp_convert_row_value($row, ['name', 'title'], 'Imported board')), 0, 50);
			if ($id <= 0 || $name === '') continue;
			$rows[] = [
				'id' => $idOffset + $id,
				'name' => $name,
				'access' => max(1, (int)acp_convert_row_value($row, ['access'], 1)),
				'closed' => (int)acp_convert_row_value($row, ['closed'], 0),
				'hidden' => (int)acp_convert_row_value($row, ['hide', 'hidden'], 0),
				'guild_id' => (int)acp_convert_row_value($row, ['guild', 'guild_id'], 0),
			];
		}
		if ($rows) {
			$sql[] = "-- Forum boards";
			$sql[] = "INSERT INTO `znote_forum` (`id`, `name`, `access`, `closed`, `hidden`, `guild_id`) VALUES\n"
				. acp_convert_dump_sql_values($rows, ['id', 'name', 'access', 'closed', 'hidden', 'guild_id'])
				. "\nON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `access` = VALUES(`access`), `closed` = VALUES(`closed`), `hidden` = VALUES(`hidden`), `guild_id` = VALUES(`guild_id`);";
		}
	}

	$forumTable = !empty($model['tables']['myaac_forum']) ? 'myaac_forum' : (!empty($model['tables']['z_forum']) ? 'z_forum' : '');
	if ($forumTable !== '') {
		$threads = [];
		$posts = [];

		foreach ($model['tables'][$forumTable] as $row) {
			$id = (int)acp_convert_row_value($row, ['id'], 0);
			$firstPost = (int)acp_convert_row_value($row, ['first_post'], 0);
			$playerId = (int)acp_convert_row_value($row, ['author_guid', 'player_id'], 0);
			$mappedPlayerId = ['sql' => acp_convert_dump_mapped_id_sql($source, 'players', $playerId, 'players')];
			$created = (int)acp_convert_row_value($row, ['post_date', 'created', 'date'], time());
			$updated = (int)acp_convert_row_value($row, ['edit_date', 'updated'], $created);
			$text = sanitize((string)acp_convert_row_value($row, ['post_text', 'text'], ''));

			if ($id > 0 && ($firstPost === 0 || $firstPost === $id)) {
				$oldBoard = (int)acp_convert_row_value($row, ['section', 'forum_id'], 1);
				$title = substr(trim((string)acp_convert_row_value($row, ['post_topic', 'title'], 'Imported thread')), 0, 50);
				$threads[] = [
					'id' => $idOffset + $id,
					'forum_id' => $idOffset + max(1, $oldBoard),
					'player_id' => $mappedPlayerId,
					'player_name' => (string)acp_convert_row_value($row, ['author', 'player_name'], acp_convert_dump_player_name($model, $playerId)),
					'title' => $title !== '' ? $title : 'Imported thread',
					'text' => $text,
					'created' => $created > 0 ? $created : time(),
					'updated' => $updated > 0 ? $updated : ($created > 0 ? $created : time()),
					'sticky' => (int)acp_convert_row_value($row, ['sticked', 'sticky'], 0),
					'hidden' => (int)acp_convert_row_value($row, ['hidden', 'hide'], 0),
					'closed' => (int)acp_convert_row_value($row, ['closed'], 0),
				];
			} elseif ($id > 0 && $firstPost > 0) {
				$posts[] = [
					'thread_id' => $idOffset + $firstPost,
					'player_id' => $mappedPlayerId,
					'player_name' => (string)acp_convert_row_value($row, ['author', 'player_name'], acp_convert_dump_player_name($model, $playerId)),
					'text' => $text,
					'created' => $created > 0 ? $created : time(),
					'updated' => $updated > 0 ? $updated : ($created > 0 ? $created : time()),
				];
			}
		}

		if ($threads) {
			$sql[] = "-- Forum threads";
			$sql[] = "INSERT INTO `znote_forum_threads` (`id`, `forum_id`, `player_id`, `player_name`, `title`, `text`, `created`, `updated`, `sticky`, `hidden`, `closed`) VALUES\n"
				. acp_convert_dump_sql_values($threads, ['id', 'forum_id', 'player_id', 'player_name', 'title', 'text', 'created', 'updated', 'sticky', 'hidden', 'closed'])
				. "\nON DUPLICATE KEY UPDATE `forum_id` = VALUES(`forum_id`), `title` = VALUES(`title`), `text` = VALUES(`text`), `updated` = VALUES(`updated`);";
		}
		if ($posts) {
			$sql[] = "-- Forum posts";
			$sql[] = "INSERT INTO `znote_forum_posts` (`thread_id`, `player_id`, `player_name`, `text`, `created`, `updated`)
SELECT `v`.`thread_id`, `v`.`player_id`, `v`.`player_name`, `v`.`text`, `v`.`created`, `v`.`updated`
FROM (
" . acp_convert_dump_sql_union($posts, ['thread_id', 'player_id', 'player_name', 'text', 'created', 'updated']) . "
) AS `v`
WHERE NOT EXISTS (
  SELECT 1 FROM `znote_forum_posts` AS `z`
  WHERE `z`.`thread_id` = `v`.`thread_id`
  AND `z`.`player_id` = `v`.`player_id`
  AND `z`.`created` = `v`.`created`
  AND `z`.`text` = `v`.`text`
);";
		}
	}

	if (!empty($model['tables']['myaac_gallery'])) {
		$rows = [];
		foreach ($model['tables']['myaac_gallery'] as $row) {
			if ((int)acp_convert_row_value($row, ['hide'], 0) !== 0) continue;
			$image = trim((string)acp_convert_row_value($row, ['image', 'url'], ''));
			if ($image === '') continue;
			$comment = (string)acp_convert_row_value($row, ['comment', 'description', 'desc'], '');
			$rows[] = [
				'title' => substr($comment !== '' ? $comment : 'Imported image', 0, 30),
				'desc' => $comment,
				'date' => (int)acp_convert_row_value($row, ['date'], time()),
				'status' => 2,
				'image' => substr($image, 0, 255),
				'delhash' => '',
				'account_id' => ['sql' => acp_convert_dump_mapped_id_sql(
					$source,
					'accounts',
					(int)acp_convert_row_value($row, ['account_id'], 0),
					'accounts'
				)],
			];
		}
		if ($rows) {
			$sql[] = "-- Gallery";
			$sql[] = "INSERT INTO `znote_images` (`title`, `desc`, `date`, `status`, `image`, `delhash`, `account_id`)
SELECT `v`.`title`, `v`.`desc`, `v`.`date`, `v`.`status`, `v`.`image`, `v`.`delhash`, `v`.`account_id`
FROM (
" . acp_convert_dump_sql_union($rows, ['title', 'desc', 'date', 'status', 'image', 'delhash', 'account_id']) . "
) AS `v`
WHERE NOT EXISTS (
  SELECT 1 FROM `znote_images` AS `z` WHERE `z`.`image` = `v`.`image`
);";
		}
	}

	if (!empty($model['tables']['myaac_menu'])) {
		$rows = [];
		foreach ($model['tables']['myaac_menu'] as $row) {
			if ((int)acp_convert_row_value($row, ['enabled'], 1) !== 1) continue;
			$label = substr(trim((string)acp_convert_row_value($row, ['name', 'label'], '')), 0, 64);
			$url = substr(trim((string)acp_convert_row_value($row, ['link', 'url'], '')), 0, 255);
			if ($label === '' || $url === '') continue;
			$rows[] = [
				'location' => 'main',
				'parent_id' => ['sql' => acp_convert_dump_menu_parent_sql((int)acp_convert_row_value($row, ['category'], 0))],
				'label' => $label,
				'url' => $url,
				'icon' => '',
				'target' => (int)acp_convert_row_value($row, ['blank'], 0) === 1 ? '_blank' : '',
				'visibility' => 'all',
				'sort_order' => (int)acp_convert_row_value($row, ['ordering', 'sort_order'], 0),
				'active' => 1,
			];
		}
		if ($rows) {
			$sql[] = "-- Menu";
			$sql[] = "INSERT INTO `znote_menu` (`location`, `parent_id`, `label`, `url`, `icon`, `target`, `visibility`, `sort_order`, `active`)
SELECT `v`.`location`, `v`.`parent_id`, `v`.`label`, `v`.`url`, `v`.`icon`, `v`.`target`, `v`.`visibility`, `v`.`sort_order`, `v`.`active`
FROM (
" . acp_convert_dump_sql_union($rows, ['location', 'parent_id', 'label', 'url', 'icon', 'target', 'visibility', 'sort_order', 'active']) . "
) AS `v`
WHERE `v`.`parent_id` IS NOT NULL
AND NOT EXISTS (
  SELECT 1 FROM `znote_menu` AS `z`
  WHERE `z`.`location` = `v`.`location`
  AND `z`.`label` = `v`.`label`
  AND `z`.`url` = `v`.`url`
);";
		}
	}

	foreach (['myaac_config' => 'legacy:myaac:', 'myaac_settings' => 'legacy:myaac_setting:', 'z_config' => 'legacy:gesior:'] as $configTable => $prefix) {
		if (empty($model['tables'][$configTable])) continue;
		$rows = [];
		foreach ($model['tables'][$configTable] as $row) {
			$key = (string)acp_convert_row_value($row, ['key', 'name', 'config'], '');
			if ($key === '') continue;
			$rows[] = ['key' => substr($prefix . $key, 0, 64), 'value' => (string)acp_convert_row_value($row, ['value'], '')];
		}
		if ($rows) {
			$sql[] = "-- Legacy config: {$configTable}";
			$sql[] = "INSERT INTO `znote_config` (`key`, `value`) VALUES\n"
				. acp_convert_dump_sql_values($rows, ['key', 'value'])
				. "\nON DUPLICATE KEY UPDATE `value` = VALUES(`value`);";
		}
	}

	foreach ($model['tables'] as $table => $rows) {
		$sql[] = "-- Legacy archive: {$table}";
		$schemaSql = (string)($model['creates'][$table] ?? '');
		$sql[] = "INSERT INTO `znote_legacy_tables` (`source`, `table_name`, `schema_sql`, `row_count`, `captured`) VALUES ("
			. acp_convert_sql_literal($source) . ', ' . acp_convert_sql_literal($table) . ', ' . acp_convert_sql_literal($schemaSql) . ', ' . count($rows) . ", {$captured})
ON DUPLICATE KEY UPDATE `schema_sql` = VALUES(`schema_sql`), `row_count` = VALUES(`row_count`), `captured` = VALUES(`captured`);";
		$sql[] = "DELETE FROM `znote_legacy_rows` WHERE `source` = " . acp_convert_sql_literal($source)
			. " AND `table_name` = " . acp_convert_sql_literal($table) . ';';
		foreach ($rows as $row) {
			$pk = substr(acp_convert_row_pk($table, $row), 0, 128);
			$json = json_encode(acp_convert_dump_archive_row($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
			if ($json === false) $json = '{}';
			$sql[] = "INSERT INTO `znote_legacy_rows` (`source`, `table_name`, `source_pk`, `row_json`, `captured`) VALUES ("
				. acp_convert_sql_literal($source) . ', '
				. acp_convert_sql_literal($table) . ', '
				. acp_convert_sql_literal($pk) . ', '
				. acp_convert_sql_literal($json) . ', '
				. $captured . ');';
		}
	}

	$sql[] = "COMMIT;";
	return trim(implode("\n\n", $sql)) . "\n";
}

function acp_convert_legacy_archive_sql(string $source): string {
	$out = [];
	$captured = time();

	foreach (acp_convert_legacy_tables($source) as $table) {
		$count = acp_convert_count($table);
		$out[] = "-- Preserve legacy table {$table}";
		$out[] = "INSERT INTO `znote_legacy_tables` (`source`, `table_name`, `schema_sql`, `row_count`, `captured`)
VALUES (" . acp_convert_sql_literal($source) . ", " . acp_convert_sql_literal($table) . ", '', {$count}, {$captured})
ON DUPLICATE KEY UPDATE `schema_sql` = VALUES(`schema_sql`), `row_count` = VALUES(`row_count`), `captured` = VALUES(`captured`);";
		$out[] = "DELETE FROM `znote_legacy_rows`
WHERE `source` = " . acp_convert_sql_literal($source) . "
AND `table_name` = " . acp_convert_sql_literal($table) . ";";

		$rows = mysql_select_multi("SELECT * FROM `" . esc($table) . "`;") ?: [];
		foreach ($rows as $row) {
			$pk = substr(acp_convert_row_pk($table, $row), 0, 128);
			$json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($json === false) {
				$json = '{}';
			}
			$out[] = "INSERT INTO `znote_legacy_rows` (`source`, `table_name`, `source_pk`, `row_json`, `captured`)
VALUES (" . acp_convert_sql_literal($source) . ", " . acp_convert_sql_literal($table) . ", " . acp_convert_sql_literal($pk) . ", " . acp_convert_sql_literal($json) . ", {$captured});";
		}
	}

	return implode("\n\n", $out);
}

function acp_convert_player_name(int $playerId): string {
	if ($playerId <= 0) {
		return '';
	}
	$row = mysql_select_single("SELECT `name` FROM `players` WHERE `id` = {$playerId} LIMIT 1;");
	return is_array($row) ? (string)$row['name'] : '';
}

function acp_convert_strip_html(string $text): string {
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$text = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $text);
	$text = preg_replace('~</\s*(h[1-6]|div|li|tr|td|th)\s*>~i', "\n", $text);
	$text = preg_replace('~</\s*p\s*>~i', "\n\n", $text);
	$text = trim(strip_tags((string)$text));
	return preg_replace("/[ \t]*\n[ \t]*/", "\n", $text);
}

function acp_convert_news_text(string $text): string {
	// MyAAC and Gesior content is often HTML. ZnoteX stores BBCode/plain text.
	return acp_convert_strip_html($text);
}

function acp_convert_myaac_detect(): array {
	return [
		'accounts'  => acp_convert_table_exists('accounts'),
		'players'   => acp_convert_table_exists('players'),
		'news'      => acp_convert_table_exists('myaac_news'),
		'changelog' => acp_convert_table_exists('myaac_changelog'),
		'boards'    => acp_convert_table_exists('myaac_forum_boards'),
		'forum'     => acp_convert_table_exists('myaac_forum'),
		'pages'     => acp_convert_table_exists('myaac_pages'),
		'gallery'   => acp_convert_table_exists('myaac_gallery'),
		'menu'      => acp_convert_table_exists('myaac_menu'),
		'config'    => acp_convert_table_exists('myaac_config') || acp_convert_table_exists('myaac_settings'),
	];
}

function acp_convert_gesior_detect(): array {
	return [
		'accounts' => acp_convert_table_exists('accounts'),
		'players'  => acp_convert_table_exists('players'),
		'news_big' => acp_convert_table_exists('z_news_big'),
		'tickers'  => acp_convert_table_exists('z_news_tickers'),
		'boards'   => acp_convert_table_exists('z_forum_boards'),
		'forum'    => acp_convert_table_exists('z_forum'),
		'pages'    => acp_convert_table_exists('z_pages'),
		'config'   => acp_convert_table_exists('z_config'),
	];
}

function acp_convert_compatibility(): array {
	$report = ['accounts' => 0, 'players' => 0];

	if (acp_convert_table_exists('accounts') && acp_convert_table_exists('znote_accounts')) {
		$before = acp_convert_count('znote_accounts');
		mysql_insert("
			INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `flag`)
			SELECT `a`.`id`, 0, UNIX_TIMESTAMP(CURDATE()), ''
			FROM `accounts` AS `a`
			LEFT JOIN `znote_accounts` AS `z` ON `z`.`account_id` = `a`.`id`
			WHERE `z`.`id` IS NULL;
		");
		$report['accounts'] = max(0, acp_convert_count('znote_accounts') - $before);
	}

	if (acp_convert_table_exists('players') && acp_convert_table_exists('znote_players')) {
		$before = acp_convert_count('znote_players');
		mysql_insert("
			INSERT INTO `znote_players` (`player_id`, `created`, `hide_char`, `comment`)
			SELECT `p`.`id`, UNIX_TIMESTAMP(CURDATE()), 0, ''
			FROM `players` AS `p`
			LEFT JOIN `znote_players` AS `z` ON `z`.`player_id` = `p`.`id`
			WHERE `z`.`id` IS NULL;
		");
		$report['players'] = max(0, acp_convert_count('znote_players') - $before);
	}

	return $report;
}

function acp_convert_myaac_run(bool $dryRun = true): array {
	$report = [
		'accounts' => 0,
		'players' => 0,
		'news' => 0,
		'changelog' => 0,
		'boards' => 0,
		'threads' => 0,
		'posts' => 0,
		'pages' => 0,
		'gallery' => 0,
		'menu' => 0,
		'config' => 0,
		'archived' => 0,
		'warnings' => [],
	];

	if (!$dryRun) {
		acp_convert_ensure_tables();
		$report = array_merge($report, acp_convert_compatibility());
		$report['archived'] = acp_convert_archive_legacy('myaac');
	} else {
		foreach (acp_convert_legacy_tables('myaac') as $table) {
			$report['archived'] += acp_convert_count($table);
		}
	}

	if (acp_convert_table_exists('myaac_news') && acp_convert_table_exists('znote_news')) {
		if ($dryRun) {
			$report['news'] = acp_convert_count('myaac_news', '`hide` = 0 AND `type` IN (1, 3)');
		} else {
			$rows = mysql_select_multi("SELECT * FROM `myaac_news` WHERE `hide` = 0 AND `type` IN (1, 3) ORDER BY `id` ASC;") ?: [];
			foreach ($rows as $row) {
				$title = trim((string)$row['title']);
				$date = (int)$row['date'];
				$pid = (int)$row['player_id'];
				$exists = mysql_select_single("
					SELECT `id` FROM `znote_news`
					WHERE `title` = '" . esc(substr($title, 0, 30)) . "'
					AND `date` = {$date}
					LIMIT 1;
				");
				if ($exists !== false) {
					continue;
				}
				if (acp_convert_insert('znote_news', [
					'title' => substr($title !== '' ? $title : 'Imported news', 0, 30),
					'text' => acp_convert_news_text((string)$row['body']),
					'date' => $date > 0 ? $date : time(),
					'pid' => $pid,
				])) {
					$report['news']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('myaac_changelog') && acp_convert_table_exists('znote_changelog')) {
		if ($dryRun) {
			$report['changelog'] = acp_convert_count('myaac_changelog', '`hide` = 0');
		} else {
			$rows = mysql_select_multi("SELECT * FROM `myaac_changelog` WHERE `hide` = 0 ORDER BY `id` ASC;") ?: [];
			foreach ($rows as $row) {
				$text = substr(acp_convert_strip_html((string)$row['body']), 0, 254);
				$date = (int)$row['date'];
				$exists = mysql_select_single("
					SELECT `id` FROM `znote_changelog`
					WHERE `text` = '" . esc($text) . "'
					AND `time` = {$date}
					LIMIT 1;
				");
				if ($text === '' || $exists !== false) {
					continue;
				}
				if (acp_convert_insert('znote_changelog', [
					'text' => $text,
					'time' => $date > 0 ? $date : time(),
					'report_id' => 0,
					'status' => (int)$row['type'],
				])) {
					$report['changelog']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('myaac_pages') && acp_convert_table_exists('znote_pages')) {
		if ($dryRun) {
			$report['pages'] = acp_convert_count('myaac_pages', '`hide` = 0');
		} else {
			$rows = mysql_select_multi("SELECT * FROM `myaac_pages` WHERE `hide` = 0 ORDER BY `id` ASC;") ?: [];
			foreach ($rows as $row) {
				$mapped = acp_convert_map_get('myaac', 'myaac_pages', $row['id'], 'znote_pages');
				if ($mapped > 0) {
					continue;
				}
				$slug = acp_convert_slug((string)$row['name'], 'myaac-page-' . (int)$row['id']);
				$title = substr(trim((string)$row['title']), 0, 100);
				$exists = mysql_select_single("SELECT `id` FROM `znote_pages` WHERE `slug` = '" . esc($slug) . "' LIMIT 1;");
				if ($exists !== false) {
					acp_convert_map_set('myaac', 'myaac_pages', $row['id'], 'znote_pages', (int)$exists['id']);
					continue;
				}
				if (acp_convert_insert('znote_pages', [
					'slug' => $slug,
					'title' => $title !== '' ? $title : $slug,
					'body' => acp_convert_news_text((string)$row['body']),
					'created' => (int)$row['date'] > 0 ? (int)$row['date'] : time(),
					'updated' => (int)$row['date'] > 0 ? (int)$row['date'] : time(),
					'player_id' => (int)$row['player_id'],
					'access' => (int)$row['access'],
					'active' => 1,
				])) {
					$newId = (int)acp_convert_scalar('SELECT LAST_INSERT_ID() AS `v`;');
					acp_convert_map_set('myaac', 'myaac_pages', $row['id'], 'znote_pages', $newId);
					$report['pages']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('myaac_gallery') && acp_convert_table_exists('znote_images')) {
		if ($dryRun) {
			$report['gallery'] = acp_convert_count('myaac_gallery', '`hide` = 0');
		} else {
			$rows = mysql_select_multi("SELECT * FROM `myaac_gallery` WHERE `hide` = 0 ORDER BY `id` ASC;") ?: [];
			foreach ($rows as $row) {
				$mapped = acp_convert_map_get('myaac', 'myaac_gallery', $row['id'], 'znote_images');
				if ($mapped > 0) {
					continue;
				}
				$image = trim((string)$row['image']);
				if ($image === '') {
					continue;
				}
				$title = substr(trim((string)($row['comment'] ?: 'Imported image')), 0, 30);
				$exists = mysql_select_single("SELECT `id` FROM `znote_images` WHERE `image` = '" . esc($image) . "' LIMIT 1;");
				if ($exists !== false) {
					acp_convert_map_set('myaac', 'myaac_gallery', $row['id'], 'znote_images', (int)$exists['id']);
					continue;
				}
				if (acp_convert_insert('znote_images', [
					'title' => $title,
					'desc' => (string)$row['comment'],
					'date' => time(),
					'status' => 2,
					'image' => substr($image, 0, 255),
					'delhash' => '',
					'account_id' => 0,
				])) {
					$newId = (int)acp_convert_scalar('SELECT LAST_INSERT_ID() AS `v`;');
					acp_convert_map_set('myaac', 'myaac_gallery', $row['id'], 'znote_images', $newId);
					$report['gallery']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('myaac_menu') && acp_convert_table_exists('znote_menu')) {
		if ($dryRun) {
			$report['menu'] = acp_convert_count('myaac_menu', '`enabled` = 1');
		} else {
			$rows = mysql_select_multi("SELECT * FROM `myaac_menu` WHERE `enabled` = 1 ORDER BY `ordering` ASC, `id` ASC;") ?: [];
			foreach ($rows as $row) {
				$mapped = acp_convert_map_get('myaac', 'myaac_menu', $row['id'], 'znote_menu');
				if ($mapped > 0) {
					continue;
				}
				$label = substr(trim((string)$row['name']), 0, 64);
				$url = substr(trim((string)$row['link']), 0, 255);
				if ($label === '' || $url === '') {
					continue;
				}
				$parentId = acp_convert_menu_parent_id((int)($row['category'] ?? 0));
				if ($parentId <= 0) {
					continue;
				}
				$exists = mysql_select_single("
					SELECT `id` FROM `znote_menu`
					WHERE `location` = 'main'
					AND `label` = '" . esc($label) . "'
					AND `url` = '" . esc($url) . "'
					LIMIT 1;
				");
				if ($exists !== false) {
					acp_convert_map_set('myaac', 'myaac_menu', $row['id'], 'znote_menu', (int)$exists['id']);
					continue;
				}
				if (acp_convert_insert('znote_menu', [
					'location' => 'main',
					'parent_id' => $parentId,
					'label' => $label,
					'url' => $url,
					'icon' => '',
					'target' => !empty($row['blank']) ? '_blank' : '',
					'visibility' => 'all',
					'sort_order' => (int)$row['ordering'],
					'active' => 1,
				])) {
					$newId = (int)acp_convert_scalar('SELECT LAST_INSERT_ID() AS `v`;');
					acp_convert_map_set('myaac', 'myaac_menu', $row['id'], 'znote_menu', $newId);
					$report['menu']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('znote_config')) {
		if ($dryRun) {
			$report['config'] =
				acp_convert_count('myaac_config') +
				acp_convert_count('myaac_settings');
		} else {
			if (acp_convert_table_exists('myaac_config')) {
				$rows = mysql_select_multi("SELECT * FROM `myaac_config` ORDER BY `id` ASC;") ?: [];
				foreach ($rows as $row) {
					if (acp_convert_config_set('legacy:myaac:' . (string)$row['name'], (string)$row['value'])) {
						$report['config']++;
					}
				}
			}
			if (acp_convert_table_exists('myaac_settings')) {
				$rows = mysql_select_multi("SELECT * FROM `myaac_settings` ORDER BY `id` ASC;") ?: [];
				foreach ($rows as $row) {
					if (acp_convert_config_set('legacy:myaac_setting:' . (string)$row['key'], (string)$row['value'])) {
						$report['config']++;
					}
				}
			}
		}
	}

	$boardMap = [];
	if (acp_convert_table_exists('myaac_forum_boards') && acp_convert_table_exists('znote_forum')) {
		$rows = mysql_select_multi("SELECT * FROM `myaac_forum_boards` ORDER BY `id` ASC;") ?: [];
		if ($dryRun) {
			$report['boards'] = count($rows);
		} else {
			foreach ($rows as $row) {
				$name = substr(trim((string)$row['name']), 0, 50);
				$existing = mysql_select_single("
					SELECT `id` FROM `znote_forum`
					WHERE `name` = '" . esc($name) . "'
					LIMIT 1;
				");
				if ($existing !== false) {
					$boardMap[(int)$row['id']] = (int)$existing['id'];
					continue;
				}
				if (acp_convert_insert('znote_forum', [
					'name' => $name !== '' ? $name : 'Imported board',
					'access' => max(1, (int)$row['access']),
					'closed' => (int)$row['closed'],
					'hidden' => (int)$row['hide'],
					'guild_id' => (int)$row['guild'],
				])) {
					$boardMap[(int)$row['id']] = (int)acp_convert_scalar('SELECT LAST_INSERT_ID() AS `v`;');
					$report['boards']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('myaac_forum') && acp_convert_table_exists('znote_forum_threads') && acp_convert_table_exists('znote_forum_posts')) {
		if ($dryRun) {
			$report['threads'] = acp_count("SELECT COUNT(*) AS `c` FROM `myaac_forum` WHERE `id` = `first_post`;");
			$report['posts'] = acp_count("SELECT COUNT(*) AS `c` FROM `myaac_forum` WHERE `id` <> `first_post`;");
		} else {
			$threadMap = [];
			$threads = mysql_select_multi("SELECT * FROM `myaac_forum` WHERE `id` = `first_post` ORDER BY `id` ASC;") ?: [];
			foreach ($threads as $row) {
				$oldBoard = (int)$row['section'];
				$boardId = $boardMap[$oldBoard] ?? $oldBoard;
				$title = substr(trim((string)$row['post_topic']), 0, 50);
				$created = (int)$row['post_date'];
				$playerId = (int)$row['author_guid'];
				$existing = mysql_select_single("
					SELECT `id` FROM `znote_forum_threads`
					WHERE `forum_id` = {$boardId}
					AND `player_id` = {$playerId}
					AND `created` = {$created}
					AND `title` = '" . esc($title) . "'
					LIMIT 1;
				");
				if ($existing !== false) {
					$threadMap[(int)$row['id']] = (int)$existing['id'];
					continue;
				}
				if (acp_convert_insert('znote_forum_threads', [
					'forum_id' => $boardId,
					'player_id' => $playerId,
					'player_name' => acp_convert_player_name($playerId),
					'title' => $title !== '' ? $title : 'Imported thread',
					'text' => sanitize((string)$row['post_text']),
					'created' => $created > 0 ? $created : time(),
					'updated' => (int)$row['edit_date'] > 0 ? (int)$row['edit_date'] : ($created > 0 ? $created : time()),
					'sticky' => (int)$row['sticked'],
					'hidden' => 0,
					'closed' => (int)$row['closed'],
				])) {
					$threadMap[(int)$row['id']] = (int)acp_convert_scalar('SELECT LAST_INSERT_ID() AS `v`;');
					$report['threads']++;
				}
			}

			$posts = mysql_select_multi("SELECT * FROM `myaac_forum` WHERE `id` <> `first_post` ORDER BY `id` ASC;") ?: [];
			foreach ($posts as $row) {
				$oldThread = (int)$row['first_post'];
				$threadId = $threadMap[$oldThread] ?? 0;
				if ($threadId <= 0) {
					continue;
				}
				$created = (int)$row['post_date'];
				$playerId = (int)$row['author_guid'];
				$exists = mysql_select_single("
					SELECT `id` FROM `znote_forum_posts`
					WHERE `thread_id` = {$threadId}
					AND `player_id` = {$playerId}
					AND `created` = {$created}
					LIMIT 1;
				");
				if ($exists !== false) {
					continue;
				}
				if (acp_convert_insert('znote_forum_posts', [
					'thread_id' => $threadId,
					'player_id' => $playerId,
					'player_name' => acp_convert_player_name($playerId),
					'text' => sanitize((string)$row['post_text']),
					'created' => $created > 0 ? $created : time(),
					'updated' => (int)$row['edit_date'] > 0 ? (int)$row['edit_date'] : ($created > 0 ? $created : time()),
				])) {
					$report['posts']++;
				}
			}
		}
	}

	if ($dryRun && !$report['news'] && !$report['changelog'] && !$report['boards'] && !$report['threads'] && !$report['posts'] && !$report['pages'] && !$report['gallery'] && !$report['menu'] && !$report['config']) {
		$report['warnings'][] = 'No MyAAC content tables were found in this database.';
	}

	return $report;
}

function acp_convert_gesior_run(bool $dryRun = true): array {
	$report = [
		'accounts' => 0,
		'players' => 0,
		'news' => 0,
		'changelog' => 0,
		'boards' => 0,
		'threads' => 0,
		'posts' => 0,
		'pages' => 0,
		'gallery' => 0,
		'menu' => 0,
		'config' => 0,
		'archived' => 0,
		'warnings' => [],
	];

	if (!$dryRun) {
		acp_convert_ensure_tables();
		$report = array_merge($report, acp_convert_compatibility());
		$report['archived'] = acp_convert_archive_legacy('gesior');
	} else {
		foreach (acp_convert_legacy_tables('gesior') as $table) {
			$report['archived'] += acp_convert_count($table);
		}
	}

	if (acp_convert_table_exists('z_news_big') && acp_convert_table_exists('znote_news')) {
		$hideCol = acp_convert_column_exists('z_news_big', 'hide_news') ? 'hide_news' : 'hide';
		if ($dryRun) {
			$report['news'] += acp_convert_count('z_news_big', '`' . $hideCol . '` = 0');
		} else {
			$rows = mysql_select_multi("SELECT * FROM `z_news_big` WHERE `" . esc($hideCol) . "` = 0 ORDER BY `date` ASC;") ?: [];
			foreach ($rows as $row) {
				$title = substr(trim((string)($row['topic'] ?? 'Imported news')), 0, 30);
				$date = (int)($row['date'] ?? 0);
				$pid = (int)($row['author_id'] ?? 0);
				$exists = mysql_select_single("
					SELECT `id` FROM `znote_news`
					WHERE `title` = '" . esc($title) . "'
					AND `date` = {$date}
					LIMIT 1;
				");
				if ($exists !== false) {
					continue;
				}
				if (acp_convert_insert('znote_news', [
					'title' => $title,
					'text' => acp_convert_news_text((string)($row['text'] ?? '')),
					'date' => $date > 0 ? $date : time(),
					'pid' => $pid,
				])) {
					$report['news']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('z_news_tickers') && acp_convert_table_exists('znote_changelog')) {
		$hideWhere = acp_convert_column_exists('z_news_tickers', 'hide_ticker') ? '`hide_ticker` = 0' : '1=1';
		$textCol = acp_convert_column_exists('z_news_tickers', 'text') ? 'text' : 'body';
		if ($dryRun) {
			$report['changelog'] += acp_convert_count('z_news_tickers', $hideWhere);
		} else {
			$rows = mysql_select_multi("SELECT * FROM `z_news_tickers` WHERE {$hideWhere} ORDER BY `date` ASC;") ?: [];
			foreach ($rows as $row) {
				$text = substr(acp_convert_strip_html((string)($row[$textCol] ?? '')), 0, 254);
				$date = (int)($row['date'] ?? 0);
				if ($text === '') {
					continue;
				}
				$exists = mysql_select_single("
					SELECT `id` FROM `znote_changelog`
					WHERE `text` = '" . esc($text) . "'
					AND `time` = {$date}
					LIMIT 1;
				");
				if ($exists !== false) {
					continue;
				}
				if (acp_convert_insert('znote_changelog', [
					'text' => $text,
					'time' => $date > 0 ? $date : time(),
					'report_id' => 0,
					'status' => 0,
				])) {
					$report['changelog']++;
				}
			}
		}
	}

	if (acp_convert_table_exists('z_config') && acp_convert_table_exists('znote_config')) {
		if ($dryRun) {
			$report['config'] = acp_convert_count('z_config');
		} else {
			$rows = mysql_select_multi("SELECT * FROM `z_config`;") ?: [];
			foreach ($rows as $row) {
				$name = (string)($row['key'] ?? ($row['name'] ?? ($row['config'] ?? '')));
				$value = (string)($row['value'] ?? '');
				if ($name !== '' && acp_convert_config_set('legacy:gesior:' . $name, $value)) {
					$report['config']++;
				}
			}
		}
	}

	$sourceForum = acp_convert_table_exists('z_forum') ? 'z_forum' : '';
	if ($sourceForum !== '' && acp_convert_table_exists('znote_forum_threads') && acp_convert_table_exists('znote_forum_posts')) {
		if ($dryRun) {
			$report['threads'] = acp_count("SELECT COUNT(*) AS `c` FROM `{$sourceForum}` WHERE `id` = `first_post`;");
			$report['posts'] = acp_count("SELECT COUNT(*) AS `c` FROM `{$sourceForum}` WHERE `id` <> `first_post`;");
		} else {
			$threadMap = [];
			$threads = mysql_select_multi("SELECT * FROM `{$sourceForum}` WHERE `id` = `first_post` ORDER BY `id` ASC;") ?: [];
			foreach ($threads as $row) {
				$boardId = max(1, (int)($row['section'] ?? 0));
				$title = substr(trim((string)($row['post_topic'] ?? 'Imported thread')), 0, 50);
				$created = (int)($row['post_date'] ?? 0);
				$playerId = (int)($row['author_guid'] ?? 0);
				$existing = mysql_select_single("
					SELECT `id` FROM `znote_forum_threads`
					WHERE `forum_id` = {$boardId}
					AND `player_id` = {$playerId}
					AND `created` = {$created}
					AND `title` = '" . esc($title) . "'
					LIMIT 1;
				");
				if ($existing !== false) {
					$threadMap[(int)$row['id']] = (int)$existing['id'];
					continue;
				}
				if (acp_convert_insert('znote_forum_threads', [
					'forum_id' => $boardId,
					'player_id' => $playerId,
					'player_name' => acp_convert_player_name($playerId),
					'title' => $title,
					'text' => sanitize((string)($row['post_text'] ?? '')),
					'created' => $created > 0 ? $created : time(),
					'updated' => (int)($row['edit_date'] ?? 0) > 0 ? (int)$row['edit_date'] : ($created > 0 ? $created : time()),
					'sticky' => (int)($row['sticked'] ?? 0),
					'hidden' => 0,
					'closed' => (int)($row['closed'] ?? 0),
				])) {
					$threadMap[(int)$row['id']] = (int)acp_convert_scalar('SELECT LAST_INSERT_ID() AS `v`;');
					$report['threads']++;
				}
			}

			$posts = mysql_select_multi("SELECT * FROM `{$sourceForum}` WHERE `id` <> `first_post` ORDER BY `id` ASC;") ?: [];
			foreach ($posts as $row) {
				$threadId = $threadMap[(int)$row['first_post']] ?? 0;
				if ($threadId <= 0) {
					continue;
				}
				$created = (int)($row['post_date'] ?? 0);
				$playerId = (int)($row['author_guid'] ?? 0);
				$exists = mysql_select_single("
					SELECT `id` FROM `znote_forum_posts`
					WHERE `thread_id` = {$threadId}
					AND `player_id` = {$playerId}
					AND `created` = {$created}
					LIMIT 1;
				");
				if ($exists !== false) {
					continue;
				}
				if (acp_convert_insert('znote_forum_posts', [
					'thread_id' => $threadId,
					'player_id' => $playerId,
					'player_name' => acp_convert_player_name($playerId),
					'text' => sanitize((string)($row['post_text'] ?? '')),
					'created' => $created > 0 ? $created : time(),
					'updated' => (int)($row['edit_date'] ?? 0) > 0 ? (int)$row['edit_date'] : ($created > 0 ? $created : time()),
				])) {
					$report['posts']++;
				}
			}
		}
	}

	if ($dryRun && !$report['news'] && !$report['changelog'] && !$report['threads'] && !$report['posts'] && !$report['config']) {
		$report['warnings'][] = 'No Gesior2012 content tables were found in this database.';
	}

	return $report;
}

function acp_convert_refresh_cache(): void {
	if (acp_convert_table_exists('znote_news')) {
		$cache = new Cache('engine/cache/news');
		$cache->setContent(fetchAllNews() ?: []);
		$cache->save();
	}

	if (acp_convert_table_exists('znote_changelog')) {
		$cache = new Cache('engine/cache/changelog');
		$cache->useMemory(false);
		$cache->setContent(mysql_select_multi("
			SELECT `id`, `text`, `time`, `report_id`, `status`
			FROM `znote_changelog`
			ORDER BY `id` DESC;
		") ?: []);
		$cache->save();
	}
}

function acp_convert_sql_preview(string $statement): string {
	$statement = preg_replace('/\s+/', ' ', trim($statement));
	if ($statement === null) {
		return '';
	}
	return strlen($statement) > 500 ? substr($statement, 0, 500) . '...' : $statement;
}

function acp_convert_run_sql_script(string $sql): array {
	global $connect, $aacQueries, $accQueriesData;

	$statements = acp_convert_dump_split($sql);
	$report = [
		'ok' => false,
		'executed' => 0,
		'total' => count($statements),
		'errors' => [],
		'rolled_back' => false,
		'started' => date('Y-m-d H:i:s'),
		'finished' => '',
	];

	if (!$statements) {
		$report['errors'][] = [
			'statement' => 0,
			'message' => 'No SQL statements were found in the uploaded file.',
			'sql' => '',
		];
		$report['finished'] = date('Y-m-d H:i:s');
		return $report;
	}

	$inTransaction = false;
	foreach ($statements as $index => $statement) {
		$statement = trim($statement);
		if ($statement === '') {
			continue;
		}

		try {
			$aacQueries++;
			$accQueriesData[] = "[" . elapsedTime() . "] " . $statement;
			$result = mysqli_query($connect, $statement);
			if ($result instanceof mysqli_result) {
				mysqli_free_result($result);
			}
			$report['executed']++;

			if (preg_match('/^START\s+TRANSACTION\b/i', $statement)) {
				$inTransaction = true;
			} elseif (preg_match('/^(COMMIT|ROLLBACK)\b/i', $statement)) {
				$inTransaction = false;
			}
		} catch (mysqli_sql_exception $e) {
			$report['errors'][] = [
				'statement' => $index + 1,
				'message' => $e->getMessage(),
				'sql' => acp_convert_sql_preview($statement),
			];

			if ($inTransaction) {
				try {
					mysqli_query($connect, 'ROLLBACK');
					$report['rolled_back'] = true;
				} catch (mysqli_sql_exception $rollbackError) {
					$report['errors'][] = [
						'statement' => 0,
						'message' => 'Rollback failed: ' . $rollbackError->getMessage(),
						'sql' => 'ROLLBACK',
					];
				}
			}
			break;
		}
	}

	$report['ok'] = empty($report['errors']);
	$report['finished'] = date('Y-m-d H:i:s');
	return $report;
}

function acp_convert_remap_report(string $sql): array {
	if (!preg_match('/^-- Source detected:\s*([a-z0-9_-]+)/mi', $sql, $match)) {
		return ['accounts' => 0, 'players' => 0];
	}

	$source = esc((string)$match[1]);
	$rows = mysql_select_multi("
		SELECT `source_table`, COUNT(*) AS `total`
		FROM `znote_convert_map`
		WHERE `source` = '{$source}'
		AND `target_table` IN ('accounts', 'players')
		AND CAST(`source_id` AS UNSIGNED) <> `target_id`
		GROUP BY `source_table`;
	") ?: [];
	$report = ['accounts' => 0, 'players' => 0];
	foreach ($rows as $row) {
		$table = (string)($row['source_table'] ?? '');
		if (isset($report[$table])) {
			$report[$table] = (int)$row['total'];
		}
	}
	return $report;
}

function acp_convert_current_account_snapshot(): array {
	global $session_user_id;

	$accountId = (int)($session_user_id ?? 0);
	if ($accountId <= 0 || !acp_convert_table_exists('accounts')) {
		return [];
	}

	$row = mysql_select_single("SELECT * FROM `accounts` WHERE `id` = {$accountId} LIMIT 1;");
	return is_array($row) ? $row : [];
}

function acp_convert_restore_account_snapshot(array $snapshot): bool {
	if (!$snapshot || empty($snapshot['id']) || !acp_convert_table_exists('accounts')) {
		return false;
	}

	$columns = mysql_select_multi("SHOW COLUMNS FROM `accounts`;") ?: [];
	$available = [];
	foreach ($columns as $column) {
		if (!empty($column['Field'])) {
			$available[(string)$column['Field']] = true;
		}
	}

	$sets = [];
	foreach ($snapshot as $column => $value) {
		if ($column === 'id' || empty($available[$column])) {
			continue;
		}
		$sets[] = '`' . esc($column) . '` = ' . acp_convert_sql_literal($value);
	}

	if (!$sets) {
		return false;
	}

	return mysql_update("
		UPDATE `accounts`
		SET " . implode(', ', $sets) . "
		WHERE `id` = " . (int)$snapshot['id'] . "
		LIMIT 1;
	");
}

function acp_convert_sql_script(string $source): string {
	$now = date('Y-m-d H:i:s');
	$sql = [];
	$sql[] = "-- ZnoteX {$source} conversion SQL";
	$sql[] = "-- Generated {$now}";
	$sql[] = "-- Import this into a database that already contains the legacy {$source} tables and the ZnoteX schema.";
	$sql[] = "START TRANSACTION;";
	$sql[] = "";
	$sql[] = file_get_contents('SQL/migrations/2.0.0_pages_and_convert_map.sql') ?: '';
	$sql[] = "";
	$sql[] = "-- Accounts and players compatibility rows";
	$sql[] = "INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `flag`)
SELECT `a`.`id`, 0, UNIX_TIMESTAMP(CURDATE()), ''
FROM `accounts` AS `a`
LEFT JOIN `znote_accounts` AS `z` ON `z`.`account_id` = `a`.`id`
WHERE `z`.`id` IS NULL;";
	$sql[] = "INSERT INTO `znote_players` (`player_id`, `created`, `hide_char`, `comment`)
SELECT `p`.`id`, UNIX_TIMESTAMP(CURDATE()), 0, ''
FROM `players` AS `p`
LEFT JOIN `znote_players` AS `z` ON `z`.`player_id` = `p`.`id`
WHERE `z`.`id` IS NULL;";

	if ($source === 'myaac') {
		if (acp_convert_table_exists('myaac_news')) {
			$sql[] = "";
			$sql[] = "-- MyAAC news";
			$sql[] = "INSERT INTO `znote_news` (`title`, `text`, `date`, `pid`)
SELECT LEFT(`m`.`title`, 30), `m`.`body`, IF(`m`.`date` > 0, `m`.`date`, UNIX_TIMESTAMP()), `m`.`player_id`
FROM `myaac_news` AS `m`
LEFT JOIN `znote_news` AS `z` ON `z`.`title` = LEFT(`m`.`title`, 30) AND `z`.`date` = `m`.`date`
WHERE `m`.`hide` = 0 AND `m`.`type` IN (1, 3) AND `z`.`id` IS NULL;";
		}
		if (acp_convert_table_exists('myaac_changelog')) {
			$sql[] = "";
			$sql[] = "-- MyAAC changelog";
			$sql[] = "INSERT INTO `znote_changelog` (`text`, `time`, `report_id`, `status`)
SELECT LEFT(`m`.`body`, 254), IF(`m`.`date` > 0, `m`.`date`, UNIX_TIMESTAMP()), 0, `m`.`type`
FROM `myaac_changelog` AS `m`
LEFT JOIN `znote_changelog` AS `z` ON `z`.`text` = LEFT(`m`.`body`, 254) AND `z`.`time` = `m`.`date`
WHERE `m`.`hide` = 0 AND `m`.`body` <> '' AND `z`.`id` IS NULL;";
		}
		if (acp_convert_table_exists('myaac_pages')) {
			$sql[] = "";
			$sql[] = "-- MyAAC custom pages";
			$sql[] = "INSERT INTO `znote_pages` (`slug`, `title`, `body`, `created`, `updated`, `player_id`, `access`, `active`)
SELECT
  LEFT(LOWER(REPLACE(REPLACE(`m`.`name`, ' ', '-'), '/', '-')), 64),
  LEFT(`m`.`title`, 100),
  `m`.`body`,
  IF(`m`.`date` > 0, `m`.`date`, UNIX_TIMESTAMP()),
  IF(`m`.`date` > 0, `m`.`date`, UNIX_TIMESTAMP()),
  `m`.`player_id`,
  `m`.`access`,
  1
FROM `myaac_pages` AS `m`
LEFT JOIN `znote_pages` AS `z` ON `z`.`slug` = LEFT(LOWER(REPLACE(REPLACE(`m`.`name`, ' ', '-'), '/', '-')), 64)
WHERE `m`.`hide` = 0 AND `z`.`id` IS NULL;";
		}
		if (acp_convert_table_exists('myaac_gallery')) {
			$sql[] = "";
			$sql[] = "-- MyAAC gallery";
			$sql[] = "INSERT INTO `znote_images` (`title`, `desc`, `date`, `status`, `image`, `delhash`, `account_id`)
SELECT LEFT(IF(`m`.`comment` <> '', `m`.`comment`, 'Imported image'), 30), `m`.`comment`, UNIX_TIMESTAMP(), 2, LEFT(`m`.`image`, 255), '', 0
FROM `myaac_gallery` AS `m`
LEFT JOIN `znote_images` AS `z` ON `z`.`image` = LEFT(`m`.`image`, 255)
WHERE `m`.`hide` = 0 AND `m`.`image` <> '' AND `z`.`id` IS NULL;";
		}
		if (acp_convert_table_exists('myaac_menu')) {
			$sql[] = "";
			$sql[] = "-- MyAAC menu";
			$sql[] = "INSERT INTO `znote_menu` (`location`, `parent_id`, `label`, `url`, `icon`, `target`, `visibility`, `sort_order`, `active`)
SELECT 'main', `p`.`id`, LEFT(`m`.`name`, 64), LEFT(`m`.`link`, 255), '', IF(`m`.`blank` = 1, '_blank', ''), 'all', `m`.`ordering`, 1
FROM `myaac_menu` AS `m`
INNER JOIN `znote_menu` AS `p`
  ON `p`.`location` = 'main'
  AND `p`.`parent_id` = 0
  AND `p`.`label` = CASE `m`.`category`
    WHEN 1 THEN 'Home'
    WHEN 2 THEN 'Account'
    WHEN 3 THEN 'Community'
    WHEN 4 THEN 'Community'
    WHEN 5 THEN 'Library'
    WHEN 6 THEN 'Shop'
    ELSE ''
  END
LEFT JOIN `znote_menu` AS `z` ON `z`.`location` = 'main' AND `z`.`label` = LEFT(`m`.`name`, 64) AND `z`.`url` = LEFT(`m`.`link`, 255)
WHERE `m`.`enabled` = 1 AND `m`.`name` <> '' AND `m`.`link` <> '' AND `z`.`id` IS NULL;";
		}
		if (acp_convert_table_exists('myaac_config')) {
			$sql[] = "";
			$sql[] = "-- MyAAC config preserved as legacy keys";
			$sql[] = "INSERT INTO `znote_config` (`key`, `value`)
SELECT LEFT(CONCAT('legacy:myaac:', `name`), 64), `value`
FROM `myaac_config`
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);";
		}
		if (acp_convert_table_exists('myaac_settings')) {
			$sql[] = "INSERT INTO `znote_config` (`key`, `value`)
SELECT LEFT(CONCAT('legacy:myaac_setting:', `key`), 64), `value`
FROM `myaac_settings`
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);";
		}
	}

	if ($source === 'gesior') {
		if (acp_convert_table_exists('z_news_big')) {
			$hideCol = acp_convert_column_exists('z_news_big', 'hide_news') ? 'hide_news' : 'hide';
			$sql[] = "";
			$sql[] = "-- Gesior big news";
			$sql[] = "INSERT INTO `znote_news` (`title`, `text`, `date`, `pid`)
SELECT LEFT(`g`.`topic`, 30), `g`.`text`, IF(`g`.`date` > 0, `g`.`date`, UNIX_TIMESTAMP()), IFNULL(`g`.`author_id`, 0)
FROM `z_news_big` AS `g`
LEFT JOIN `znote_news` AS `z` ON `z`.`title` = LEFT(`g`.`topic`, 30) AND `z`.`date` = `g`.`date`
WHERE `g`.`{$hideCol}` = 0 AND `z`.`id` IS NULL;";
		}
		if (acp_convert_table_exists('z_news_tickers')) {
			$hideWhere = acp_convert_column_exists('z_news_tickers', 'hide_ticker') ? "`g`.`hide_ticker` = 0" : '1=1';
			$textCol = acp_convert_column_exists('z_news_tickers', 'text') ? 'text' : 'body';
			$sql[] = "";
			$sql[] = "-- Gesior tickers as Znote changelog entries";
			$sql[] = "INSERT INTO `znote_changelog` (`text`, `time`, `report_id`, `status`)
SELECT LEFT(`g`.`{$textCol}`, 254), IF(`g`.`date` > 0, `g`.`date`, UNIX_TIMESTAMP()), 0, 0
FROM `z_news_tickers` AS `g`
LEFT JOIN `znote_changelog` AS `z` ON `z`.`text` = LEFT(`g`.`{$textCol}`, 254) AND `z`.`time` = `g`.`date`
WHERE {$hideWhere} AND `g`.`{$textCol}` <> '' AND `z`.`id` IS NULL;";
		}
	}

	$archive = acp_convert_legacy_archive_sql($source);
	if ($archive !== '') {
		$sql[] = "";
		$sql[] = "-- Lossless legacy archive for custom tables and columns";
		$sql[] = $archive;
	}

	$sql[] = "";
	$sql[] = "COMMIT;";
	$sql[] = "-- Rebuild the ZnoteX news/changelog cache from the admin panel after importing.";

	return trim(implode("\n\n", $sql)) . "\n";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$source = (string)($_POST['source'] ?? '');
	$action = (string)($_POST['action'] ?? 'upload_download');

	if ($action === 'upload_download') {
		if (!in_array($source, ['auto', 'myaac', 'gesior'], true)) {
			acp_flash_error(t('acp.conv.err_choose_source'));
			acp_redirect('convert');
		}

		if (!isset($_FILES['sql_file']) || !is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
			acp_flash_error(t('acp.conv.err_no_dump'));
			acp_redirect('convert');
		}

		$size = (int)($_FILES['sql_file']['size'] ?? 0);
		if ($size <= 0 || $size > 256 * 1024 * 1024) {
			acp_flash_error(t('acp.conv.err_dump_size'));
			acp_redirect('convert');
		}

		$dump = (string)file_get_contents($_FILES['sql_file']['tmp_name']);
		$model = acp_convert_dump_model($dump);
		$converted = acp_convert_dump_script($model, $source);
		$detected = acp_convert_dump_source($model, $source);

		header('Content-Type: application/sql; charset=UTF-8');
		header('Content-Disposition: attachment; filename="znote_' . $detected . '_converted.sql"');
		header('Content-Length: ' . strlen($converted));
		echo $converted;
		exit;
	}

	if ($action === 'import_converted') {
		if (!isset($_FILES['converted_sql_file']) || !is_uploaded_file($_FILES['converted_sql_file']['tmp_name'])) {
			acp_flash_error(t('acp.conv.err_no_converted_file'));
			acp_redirect('convert');
		}

		$size = (int)($_FILES['converted_sql_file']['size'] ?? 0);
		if ($size <= 0 || $size > 256 * 1024 * 1024) {
			acp_flash_error(t('acp.conv.err_converted_size'));
			acp_redirect('convert');
		}

		$sql = (string)file_get_contents($_FILES['converted_sql_file']['tmp_name']);
		if (strpos($sql, '-- ZnoteX uploaded SQL conversion') === false || strpos($sql, 'znote_legacy_tables') === false) {
			acp_flash_error(t('acp.conv.err_not_generated'));
			acp_redirect('convert');
		}

		$currentAccount = acp_convert_current_account_snapshot();
		acp_convert_ensure_tables();
		$report = acp_convert_run_sql_script($sql);
		$report['protected_account'] = acp_convert_restore_account_snapshot($currentAccount);
		$report['remapped'] = acp_convert_remap_report($sql);
		$report['file'] = (string)($_FILES['converted_sql_file']['name'] ?? 'converted.sql');
		$report['size'] = $size;
		$_SESSION['acp_convert_import_report'] = $report;

		if ($report['ok']) {
			acp_convert_refresh_cache();
			acp_log('convert.import', $report['file'], ['statements_executed' => (int)$report['executed']]);
			acp_flash_success(t('acp.conv.imported_success', ['n' => (int)$report['executed']]));
		} else {
			$error = $report['errors'][0] ?? ['statement' => 0, 'message' => t('acp.conv.unknown_sql_error')];
			acp_flash_error(t('acp.conv.import_stopped', ['n' => (int)$error['statement'], 'message' => h((string)$error['message'])]));
		}
		acp_redirect('convert');
	}

	acp_flash_error(t('acp.conv.err_unknown_action'));
	acp_redirect('convert');
}

$importReport = $_SESSION['acp_convert_import_report'] ?? null;
unset($_SESSION['acp_convert_import_report']);
?>

<style>
.acp-convert-progress { display: none; margin: 0 0 16px; }
.acp-convert-progress.is-active { display: block; }
.acp-convert-bar { height: 8px; overflow: hidden; border-radius: 4px; background: var(--acp-panel-2); }
.acp-convert-bar span { display: block; width: 35%; height: 100%; background: var(--acp-blue-600); animation: acpConvertMove 1.1s ease-in-out infinite; }
.acp-convert-terminal { margin-top: 10px; padding: 10px 12px; border-radius: var(--acp-radius); background: #101820; color: #c9f7d1; font-family: Consolas, Monaco, monospace; font-size: 12.5px; line-height: 1.45; }
.acp-convert-terminal div { white-space: pre-wrap; }
.acp-convert-terminal strong { color: #fff; }
.acp-convert-terminal--error { color: #ffd1d1; }
@keyframes acpConvertMove {
	0% { transform: translateX(-110%); }
	100% { transform: translateX(310%); }
}
</style>

<div class="acp-convert-progress" id="convertProgress">
	<div class="acp-convert-bar"><span></span></div>
	<div class="acp-convert-terminal" id="convertTerminal">
		<div>$ waiting for action...</div>
	</div>
</div>

<div class="acp-flash acp-flash--info">
	<i class="fa fa-info-circle"></i>
	<span><?= h(t('acp.conv.info_banner')) ?></span>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= h(t('acp.conv.convert_title')) ?></h2>
		<p><?= h(t('acp.conv.convert_sub')) ?></p>
	</header>
	<div class="acp-card-body">
		<form method="post" enctype="multipart/form-data" data-convert-form>
			<?= acp_csrf_field() ?>
			<input type="hidden" name="action" value="upload_download">

			<div class="acp-field">
				<label class="acp-label" for="source"><?= h(t('acp.conv.source_label')) ?></label>
				<select class="acp-select" id="source" name="source">
					<option value="auto"><?= h(t('acp.conv.source_auto')) ?></option>
					<option value="myaac"><?= h(t('acp.conv.source_myaac')) ?></option>
					<option value="gesior"><?= h(t('acp.conv.source_gesior')) ?></option>
				</select>
				<p class="acp-hint"><?= t('acp.conv.source_hint', ['tag1' => '<code>myaac_news</code>', 'tag2' => '<code>z_news_big</code>']) ?></p>
			</div>

			<div class="acp-field">
				<label class="acp-label" for="sql_file"><?= h(t('acp.conv.sql_dump_label')) ?></label>
				<input class="acp-input" id="sql_file" name="sql_file" type="file" accept=".sql,text/sql,text/plain" required>
				<p class="acp-hint"><?= h(t('acp.conv.sql_dump_hint')) ?></p>
			</div>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--blue" type="submit">
					<i class="fa fa-download"></i> <?= h(t('acp.conv.convert_btn')) ?>
				</button>
			</div>
		</form>
	</div>
</section>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= h(t('acp.conv.import_title')) ?></h2>
		<p><?= h(t('acp.conv.import_sub')) ?></p>
	</header>
	<div class="acp-card-body">
		<form method="post" enctype="multipart/form-data" data-convert-form>
			<?= acp_csrf_field() ?>
			<input type="hidden" name="action" value="import_converted">

			<div class="acp-field">
				<label class="acp-label" for="converted_sql_file"><?= h(t('acp.conv.converted_sql_label')) ?></label>
				<input class="acp-input" id="converted_sql_file" name="converted_sql_file" type="file" accept=".sql,text/sql,text/plain" required>
				<p class="acp-hint"><?= h(t('acp.conv.converted_sql_hint')) ?></p>
			</div>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--green" type="submit">
					<i class="fa fa-upload"></i> <?= h(t('acp.conv.import_btn')) ?>
				</button>
			</div>
		</form>

		<?php if (is_array($importReport)): ?>
			<div class="acp-convert-terminal<?= empty($importReport['errors']) ? '' : ' acp-convert-terminal--error' ?>">
				<div><strong>$ <?= h(t('acp.conv.report_title')) ?></strong></div>
				<div>$ <?= h(t('acp.conv.report_file')) ?>: <?= h((string)($importReport['file'] ?? 'converted.sql')) ?> (<?= number_format((int)($importReport['size'] ?? 0)) ?> bytes)</div>
				<div>$ <?= h(t('acp.conv.report_started')) ?>: <?= h((string)($importReport['started'] ?? '')) ?></div>
				<div>$ <?= h(t('acp.conv.report_finished')) ?>: <?= h((string)($importReport['finished'] ?? '')) ?></div>
				<div>$ <?= h(t('acp.conv.report_statements')) ?>: <?= (int)($importReport['executed'] ?? 0) ?> / <?= (int)($importReport['total'] ?? 0) ?></div>
				<div>$ <?= h(t('acp.conv.report_status')) ?>: <?= !empty($importReport['ok']) ? h(t('acp.conv.status_ok')) : h(t('acp.conv.status_error')) ?></div>
				<div>$ <?= h(t('acp.conv.report_protected')) ?>: <?= !empty($importReport['protected_account']) ? h(t('acp.conv.yes')) : h(t('acp.conv.no')) ?></div>
				<div>$ <?= h(t('acp.conv.report_remapped_accounts')) ?>: <?= (int)($importReport['remapped']['accounts'] ?? 0) ?></div>
				<div>$ <?= h(t('acp.conv.report_remapped_players')) ?>: <?= (int)($importReport['remapped']['players'] ?? 0) ?></div>
				<?php if (!empty($importReport['rolled_back'])): ?>
					<div>$ <?= h(t('acp.conv.report_rollback')) ?></div>
				<?php endif; ?>
				<?php foreach (($importReport['errors'] ?? []) as $error): ?>
					<div>$ <?= h(t('acp.conv.report_error_statement', ['n' => (int)($error['statement'] ?? 0), 'message' => (string)($error['message'] ?? t('acp.conv.unknown_sql_error'))])) ?></div>
					<?php if (!empty($error['sql'])): ?>
						<div>$ <?= h(t('acp.conv.report_sql')) ?>: <?= h((string)$error['sql']) ?></div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= h(t('acp.conv.what_gets_converted_title')) ?></h2>
	</header>
	<div class="acp-card-body">
		<p><?= h(t('acp.conv.info_p1')) ?></p>
		<p><?= t('acp.conv.info_p2', [
			'myaac_news' => '<code>myaac_news</code>',
			'myaac_changelog' => '<code>myaac_changelog</code>',
			'myaac_forum_boards' => '<code>myaac_forum_boards</code>',
			'myaac_forum' => '<code>myaac_forum</code>',
			'myaac_pages' => '<code>myaac_pages</code>',
			'myaac_gallery' => '<code>myaac_gallery</code>',
			'myaac_menu' => '<code>myaac_menu</code>',
			'z_news_big' => '<code>z_news_big</code>',
			'z_news_tickers' => '<code>z_news_tickers</code>',
			'z_forum' => '<code>z_forum</code>',
			'legacy' => '<code>legacy:*</code>',
			'znote_config' => '<code>znote_config</code>',
			'znote_legacy_tables' => '<code>znote_legacy_tables</code>',
			'znote_legacy_rows' => '<code>znote_legacy_rows</code>',
		]) ?></p>
	</div>
</section>

<script>
(function () {
	var progress = document.getElementById('convertProgress');
	var terminal = document.getElementById('convertTerminal');
	if (!progress || !terminal) return;

	function line(text) {
		var div = document.createElement('div');
		div.textContent = text;
		terminal.appendChild(div);
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.hasAttribute('data-convert-form')) return;

		var action = form.querySelector('input[name="action"]');
		var source = form.querySelector('select[name="source"]');
		var file = form.querySelector('input[type="file"]');
		var actionValue = action ? action.value : 'convert';
		progress.classList.add('is-active');
		terminal.innerHTML = '';
		line('$ file: ' + (file && file.files && file.files[0] ? file.files[0].name : 'uploaded sql'));
		line('$ source: ' + (source ? source.value : 'converted znote sql'));
		line('$ action: ' + actionValue);
		if (actionValue === 'import_converted') {
			line('$ executing converted SQL...');
			line('$ errors will be printed here after redirect');
		} else {
			line('$ checking tables...');
			line('$ preserving custom legacy rows...');
			line('$ browser will receive the converted SQL file when finished');
		}
	}, true);
})();
</script>
