<?php
/**
 * Navigation menus.
 *
 * Menu links used to be hardcoded in each theme, so adding one meant editing
 * PHP, and every theme carried its own copy. They live in `znote_menu` now and
 * are edited from Admin Panel > Menus.
 *
 * A theme declares the slots it renders in theme.json:
 *
 *   "menus": {
 *     "main":    "Top navigation",
 *     "sidebar": "Left column",
 *     "footer":  "Footer links"
 *   }
 *
 * and renders one with:
 *
 *   <?php foreach (theme_menu_items('main') as $item): ?>
 *     <a href="<?= h($item['url']) ?>"><?= h($item['label']) ?></a>
 *   <?php endforeach; ?>
 *
 * The theme keeps full control of the markup - this only supplies the data,
 * already filtered for the current visitor and nested by parent.
 */

/**
 * Entries for one location, filtered by visibility and nested.
 *
 * Each item: id, label, url, icon, target, children[].
 * A parent whose children are all hidden still shows; a child of a hidden
 * parent does not, because it is unreachable.
 */
function theme_menu_items(string $location): array {
	static $cache = array();

	$location = preg_replace('/[^a-z0-9_-]/', '', strtolower($location));
	if ($location === '') {
		return array();
	}
	if (isset($cache[$location])) {
		return $cache[$location];
	}

	$rows = mysql_select_multi("
		SELECT `id`, `parent_id`, `label`, `url`, `icon`, `target`, `visibility`
		FROM `znote_menu`
		WHERE `location` = '" . mysql_znote_escape_string($location) . "'
		  AND `active` = 1
		ORDER BY `sort_order` ASC, `id` ASC;
	");

	if (!is_array($rows)) {
		// No table yet (migration not run) or nothing defined: the theme falls
		// back to whatever it hardcodes.
		return $cache[$location] = array();
	}

	$loggedIn = (function_exists('user_logged_in') && user_logged_in() === true);
	$isAdmin  = $loggedIn && isset($GLOBALS['user_data']) && is_admin($GLOBALS['user_data']);

	$visible = array();
	foreach ($rows as $row) {
		switch ($row['visibility']) {
			case 'guest': $show = !$loggedIn; break;
			case 'user':  $show = $loggedIn;  break;
			case 'admin': $show = $isAdmin;   break;
			default:      $show = true;
		}
		if ($show) {
			$visible[(int)$row['id']] = array(
				'id'       => (int)$row['id'],
				'parent'   => (int)$row['parent_id'],
				'label'    => (string)$row['label'],
				'url'      => (string)$row['url'],
				'icon'     => (string)$row['icon'],
				'target'   => (string)$row['target'],
				'children' => array(),
			);
		}
	}

	// Nest. A child whose parent was filtered out disappears with it.
	$tree = array();
	foreach ($visible as $id => $item) {
		if ($item['parent'] > 0 && isset($visible[$item['parent']])) {
			continue;
		}
		if ($item['parent'] > 0) {
			continue; // parent hidden: so is this
		}
		$tree[$id] = $item;
	}
	foreach ($visible as $id => $item) {
		if ($item['parent'] > 0 && isset($tree[$item['parent']])) {
			$tree[$item['parent']]['children'][] = $item;
		}
	}

	return $cache[$location] = array_values($tree);
}

/**
 * The menu slots the active theme declares, as slug => label.
 * Falls back to a single "main" slot so the admin page is never empty.
 */
function theme_menu_locations(?string $theme = null): array {
	$manifest = theme_manifest($theme ?? theme_active());
	$declared = $manifest['menus'] ?? null;

	if (!is_array($declared) || !$declared) {
		return array('main' => t_default('acp.menu.default_location', 'Main navigation'));
	}

	$out = array();
	foreach ($declared as $slug => $label) {
		// Accept both {"main":"Top"} and ["main","sidebar"].
		if (is_int($slug)) {
			$slug  = (string)$label;
			$label = ucfirst(str_replace(array('-', '_'), ' ', $slug));
		}
		$slug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$slug));
		if ($slug !== '') {
			$out[$slug] = (string)$label;
		}
	}

	return $out ?: array('main' => 'Main navigation');
}

/**
 * True when the menu table exists and holds at least one entry.
 * A theme can use it to decide between the managed menu and its own fallback.
 */
function theme_menu_available(): bool {
	$row = mysql_select_single("SELECT `id` FROM `znote_menu` LIMIT 1;");
	return is_array($row) && $row;
}
