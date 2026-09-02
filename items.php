<?php require_once 'engine/init.php'; theme_open();

/**
 * Equipable items browser.
 *
 * All data preparation happens here so the view stays markup only:
 *   $itemsEnabled  the page is turned on in config.php
 *   $itemsAdmin    the visitor may regenerate the cache
 *   $itemsUpdated  the cache was just regenerated on this request
 *   $itemsFailed   engine/XML/items.xml could not be read
 *   $items         the item list, or false when the cache is empty
 */

$itemsEnabled = ($config['items'] == true);
$itemsAdmin   = (user_logged_in() && is_admin($user_data));
$itemsUpdated = false;
$itemsFailed  = false;
$items        = false;

if ($itemsEnabled) {

	$itemsCache = new Cache('engine/cache/items');

	if ($itemsAdmin && isset($_GET['update'])) {

		$itemsUpdated = true;

			// ITEMS XML TO PHP ARRAY
				$itemsXML = simplexml_load_file("engine/XML/items.xml");
				if ($itemsXML !== false) {
					$types = array();
					$type_attr = array();
					$groups = array();
	
					// This empty array will eventually contain all items grouped by type and indexed by item type
					$items = array();
	
					// Loop through each XML item object
					foreach ($itemsXML as $type => $item) {
						// Get item types
						if (!in_array($type, $types)) {
							$types[] = $type;
							$type_attr[$type] = array();
						}
						// Get item attributes
						$attributes = array();
						// Extract attribute values from the XML object and store it in a more manage friendly way $attributes
						foreach ($item->attributes() as $aName => $aValue)
							$attributes["$aName"] = "$aValue";
						// Remove unececsary attributes
						if (isset($attributes['plural'])) unset($attributes['plural']);
						//if (isset($attributes['id'])) unset($attributes['id']);
						//if (isset($attributes['fromid'])) unset($attributes['fromid']);
						//if (isset($attributes['toid'])) unset($attributes['toid']);
						if (isset($attributes['editorsuffix'])) unset($attributes['editorsuffix']);
						if (isset($attributes['article'])) unset($attributes['article']);
						// Populate type attributes
						foreach (array_keys($attributes) as $attr) {
							if (!in_array($attr, $type_attr[$type]))
								$type_attr[$type][] = $attr;
						}
	
						// Loop through every <attribute> object inside the <item> object
						$item_attributes = array();
						$iai = array();
	
						foreach ($item as $attribute) {
							foreach ($attribute->attributes() as $aName => $aValue) {
								if($aName == 'key') {
									$attribute_attributes["$aName"] = "$aValue";
									$iai[] = $attribute_attributes[$aName];
								}
							}
						}
						foreach ($item as $attribute) {
							foreach ($attribute->attributes() as $aName => $aValue) {
								$attribute_attributes["$aName"] = "$aValue";
								if(in_array($attribute_attributes[$aName], $iai)) {
									$whatis = $attribute_attributes[$aName];
								} else {
									$item_attributes[$whatis] = (isset($attribute_attributes[$aName])) ? $attribute_attributes[$aName] : false;
								}
							}
						}
						foreach (array_keys($attributes) as $attr) {
							if (!in_array($attr, $type_attr[$type]))
								$type_attr[$type][] = $attr;
						}
	
						// Add items with slotType or weaponType (TFS 1.x default)
						if(isset($attributes['id'])) $id = (isset($attributes['id'])) ? $attributes['id'] : false;
						if(isset($attributes['fromid'])) $id = (isset($attributes['name'])) ? $attributes['name'] : false;
						if (isset($item_attributes['slotType']) || isset($item_attributes['weaponType'])) {
							$items[$type][$id] = array('attributes' => $item_attributes);
	
							// Populate item array with potential relevant attributes for the item type
							foreach ($type_attr[$type] as $att)
								$items[$type][$id][$att] = (isset($attributes[$att])) ? $attributes[$att] : false;
						}
	
	
						$save = array($items);
	
	
					}
					$itemsCache->setContent($items);
					$itemsCache->save();
		} else {
			$itemsFailed = true;
		}

	} else {
		$items = $itemsCache->load();
	}
}

view('items');

theme_close();
