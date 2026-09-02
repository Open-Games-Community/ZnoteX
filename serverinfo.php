<?php require_once 'engine/init.php'; theme_open();
// Calculate integer values into days, hours, minutes, seconds
function toDuration($ms) {
	$duration['day'] = $ms / (24 * 60 * 60 * 1000);
	if (($duration['day'] - (int)$duration['day']) > 0)
		$duration['hour'] = ($duration['day'] - (int)$duration['day']) * 24;
	if (isset($duration['hour'])) {
		if (($duration['hour'] - (int)$duration['hour']) > 0)
			$duration['minute'] = ($duration['hour'] - (int)$duration['hour']) * 60;
		if (isset($duration['minute'])) {
			if (($duration['minute'] - (int)$duration['minute']) > 0)
				$duration['second'] = ($duration['minute'] - (int)$duration['minute']) * 60;
		}
	}
	$tmp = array();
	foreach ($duration as $type => $value) {
		if ($value >= 1) {
			$pluralType = ((int)$value === 1) ? $type : $type . 's';
			if ($type !== 'second') $tmp[] = (int)$value . " $pluralType";
			else $tmp[] = $value . " $pluralType";
		}
	}
	return implode(', ', $tmp);
}
function toYesNo($bool) {
	return ($bool) ? 'Yes' : 'No';
}
// Loading stage list
$cache = new Cache('engine/cache/stages');
$cache->useMemory(false);

/**
 * Server information.
 *
 * Everything is prepared here so the view stays markup only:
 *   $serverAdmin     the visitor may reload stages.xml / config.lua
 *   $showStagesForm  render the "Load stages.xml" form
 *   $showConfigForm  render the config.lua paste form
 *   $stagesUpdated   stages.xml was just re-read
 *   $stagesFailed    stages.xml could not be read
 *   $stagesData, $luaConfig, $stages
 */
$serverAdmin    = (user_logged_in() && is_admin($user_data));
$showStagesForm = false;
$showConfigForm = false;
$stagesUpdated  = false;
$stagesFailed   = false;

if (user_logged_in() && is_admin($user_data)) {
	if (isset($_GET['loadStages'])) {
		$stagesUpdated = true;
		// STAGES XML TO PHP ARRAY
		$stagesXML = simplexml_load_file("engine/XML/stages.xml");
		if ($stagesXML !== false) {
			$stagesData = array();
			// Load config (stages enabled or disabled)
			foreach ($stagesXML->config->attributes() as $name => $value)
				$stagesData["$name"] = "$value";
			// Load stage levels
			// Each stage XML object
			foreach ($stagesXML->stage as $stage) {
				$rowData = array();
				// Each attribute name and values on current stage object
				foreach ($stage->attributes() as $name => $value) {
					$rowData["$name"] = "$value";
				}
				// Populate XML assoc array
				$stagesData['stages'][] = $rowData;
			}
			$cache->setContent($stagesData);
			$cache->save();
		}
	} else {
		$stagesData = $cache->load();
		$showStagesForm = true;
	}
	// END STAGES XML TO PHP ARRAY
} else {
	$stagesData = $cache->load();
}
// End loading stage list

// Loading config.lua
$cache = new Cache('engine/cache/luaconfig');
$cache->useMemory(false);
if (user_logged_in() && is_admin($user_data)) {
	if (isset($_POST['loadConfig']) && isset($_POST['configData'])) {
		// Whitelist for values we are interested in
		$whitelist = array( // Etc 'maxPlayers'
			'worldType',
			'hotkeyAimbotEnabled',
			'protectionLevel',
			'killsToRedSkull',
			'killsToBlackSkull',
			'pzLocked',
			'removeChargesFromRunes',
			'timeToDecreaseFrags',
			'whiteSkullTime',
			'stairJumpExhaustion',
			'experienceByKillingPlayers',
			'expFromPlayersLevelRange',
			'loginProtocolPort',
			'maxPlayers',
			'motd',
			'onePlayerOnlinePerAccount',
			'deathLosePercent',
			'housePriceEachSQM',
			'houseRentPeriod',
			'marketOfferDuration',
			'premiumToCreateMarketOffer',
			'maxMarketOffersAtATimePerPlayer',
			'allowChangeOutfit',
			'freePremium',
			'kickIdlePlayerAfterMinutes',
			'rateExp',
			'rateSkill',
			'rateLoot',
			'rateMagic',
			'rateSpawn',
			'staminaSystem',
			'experienceStages'
		);
		// This will be the populated array with filtered relevant data
		$luaConfig = array();

		// Remove everything between first { and last }
		$poststring = $_POST['configData'];
		$first = strpos($poststring, '{');
		if ($first !== false) {
			$last = strripos($poststring, '}');
			if ($last !== false) {
				$sliced_string = substr($poststring, 0, $first).substr($poststring, $last+1);
				$poststring = $sliced_string;
			} else {
				die("Lua process error: Syntax error in config.lua");
			}
		}

		// Explode the string into string array by newline
		$rawLua = explode("\n", $poststring);
		// Clean up the array
		$length = count($rawLua);
		for ($i = 0; $i < $length; $i++) {
			// We only care about lines that have the = symbol
			if (strpos($rawLua[$i], '=') !== false) {
				// Look for inline Lua comments and remove them
				$comment = strpos($rawLua[$i], '--');
				if ($comment !== false)
					$rawLua[$i] = substr($rawLua[$i], 0, $comment);
				$rawLua[$i] = trim($rawLua[$i]); // Remove unnecessary whitespace
				// If for some reason the line is empty, ignore it. (Could be a "=" symbol inside an inline Lua comment that we sliced away)
				if (!empty($rawLua[$i])) {
					// Built a relevant data array
					$data = explode('=', $rawLua[$i]);
					// Remove unnecessary whitespace
					$data[0] = trim($data[0]);
					$data[1] = trim($data[1]);

					if (in_array($data[0], $whitelist)) {
						// Type cast: boolean
						if (in_array(strtolower($data[1]), array('true', 'false'))) {
							$data[1] = (strtolower($data[1]) === 'true') ? true : false;
						} else {
							if (strpos($data[1], '"') === false) {
								if (!in_array($data[1], array_keys($luaConfig))) {
									// Type cast: integer
									if (strlen($data[1]) > 0) {
										$data[1] = eval('return (' . $data[1] . ');');
									}
								} else {
									// Type cast: Load value from another key
									$data[1] = (isset($luaConfig[$data[1]])) ? $luaConfig[$data[1]] : null;
								}
							} else {
								// Type cast: string, just remove the quote we earlier used to determine if it was a string.
								$data[1] = str_replace('"', '', $data[1]);
							}
						}
						// Add the results
						$luaConfig[$data[0]] = $data[1];
					} // End whitelisted row
				} // End not empty row
			} // Line has \= symbol
		} // for loop
		$cache->setContent($luaConfig);
		$cache->save();
	} else {
		$luaConfig = $cache->load();
		$showConfigForm = true;
	}
} else {
	$luaConfig = $cache->load();
}
// End loading config.lua

$stages = false;

view('serverinfo');

theme_close();
