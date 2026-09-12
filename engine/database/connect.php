<?php

$time = time();
if (!isset($version)) {
	$version = (string)require dirname(__DIR__) . '/version.php';
}

if (!isset($GLOBALS['__znote_start_time'])) {
	$GLOBALS['__znote_start_time'] = microtime(true);
}

if (!function_exists('elapsedTime')) {
	function elapsedTime(): float {
		return round(microtime(true) - $GLOBALS['__znote_start_time'], 4);
	}
}

if (!function_exists('znote_database_wait_screen')) {
	function znote_database_error_reference(): string {
		try {
			return strtoupper(bin2hex(random_bytes(6)));
		} catch (Throwable $e) {
			return strtoupper(substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, 12));
		}
	}

	function znote_database_request_context(): string {
		if (PHP_SAPI === 'cli') {
			return 'cli';
		}

		$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
		$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
		$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

		return trim($method . ' ' . $uri . ($ip !== '' ? ' ip=' . $ip : ''));
	}

	function znote_database_sql_preview(?string $sql): string {
		if ($sql === null || $sql === '') {
			return '';
		}

		$preview = preg_replace('/\s+/', ' ', trim($sql)) ?? '';
		if (strlen($preview) > 900) {
			$preview = substr($preview, 0, 900) . '...';
		}

		return $preview;
	}

	function znote_database_log_error(string $type, string $message, ?string $sql = null, int|string $code = 0): string {
		$reference = znote_database_error_reference();
		$parts = array(
			'[ZnoteX DB]',
			'ref=' . $reference,
			'type=' . $type,
			'code=' . (string)$code,
			'context=' . znote_database_request_context(),
			'message=' . preg_replace('/\s+/', ' ', trim($message)),
		);

		$preview = znote_database_sql_preview($sql);
		if ($preview !== '') {
			$parts[] = 'sql=' . $preview;
		}

		error_log(implode(' | ', $parts));
		return $reference;
	}

	function znote_database_wait_screen(int $errorCode, string $errorMessage): void {
		global $config;

		if (PHP_SAPI === 'cli') {
			die("Failed to connect to MySQL: (" . $errorCode . ") " . $errorMessage . PHP_EOL);
		}

		$reference = znote_database_log_error('connection', $errorMessage, null, $errorCode);

		if (!headers_sent()) {
			http_response_code(503);
			header('Retry-After: 5');
			header('Content-Type: text/html; charset=UTF-8');
		}

		$siteTitle = htmlspecialchars((string)($config['site_title'] ?? 'ZnoteX'), ENT_QUOTES, 'UTF-8');
		$safeReference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');
		$showDetails = !empty($config['security']['show_database_errors']);
		$safeDetails = $showDetails
			? htmlspecialchars('MySQL ' . $errorCode . ': ' . $errorMessage, ENT_QUOTES, 'UTF-8')
			: '';
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="refresh" content="5">
	<title><?= $siteTitle ?> - Connecting</title>
	<style>
		:root {
			color-scheme: dark;
			--bg: #101319;
			--panel: rgba(25, 31, 42, .86);
			--text: #eef3fb;
			--muted: #a9b4c5;
			--line: rgba(255, 255, 255, .12);
			--gold: #d9aa48;
			--ember: #ef6f4f;
			--blue: #6ea8ff;
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			min-height: 100vh;
			display: grid;
			place-items: center;
			padding: 24px;
			background:
				radial-gradient(circle at 25% 20%, rgba(110, 168, 255, .16), transparent 34%),
				radial-gradient(circle at 78% 72%, rgba(217, 170, 72, .14), transparent 34%),
				linear-gradient(135deg, #101319 0%, #171b24 48%, #0f1218 100%);
			color: var(--text);
			font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
		}

		.db-wait {
			width: min(560px, 100%);
			padding: 38px 32px;
			border: 1px solid var(--line);
			border-radius: 8px;
			background: var(--panel);
			box-shadow: 0 24px 80px rgba(0, 0, 0, .38);
			text-align: center;
			backdrop-filter: blur(14px);
		}

		.db-orbit {
			position: relative;
			width: 94px;
			height: 94px;
			margin: 0 auto 26px;
			border-radius: 50%;
			background: conic-gradient(from 90deg, var(--gold), var(--ember), var(--blue), var(--gold));
			animation: spin 1.25s linear infinite;
		}

		.db-orbit::before {
			content: "";
			position: absolute;
			inset: 9px;
			border-radius: inherit;
			background: #151a22;
			box-shadow: inset 0 0 24px rgba(255, 255, 255, .05);
		}

		.db-orbit::after {
			content: "";
			position: absolute;
			top: 7px;
			left: 50%;
			width: 14px;
			height: 14px;
			border-radius: 50%;
			background: #fff7d6;
			box-shadow: 0 0 22px rgba(217, 170, 72, .92);
			transform: translateX(-50%);
		}

		h1 {
			margin: 0 0 12px;
			font-size: clamp(26px, 4vw, 38px);
			line-height: 1.12;
			font-weight: 800;
			letter-spacing: 0;
		}

		p {
			margin: 0;
			color: var(--muted);
		}

		.db-status {
			display: inline-flex;
			align-items: center;
			gap: 10px;
			margin-top: 24px;
			padding: 10px 14px;
			border: 1px solid var(--line);
			border-radius: 999px;
			background: rgba(255, 255, 255, .05);
			color: #dce5f2;
			font-size: 14px;
		}

		.db-pulse {
			width: 9px;
			height: 9px;
			border-radius: 50%;
			background: var(--gold);
			box-shadow: 0 0 0 rgba(217, 170, 72, .7);
			animation: pulse 1.45s ease-out infinite;
			flex: 0 0 auto;
		}

		.db-details {
			margin-top: 22px;
			padding-top: 18px;
			border-top: 1px solid var(--line);
			color: #7f8a9d;
			font-size: 13px;
			word-break: break-word;
		}

		@keyframes spin {
			to { transform: rotate(360deg); }
		}

		@keyframes pulse {
			70% { box-shadow: 0 0 0 12px rgba(217, 170, 72, 0); }
			100% { box-shadow: 0 0 0 0 rgba(217, 170, 72, 0); }
		}

		@media (max-width: 520px) {
			body {
				padding: 16px;
			}

			.db-wait {
				padding: 30px 22px;
			}
		}
	</style>
</head>
<body>
	<main class="db-wait" role="status" aria-live="polite">
		<div class="db-orbit" aria-hidden="true"></div>
		<h1><?= $siteTitle ?></h1>
		<p>The database connection is not ready yet.</p>
		<div class="db-status"><span class="db-pulse" aria-hidden="true"></span>Retrying automatically in 5 seconds</div>
		<div class="db-details">
			Reference: <?= $safeReference ?>
			<?php if ($safeDetails !== ''): ?><br><?= $safeDetails ?><?php endif; ?>
		</div>
	</main>
</body>
</html>
		<?php
		exit;
	}
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
	$connect = new mysqli(
		$config['sqlHost'],
		$config['sqlUser'],
		$config['sqlPassword'],
		$config['sqlDatabase']
	);
	// Use the full UTF-8 character set for every request. This keeps accents,
	// supplementary characters and emoji consistent regardless of the server's
	// global MySQL/MariaDB defaults.
	$connect->set_charset('utf8mb4');
	$connect->query("SET collation_connection = 'utf8mb4_general_ci'");
} catch (mysqli_sql_exception $e) {
	znote_database_wait_screen($e->getCode(), $e->getMessage());
}

