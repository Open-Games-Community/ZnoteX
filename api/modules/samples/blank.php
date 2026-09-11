<?php require_once '../../module.php';
// Blank/empty module, nice code to start with when making custom stuff.

// Configure module version number
$response['version']['module'] = 1;

/* Do PHP logic, you got access to:
	-Znote AAC sql functions (prepared, use ? placeholders):
		:db()->fetchOne($sql, $params), db()->fetchAll($sql, $params)
		:db()->execute($sql, $params) for INSERT/UPDATE/DELETE
		:db()->transaction(function ($db) { ... }) for several writes, all or nothing

	-Config values
		:etc $config['vocations']

	-Cache system
		:Sample:
		$cache = new Cache('engine/cache/api/ApiModuleName');
		if ($cache->hasExpired()) {
			$players = db()->fetchAll("SELECT `name`, `level`, `experience` FROM `players` ORDER BY `experience` DESC LIMIT 5;");

			$cache->setContent($players);
			$cache->save();
		} else {
			$players = $cache->load();
		}

	-Functions found in general.php
		:When fetching GET or POST from parameters, ALWAYS use getValue($value)
		:Etc if you want to fetch character name from url, do it like this:
		$playername = getValue($_GET['name'] ?? null);
		if ($playername !== false) {
			// $playername either contains player name, or false if failed to fetch name from GET.
		}
		:getValue is often used in 3 ways: Fetch GET and POST values, or sanitize/secure any value you wish.
		:Check ZnoteAAC\engine\function\general.php for full list of available functions.
*/

// Save the results of previous logic to the response
$response['data']['title'] = "The fabulous blank page!";

// Send the response through JSON API
SendResponse($response);
?>
