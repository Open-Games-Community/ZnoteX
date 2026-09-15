<?php

const ZNOTE_BACKUP_DIR = 'engine/backups';
const ZNOTE_BACKUP_ROWS_PER_CHUNK = 500;

function znote_backups_root(): string {
	return dirname(__DIR__, 2) . '/' . ZNOTE_BACKUP_DIR;
}

function znote_backups_prepare_dir(): bool {
	$dir = znote_backups_root();
	if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
		return false;
	}

	$deny = $dir . '/.htaccess';
	if (!is_file($deny)) {
		@file_put_contents($deny, "Require all denied\nDeny from all\n");
	}

	return is_writable($dir);
}

/** Existing backups, newest first. */
function znote_backups_list(): array {
	$dir = znote_backups_root();
	if (!is_dir($dir)) {
		return array();
	}

	$out = array();
	foreach (glob($dir . '/*.sql.gz') ?: array() as $file) {
		$out[] = array(
			'name' => basename($file),
			'size' => (int)filesize($file),
			'time' => (int)filemtime($file),
		);
	}

	usort($out, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);

	return $out;
}

function znote_backups_safe_name(string $name): string {
	$name = basename($name);
	return preg_match('/^znotex-backup-\d{8}-\d{6}\.sql\.gz$/', $name) ? $name : '';
}

function znote_backups_path(string $name): ?string {
	$safe = znote_backups_safe_name($name);
	if ($safe === '') {
		return null;
	}

	$path = znote_backups_root() . '/' . $safe;
	return is_file($path) ? $path : null;
}

function znote_backups_delete(string $name): bool {
	$path = znote_backups_path($name);
	return $path !== null && @unlink($path);
}

function znote_backups_prune(int $keep): int {
	if ($keep <= 0) {
		return 0;
	}

	$all = znote_backups_list();
	$extra = array_slice($all, $keep);
	$removed = 0;

	foreach ($extra as $backup) {
		if (znote_backups_delete($backup['name'])) {
			$removed++;
		}
	}

	return $removed;
}

function znote_backups_sql_value(mysqli $link, $value): string {
	if ($value === null) {
		return 'NULL';
	}

	return "'" . $link->real_escape_string((string)$value) . "'";
}

function znote_backups_write(mysqli $link, $handle): string {
	fwrite($handle, "-- ZnoteX backup - " . gmdate('Y-m-d H:i:s') . " UTC\n");
	fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

	$tables = array();
	$result = $link->query('SHOW TABLES;');
	if ($result === false) {
		return 'Could not list tables: ' . $link->error;
	}
	while ($row = $result->fetch_array(MYSQLI_NUM)) {
		$tables[] = $row[0];
	}

	foreach ($tables as $table) {
		$escapedTable = '`' . str_replace('`', '``', $table) . '`';

		$createResult = $link->query('SHOW CREATE TABLE ' . $escapedTable . ';');
		if ($createResult === false) {
			return 'Could not read structure of ' . $table . ': ' . $link->error;
		}
		$createRow = $createResult->fetch_assoc();
		$createSql = $createRow['Create Table'] ?? null;
		if ($createSql === null) {
			continue;
		}

		fwrite($handle, "\n-- ----------------------------\n-- Table: {$table}\n-- ----------------------------\n");
		fwrite($handle, "DROP TABLE IF EXISTS {$escapedTable};\n{$createSql};\n\n");

		$countResult = $link->query('SELECT COUNT(*) AS c FROM ' . $escapedTable . ';');
		$total = $countResult !== false ? (int)($countResult->fetch_assoc()['c'] ?? 0) : 0;
		if ($total === 0) {
			continue;
		}

		for ($offset = 0; $offset < $total; $offset += ZNOTE_BACKUP_ROWS_PER_CHUNK) {
			$dataResult = $link->query('SELECT * FROM ' . $escapedTable . ' LIMIT ' . ZNOTE_BACKUP_ROWS_PER_CHUNK . ' OFFSET ' . $offset . ';');
			if ($dataResult === false) {
				return 'Could not read data from ' . $table . ': ' . $link->error;
			}

			$columns = null;
			$rowsSql = array();
			while ($row = $dataResult->fetch_assoc()) {
				if ($columns === null) {
					$columns = '`' . implode('`,`', array_map(static fn($c) => str_replace('`', '``', $c), array_keys($row))) . '`';
				}
				$values = array_map(static fn($v) => znote_backups_sql_value($link, $v), array_values($row));
				$rowsSql[] = '(' . implode(',', $values) . ')';
			}

			if ($rowsSql) {
				fwrite($handle, "INSERT INTO {$escapedTable} ({$columns}) VALUES\n" . implode(",\n", $rowsSql) . ";\n");
			}
		}
	}

	fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");

	return '';
}

function znote_backups_create(): array {
	if (!znote_backups_prepare_dir()) {
		return array(false, 'Could not create/write to ' . ZNOTE_BACKUP_DIR . '/.');
	}

	if (!class_exists('mysqli') || !function_exists('gzopen')) {
		return array(false, 'PHP needs the mysqli and zlib extensions for backups.');
	}

	$name = 'znotex-backup-' . date('Ymd-His') . '.sql.gz';
	$path = znote_backups_root() . '/' . $name;

	$handle = @gzopen($path, 'wb9');
	if ($handle === false) {
		return array(false, 'Could not open ' . $name . ' for writing.');
	}

	$link = db()->connection();
	$error = znote_backups_write($link, $handle);
	gzclose($handle);

	if ($error !== '') {
		@unlink($path);
		return array(false, $error);
	}

	if (!is_file($path) || filesize($path) < 1) {
		@unlink($path);
		return array(false, 'The backup file ended up empty.');
	}

	return array(true, $name);
}
