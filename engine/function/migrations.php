<?php
/**
 * ZnoteX database migrations.
 *
 * Runs SQL files from SQL/migrations and records successful executions in
 * znote_migrations, so updates no longer require manual phpMyAdmin imports.
 */

function znote_migrations_dir(): string {
	return dirname(__DIR__, 2) . '/SQL/migrations';
}

function znote_migrations_table_ensure(): bool {
	return db()->rawExecute("
		CREATE TABLE IF NOT EXISTS `znote_migrations` (
			`id` int NOT NULL AUTO_INCREMENT,
			`migration` varchar(191) NOT NULL,
			`checksum` char(64) NOT NULL,
			`executed_at` int NOT NULL,
			`execution_time_ms` int NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`),
			UNIQUE KEY `migration` (`migration`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
	");
}

function znote_migrations_applied(): array {
	if (!znote_migrations_table_ensure()) {
		return array();
	}

	$rows = db()->fetchAll("SELECT `migration`, `checksum`, `executed_at`, `execution_time_ms` FROM `znote_migrations` ORDER BY `migration` ASC;");
	$out = array();

	if (is_array($rows)) {
		foreach ($rows as $row) {
			$out[(string)$row['migration']] = $row;
		}
	}

	return $out;
}

function znote_migrations_files(): array {
	$dir = znote_migrations_dir();
	$files = is_dir($dir) ? glob($dir . '/*.sql') : array();
	$files = is_array($files) ? $files : array();
	sort($files, SORT_NATURAL | SORT_FLAG_CASE);

	$out = array();
	foreach ($files as $file) {
		$name = basename($file);
		$out[$name] = array(
			'name' => $name,
			'path' => $file,
			'checksum' => hash_file('sha256', $file) ?: '',
			'size' => filesize($file) ?: 0,
		);
	}

	return $out;
}

function znote_migrations_status(): array {
	$applied = znote_migrations_applied();
	$files = znote_migrations_files();
	$status = array();

	foreach ($files as $name => $file) {
		$row = $applied[$name] ?? null;
		$state = 'pending';
		if (is_array($row)) {
			$state = hash_equals((string)$row['checksum'], (string)$file['checksum']) ? 'applied' : 'changed';
		}

		$status[$name] = $file + array(
			'state' => $state,
			'applied' => $row,
		);
	}

	foreach ($applied as $name => $row) {
		if (!isset($status[$name])) {
			$status[$name] = array(
				'name' => $name,
				'path' => '',
				'checksum' => (string)$row['checksum'],
				'size' => 0,
				'state' => 'missing',
				'applied' => $row,
			);
		}
	}

	ksort($status, SORT_NATURAL | SORT_FLAG_CASE);
	return $status;
}

function znote_migrations_pending(): array {
	return array_filter(znote_migrations_status(), static function (array $migration): bool {
		return $migration['state'] === 'pending';
	});
}

function znote_migration_split_sql(string $sql): array {
	$statements = array();
	$current = '';
	$len = strlen($sql);
	$quote = null;
	$lineComment = false;
	$blockComment = false;

	for ($i = 0; $i < $len; $i++) {
		$char = $sql[$i];
		$next = ($i + 1 < $len) ? $sql[$i + 1] : '';

		if ($lineComment) {
			$current .= $char;
			if ($char === "\n") {
				$lineComment = false;
			}
			continue;
		}

		if ($blockComment) {
			$current .= $char;
			if ($char === '*' && $next === '/') {
				$current .= $next;
				$i++;
				$blockComment = false;
			}
			continue;
		}

		if ($quote !== null) {
			$current .= $char;
			if ($char === '\\' && $next !== '') {
				$current .= $next;
				$i++;
				continue;
			}
			if ($char === $quote) {
				$quote = null;
			}
			continue;
		}

		if (($char === '-' && $next === '-' && ($i + 2 >= $len || preg_match('/\s/', $sql[$i + 2])))
			|| $char === '#'
		) {
			$lineComment = true;
			$current .= $char;
			continue;
		}

		if ($char === '/' && $next === '*') {
			$blockComment = true;
			$current .= $char . $next;
			$i++;
			continue;
		}

		if ($char === '\'' || $char === '"' || $char === '`') {
			$quote = $char;
			$current .= $char;
			continue;
		}

		if ($char === ';') {
			$trimmed = trim($current);
			if ($trimmed !== '') {
				$statements[] = $trimmed;
			}
			$current = '';
			continue;
		}

		$current .= $char;
	}

	$trimmed = trim($current);
	if ($trimmed !== '') {
		$statements[] = $trimmed;
	}

	return $statements;
}

function znote_migration_run(string $migration): array {
	$files = znote_migrations_files();
	if (!isset($files[$migration])) {
		return array('ok' => false, 'message' => 'Migration file not found.', 'statements' => 0, 'time_ms' => 0);
	}
	if (!znote_migrations_table_ensure()) {
		return array('ok' => false, 'message' => 'Could not create znote_migrations table.', 'statements' => 0, 'time_ms' => 0);
	}

	$applied = znote_migrations_applied();
	if (isset($applied[$migration]) && hash_equals((string)$applied[$migration]['checksum'], (string)$files[$migration]['checksum'])) {
		return array('ok' => true, 'message' => 'Already applied.', 'statements' => 0, 'time_ms' => 0);
	}
	if (isset($applied[$migration])) {
		return array('ok' => false, 'message' => 'Migration was already applied but the file checksum changed.', 'statements' => 0, 'time_ms' => 0);
	}

	$sql = (string)file_get_contents($files[$migration]['path']);
	$statements = znote_migration_split_sql($sql);
	$started = microtime(true);
	$done = 0;

	foreach ($statements as $index => $statement) {
		if (!db()->rawExecute($statement)) {
			return array(
				'ok' => false,
				'message' => 'Statement ' . ($index + 1) . ' failed. Check Admin Panel > Error Log for the database reference.',
				'statements' => $done,
				'time_ms' => (int)round((microtime(true) - $started) * 1000),
			);
		}
		$done++;
	}

	$timeMs = (int)round((microtime(true) - $started) * 1000);
	$recorded = db()->execute("
		INSERT INTO `znote_migrations` (`migration`, `checksum`, `executed_at`, `execution_time_ms`)
		VALUES (?, ?, ?, ?);
	", [$migration, $files[$migration]['checksum'], time(), $timeMs]);

	if (!$recorded) {
		return array('ok' => false, 'message' => 'Migration ran but could not be recorded.', 'statements' => $done, 'time_ms' => $timeMs);
	}

	return array('ok' => true, 'message' => 'Applied successfully.', 'statements' => $done, 'time_ms' => $timeMs);
}

function znote_migrations_run_pending(): array {
	$results = array();

	foreach (array_keys(znote_migrations_pending()) as $migration) {
		$result = znote_migration_run($migration);
		$results[$migration] = $result;
		if (empty($result['ok'])) {
			break;
		}
	}

	return $results;
}
?>