if ($connect->connect_errno) {
	znote_database_wait_screen($connect->connect_errno, (string)$connect->connect_error);
}

if (!isset($aacQueries)) {
	$aacQueries = 0;
}
if (!isset($accQueriesData)) {
	$accQueriesData = [];
}

class ZnoteDatabase {
	private mysqli $connection;

	public function __construct(mysqli $connection) {
		$this->connection = $connection;
	}

	public function connection(): mysqli {
		return $this->connection;
	}

	public function fetchOne(string $sql, array $params = []): array|false {
		$rows = $this->fetchAll($sql, $params);
		return ($rows !== false && isset($rows[0])) ? $rows[0] : false;
	}

	public function fetchAll(string $sql, array $params = []): array|false {
		$result = $this->query($sql, $params);
		if (!($result instanceof mysqli_result)) {
			return false;
		}

		$rows = [];
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		$result->free();

		return $rows ?: false;
	}

	public function execute(string $sql, array $params = []): bool {
		$result = $this->query($sql, $params);
		if ($result instanceof mysqli_result) {
			$result->free();
		}

		return $result !== false;
	}

	public function insertId(): int|string {
		return $this->connection->insert_id;
	}

	public function affectedRows(): int|string {
		return $this->connection->affected_rows;
	}

	public function transaction(callable $callback): mixed {
		try {
			$this->connection->begin_transaction();
			$result = $callback($this);

			if ($result === false) {
				$this->connection->rollback();
				return false;
			}

			$this->connection->commit();
			return $result;
		} catch (Throwable $e) {
			$this->connection->rollback();
			znote_database_log_error('transaction', $e->getMessage(), null, (int)$e->getCode());
			return false;
		}
	}

