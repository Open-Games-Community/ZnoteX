<?php require_once 'engine/init.php'; theme_open();

// Loading spell list
$spellsCache = new Cache('engine/cache/spells');
$spellsCache->useMemory(false);

/**
 * Spell list.
 *
 * Prepared here so the view stays markup only:
 *   $showSpellsForm  render the "Generate new cache" form
 *   $spellsUpdated   spells.xml was just re-read
 *   $spellsFailed    spells.xml could not be read
 *   $spells          the spell list, or false
 */
$showSpellsForm = false;
$spellsUpdated  = false;
$spellsFailed   = false;

if (user_logged_in() && is_admin($user_data)) {
	if (isset($_GET['update'])) {
		$spellsUpdated = true;
		// SPELLS XML TO PHP ARRAY
		$spellsXML = simplexml_load_file("engine/XML/spells.xml");
		if ($spellsXML !== false) {
			$types = array();
			$type_attr = array();
			$groups = array();

			// This empty array will eventually contain all spells grouped by type and indexed by spell name
			$spells = array();

			// Loop through each XML spell object
			foreach ($spellsXML as $type => $spell) {
				// Get spell types
				if (!in_array($type, $types)) {
					$types[] = $type;
					$type_attr[$type] = array();
				}
				// Get spell attributes
				$attributes = array();
				// Extract attribute values from the XML object and store it in a more manage friendly way $attributes
				foreach ($spell->attributes() as $aName => $aValue)
					$attributes["$aName"] = "$aValue";
				// Remove unececsary attributes
				if (isset($attributes['script'])) unset($attributes['script']);
				if (isset($attributes['spellid'])) unset($attributes['spellid']);
				//if (isset($attributes['id'])) unset($attributes['id']);
				//if (isset($attributes['conjureId'])) unset($attributes['conjureId']);
				if (isset($attributes['function'])) unset($attributes['function']);

				// Alias attributes
				if (isset($attributes['level'])) $attributes['lvl'] = $attributes['level'];
				if (isset($attributes['magiclevel'])) $attributes['maglv'] = $attributes['magiclevel'];

				// Populate type attributes
				foreach (array_keys($attributes) as $attr) {
					if (!in_array($attr, $type_attr[$type]))
						$type_attr[$type][] = $attr;
				}
				// Get spell groups
				if (isset($attributes['group'])) {
					if (!in_array($attributes['group'], $groups))
						$groups[] = $attributes['group'];
				}
				// Get spell vocations
				$vocations = array();
				foreach ($spell->vocation as $vocation) {
					foreach ($vocation->attributes() as $attributeName => $attributeValue) {
						if ("$attributeName" == "name") {
							$vocId = vocation_name_to_id("$attributeValue");
							$vocations[] = ($vocId !== false) ? $vocId : "$attributeValue";
						} elseif ("$attributeName" == "id") {
							$vocations[] = (int)"$attributeValue";
						}
					}
				}
				// Exclude monster spells (Monster spells looks like this on the ORTS data pack)
				$words = (isset($attributes['words'])) ? $attributes['words'] : false;
				// Also exclude "house spells" such as aleta sio.
				$name = (isset($attributes['name'])) ? $attributes['name'] : false;
				if (substr($words, 0, 3) !== '###' && substr($name, 0, 5) !== 'House') {
					// Build full spell list where the spell name is the key to the spell array.
					$spells[$type][$name] = array('vocations' => $vocations);
					// Populate spell array with potential relevant attributes for the spell type
					foreach ($type_attr[$type] as $att)
						$spells[$type][$name][$att] = (isset($attributes[$att])) ? $attributes[$att] : false;
				}
			}

			// Sort the spell list properly
			foreach (array_keys($spells) as $type) {
				usort($spells[$type], function ($a, $b) {
					if (isset($a['lvl']))
						return $a['lvl'] - $b['lvl'];
					if (isset($a['maglv']))
						return $a['maglv'] - $b['maglv'];
					return -1;
				});
			}
			$spellsCache->setContent($spells);
			$spellsCache->save();
		} else {
			$spellsFailed = true;
		}
	} else {
		$spells = $spellsCache->load();
		$showSpellsForm = true;
	}
	// END SPELLS XML TO PHP ARRAY
} else {
	$spells = $spellsCache->load();
}
// End loading spell list

view('spells');

theme_close();
