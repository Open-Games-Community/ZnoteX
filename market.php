<?php require_once 'engine/init.php'; theme_open();

$server = $config['shop']['imageServer'];
$imageType = $config['shop']['imageType'];
$items = getItemList();
$compare = &$_GET['compare'];

$marketLoadError = '';
$marketMode = 'list';
$offers = array();
$historyOffers = array();
$activeSellOffers = array();
$buylist = false;
$itemname = '';

// If we failed to load items.xml, a string is returned (not an array) with
// the attempted loaded file path.
if (is_array($items) === false) {
	$marketLoadError = (string)$items;
} elseif (!$compare) {
	// If you are not comparing any items, present the list.
	$cache = new Cache('engine/cache/market');
	$cache->setExpiration(60);
	if ($cache->hasExpired()) {
		$offers = array(
			'wts' => db()->fetchAll("SELECT `mo`.`id`, `mo`.`itemtype` AS `item_id`, `mo`.`amount`, `mo`.`price`, `mo`.`created`, `mo`.`anonymous`, `p`.`name` AS `player_name` FROM `market_offers` AS `mo` INNER JOIN `players` AS `p` ON `mo`.`player_id`=`p`.`id` WHERE `mo`.`sale` = '1'  ORDER BY `mo`.`created` DESC;"),
			'wtb' => db()->fetchAll("SELECT `mo`.`id`, `mo`.`itemtype` AS `item_id`, `mo`.`amount`, `mo`.`price`, `mo`.`created`, `mo`.`anonymous`, `p`.`name` AS `player_name` FROM `market_offers` AS `mo` INNER JOIN `players` AS `p` ON `mo`.`player_id`=`p`.`id` WHERE `mo`.`sale` = '0'  ORDER BY `mo`.`created` DESC;")
		);
		$cache->setContent($offers);
		$cache->save();
	} else {
		$offers = $cache->load();
	}
} else {
	// Else you want to compare price.
	$marketMode = 'compare';
	$compare = ((int)$compare > 0) ? (int)$compare : getValue($compare);

	$conditionSql = '`itemtype` = ?';
	$conditionParams = [$compare];

	if (is_string($compare)) {
		$query = array();
		foreach ($items as $id => $name) {
			if (strpos(strtolower($name), stripslashes(strtolower($compare))) !== false) {
				$query[] = (int)$id;
			}
		}
		if (!empty($query)) {
			$conditionSql = '`itemtype` IN (' . implode(',', array_fill(0, count($query), '?')) . ')';
			$conditionParams = $query;
		} else {
			$conditionSql = false;
		}
	}

	// First list active bids.
	if ($conditionSql !== false) {
		$offers = db()->fetchAll("SELECT `mo`.`id`, `mo`.`sale`, `mo`.`itemtype` AS `item_id`, `mo`.`amount`, `mo`.`price`, `mo`.`created`, `mo`.`anonymous`, `p`.`name` AS `player_name` FROM `market_offers` AS `mo` INNER JOIN `players` AS `p` ON `mo`.`player_id`=`p`.`id` WHERE `mo`.{$conditionSql} ORDER BY `mo`.`price` ASC;", $conditionParams);
		$historyOffers = db()->fetchAll("SELECT `id`, `itemtype` AS `item_id`, `amount`, `price`, `inserted`, `expires_at` FROM `market_history` WHERE {$conditionSql} AND `state`='255' ORDER BY `price` ASC;", $conditionParams);
	}

	$itemname = (isset($items[$compare])) ? $items[$compare] : $compare;

	// Split active offers into sell offers and the want-to-buy list.
	foreach (($offers ? $offers : array()) as $o) {
		if ($o['sale'] == 0) {
			if ($buylist === false) $buylist = array();
			$buylist[] = $o;
		} else {
			$activeSellOffers[] = $o;
		}
	}
}

view('market');
theme_close();