	public function beginTransaction(): bool {
		return $this->connection->begin_transaction();
	}

	public function commit(): bool {
		return $this->connection->commit();
	}

	public function rollback(): bool {
		return $this->connection->rollback();
	}

	public function rawFetchOne(string $sql): array|false {
		$result = $this->rawQuery($sql);
		if (!($result instanceof mysqli_result)) {
			return false;
		}

		$row = $result->fetch_assoc();
		$result->free();

		return $row ?: false;
	}

	public function rawFetchAll(string $sql): array|false {
		$result = $this->rawQuery($sql);
		if (!($result instanceof mysqli_result)) {
			return false;
		}

		$rows = [];
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		$result->free();

		return $rows ?: false;
	}

	public function rawExecute(string $sql): bool {
		$result = $this->rawQuery($sql);
		if ($result instanceof mysqli_result) {
			$result->free();
		}

		return $result !== false;
	}

	private function query(string $sql, array $params = []): mysqli_result|bool {
		$this->logQuery($sql, $params);

		try {
			if ($params === []) {
				return $this->connection->query($sql);
			}

			$stmt = $this->connection->prepare($sql);
			$this->bindParams($stmt, $params);
			$stmt->execute();

			$result = $this->statementResult($stmt);
			$stmt->close();

			return $result;
		} catch (mysqli_sql_exception $e) {
			znote_database_log_error('prepared-query', $e->getMessage(), $sql, (int)$e->getCode());
			return false;
		}
	}

	private function rawQuery(string $sql): mysqli_result|bool {
		$this->logQuery($sql);

		try {
			return $this->connection->query($sql);
		} catch (mysqli_sql_exception $e) {
			znote_database_log_error('raw-query', $e->getMessage(), $sql, (int)$e->getCode());
			return false;
		}
	}

	private function logQuery(string $sql, array $params = []): void {
		global $aacQueries, $accQueriesData;

		$aacQueries++;
		$accQueriesData[] = '[' . elapsedTime() . '] ' . $sql . ($params === [] ? '' : ' [prepared params: ' . count($params) . ']');
	}

	private function bindParams(mysqli_stmt $stmt, array $params): void {
		$types = '';
		$values = [];

		foreach ($params as $param) {
			if (is_int($param) || is_bool($param)) {
				$types .= 'i';
				$values[] = (int)$param;
			} elseif (is_float($param)) {
				$types .= 'd';
				$values[] = $param;
			} else {
				$types .= 's';
				$values[] = $param;
			}
		}

		if ($types === '') {
			return;
		}

		$refs = [];
		foreach ($values as $key => &$value) {
			$refs[$key] = &$value;
		}

		$stmt->bind_param($types, ...$refs);
	}

	private function statementResult(mysqli_stmt $stmt): mysqli_result|bool {
		$result = $stmt->get_result();
		if ($result instanceof mysqli_result) {
			return $result;
		}

		return true;
	}
}

function db(): ZnoteDatabase {
	global $znoteDatabase, $connect;

	if (!isset($znoteDatabase)) {
		$znoteDatabase = new ZnoteDatabase($connect);
	}

	return $znoteDatabase;
}

function mysql_znote_escape_string($escapestr): string {
	global $connect;
	return mysqli_real_escape_string($connect, (string)($escapestr ?? ''));
}

function mysql_select_single(string $query): array|false {
	return db()->rawFetchOne($query);
}

function mysql_select_multi(string $query): array|false {
	return db()->rawFetchAll($query);
}

function voidQuery(string $query): bool {
	return db()->rawExecute($query);
}

function mysql_update(string $query): bool { return voidQuery($query); }
function mysql_insert(string $query): bool { return voidQuery($query); }
function mysql_delete(string $query): bool { return voidQuery($query); }
