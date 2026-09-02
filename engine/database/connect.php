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

$install = "
<h2>Install:</h2>
<ol>
	<li>Import TFS database schema.</li>
	<li>Import <a href='/SQL/znote_schema.sql'>Znote AAC schema</a>.</li>
	<li>Edit config.php with correct MySQL details.</li>
</ol>
";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
	$connect = new mysqli(
		$config['sqlHost'],
		$config['sqlUser'],
		$config['sqlPassword'],
		$config['sqlDatabase']
	);
} catch (mysqli_sql_exception $e) {
	die(
		"Failed to connect to MySQL: (" .
		$e->getCode() . ") " .
		htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
		$install
	);
}

if ($connect->connect_errno) {
	die(
		"Failed to connect to MySQL: (" .
		$connect->connect_errno . ") " .
		htmlspecialchars((string)$connect->connect_error, ENT_QUOTES, 'UTF-8') .
		$install
	);
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
