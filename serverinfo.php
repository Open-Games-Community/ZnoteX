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
$serverAdmin    = (user_logged_in() && is_admin($user_data));
$showStagesForm = false;
$showConfigForm = false;
$stagesUpdated  = false;
$stagesFailed   = false;

$stagesData = serverdata_load('stages');
if ($stagesData === false && is_file(serverdata_file('stages.xml'))) {
	serverdata_rebuild('stages');
	$stagesData = serverdata_load('stages');
}

$luaConfig = serverdata_load('config');

$stages = false;

view('serverinfo');

if (!minimap_was_rendered()) {
	minimap_render();
}

theme_close();
