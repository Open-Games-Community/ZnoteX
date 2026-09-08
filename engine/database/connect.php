<?php

$time = time();
if (!isset($version)) {
	$version = '2.0.0';
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
	function znote_database_wait_screen(int $errorCode, string $errorMessage): void {
		global $config;

		if (PHP_SAPI === 'cli') {
			die("Failed to connect to MySQL: (" . $errorCode . ") " . $errorMessage . PHP_EOL);
		}

		if (!headers_sent()) {
			http_response_code(503);
			header('Retry-After: 5');
			header('Content-Type: text/html; charset=UTF-8');
		}

		$siteTitle = htmlspecialchars((string)($config['site_title'] ?? 'ZnoteX'), ENT_QUOTES, 'UTF-8');
		$safeCode = htmlspecialchars((string)$errorCode, ENT_QUOTES, 'UTF-8');
		$safeMessage = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
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
		<div class="db-details">MySQL <?= $safeCode ?>: <?= $safeMessage ?></div>
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

function mysql_znote_escape_string($escapestr): string {
	global $connect;
	return mysqli_real_escape_string($connect, (string)($escapestr ?? ''));
}

function mysql_select_single(string $query): array|false {
	global $connect, $aacQueries, $accQueriesData;

	$aacQueries++;
	$accQueriesData[] = "[" . elapsedTime() . "] " . $query;

	try {
		$result = mysqli_query($connect, $query);
	} catch (mysqli_sql_exception $e) {
		error_log("SQL ERROR (select_single): " . $e->getMessage() . " | Query: " . $query);
		return false;
	}

	if (!($result instanceof mysqli_result)) {
		error_log("SQL ERROR (select_single): " . mysqli_error($connect) . " | Query: " . $query);
		return false;
	}

	$row = mysqli_fetch_assoc($result);
	mysqli_free_result($result);

	return $row ?: false;
}

function mysql_select_multi(string $query): array|false {
	global $connect, $aacQueries, $accQueriesData;

	$aacQueries++;
	$accQueriesData[] = "[" . elapsedTime() . "] " . $query;

	try {
		$result = mysqli_query($connect, $query);
	} catch (mysqli_sql_exception $e) {
		error_log("SQL ERROR (select_multi): " . $e->getMessage() . " | Query: " . $query);
		return false;
	}

	if (!($result instanceof mysqli_result)) {
		error_log("SQL ERROR (select_multi): " . mysqli_error($connect) . " | Query: " . $query);
		return false;
	}

	$array = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$array[] = $row;
	}
	mysqli_free_result($result);

	return $array ?: false;
}

function voidQuery(string $query): bool {
	global $connect, $aacQueries, $accQueriesData;

	$aacQueries++;
	$accQueriesData[] = "[" . elapsedTime() . "] " . $query;

	try {
		$result = mysqli_query($connect, $query);
	} catch (mysqli_sql_exception $e) {
		error_log("SQL ERROR (voidQuery): " . $e->getMessage() . " | Query: " . $query);
		return false;
	}

	if ($result === false) {
		error_log("SQL ERROR (voidQuery): " . mysqli_error($connect) . " | Query: " . $query);
		return false;
	}

	if ($result instanceof mysqli_result) {
		mysqli_free_result($result);
	}

	return true;
}

function mysql_update(string $query): bool { return voidQuery($query); }
function mysql_insert(string $query): bool { return voidQuery($query); }
function mysql_delete(string $query): bool { return voidQuery($query); }
