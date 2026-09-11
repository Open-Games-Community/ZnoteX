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

	$rows = db()->fetchAll("
		SELECT `id`, `parent_id`, `label`, `url`, `icon`, `target`, `visibility`
		FROM `znote_menu`
		WHERE `location` = ?
		  AND `active` = 1
		ORDER BY `sort_order` ASC, `id` ASC;
	", [$location]);

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
		if ($show && !menu_url_available((string)$row['url'])) {
			$show = false;
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

function menu_url_available(string $url): bool {
	global $config;

	$url = trim($url);
	if ($url === '' || $url === '#') {
		return true;
	}

	$path = parse_url($url, PHP_URL_PATH);
	$page = strtolower(basename($path !== null && $path !== false ? $path : $url));

	// A plugin page (page.php?plugin=X): gone from the menu the moment the
	// plugin is disabled or uninstalled, without the plugin having to run.
	if ($page === 'page.php' && function_exists('setting')) {
		parse_str((string)(parse_url($url, PHP_URL_QUERY) ?: ''), $mq);
		$mp = isset($mq['plugin']) ? preg_replace('/[^a-z0-9_-]/i', '', (string)$mq['plugin']) : '';
		if ($mp !== '') {
			return setting('plugin:' . $mp . ':enabled', '0') === '1'
				&& (string)setting('plugin:' . $mp . ':version', '') !== '';
		}
	}

	switch ($page) {
		case 'shop.php':
			return !empty($config['shop']['enabled']);
		case 'buypoints.php':
			return !empty($config['buypoints_enabled']);
		case 'guildwar.php':
		case 'guildwars.php':
			return !empty($config['guildwar_enabled']);
		case 'forum.php':
			return !empty($config['forum']['enabled']);
		case 'powergamers.php':
			return !empty($config['powergamers']['enabled']);
		case 'toponline.php':
			return !empty($config['toponline']['enabled']);
		case 'achievements.php':
			return !empty($config['Ach']);
		case 'items.php':
			return !empty($config['items']);
		case 'credits.php':
			return $config['credits_enabled'] ?? true;
		case 'queststatus.php':
			return !empty($config['queststatus_enabled']);
		default:
			return true;
	}
}

function theme_menu_label(string $label): string {
	$raw = trim($label);
	if ($raw === '') {
		return '';
	}

	if (function_exists('t') && preg_match('/^[a-z0-9_.-]+$/i', $raw)) {
		$translated = t($raw);
		if ($translated !== $raw) {
			return $translated;
		}
	}

	$keyByLabel = array(
		'account' => 'nav.account_section',
		'account management' => 'nav.account_management',
		'admin panel' => 'widget.admin.panel',
		'bans' => 'bans.title',
		'buy points' => 'shop.buy_points',
		'changelog' => 'changelog.title',
		'community' => 'nav.community',
		'contact' => 'nav.contact',
		'create account' => 'nav.register',
		'create character' => 'account.create_character',
		'credits' => 'nav.credits',
		'creatures' => 'creatures.title',
		'donate' => 'shop.buy_points',
		'download' => 'downloads.download',
		'download client' => 'nav.download_client',
		'download game' => 'nav.downloads',
		'downloads' => 'nav.downloads',
		'forum' => 'nav.forum',
		'guilds' => 'nav.guilds',
		'helpdesk' => 'helpdesk.title',
		'highscores' => 'nav.highscores',
		'home' => 'nav.home',
		'houses' => 'nav.houses',
		'information' => 'front.server_information',
		'item market' => 'nav.item_market',
		'kill statistics' => 'nav.kill_statistics',
		'kills statistics' => 'nav.kill_statistics',
		'latest deaths' => 'deaths.latest',
		'latest news' => 'nav.latest_news',
		'library' => 'nav.library',
		'log in' => 'nav.login',
		'login' => 'nav.login',
		'logout' => 'nav.logout',
		'lost account' => 'recovery.lost_account_title',
		'lost account?' => 'nav.lost_account',
		'my account' => 'nav.account',
		'news' => 'nav.news',
		'register' => 'nav.register',
		'server info' => 'nav.serverinfo',
		'server information' => 'front.server_information',
		'settings' => 'settings.title',
		'shop' => 'nav.shop',
		'spells' => 'spells.title',
		'store' => 'nav.shop',
		'support' => 'nav.support',
		'vote for us' => 'nav.vote_for_us',
		'vote for us!' => 'nav.vote_for_us',
		'who is online' => 'nav.online',
		'wikipedia' => 'nav.wikipedia',
		'wiki search' => 'nav.wiki_search',
	);

	$normalized = strtolower(preg_replace('/\s+/', ' ', str_replace(array('_', '-'), ' ', $raw)));
	if (isset($keyByLabel[$normalized]) && function_exists('t_default')) {
		return t_default($keyByLabel[$normalized], $label);
	}

	return $label;
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
	$row = db()->fetchOne("SELECT `id` FROM `znote_menu` LIMIT 1;");
	return is_array($row) && $row;
}
