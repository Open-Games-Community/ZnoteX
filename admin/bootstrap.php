<?php
/**
 * ZnoteX Admin Control Panel - shared runtime.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

if (!function_exists('h')) {
	function h($s): string {
		return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('intv')) {
	function intv($v): int {
		return is_numeric($v) ? (int)$v : 0;
	}
}
if (!function_exists('esc')) {
	function esc($s): string {
		return mysql_znote_escape_string((string)($s ?? ''));
	}
}

function acp_site(string $path = ''): string {
	return '../' . ltrim($path, '/');
}

function acp_url(string $module = 'dashboard', array $params = []): string {
	return 'index.php?' . http_build_query(array_merge(['p' => $module], $params));
}

function acp_redirect(string $module = 'dashboard', array $params = []): void {
	header('Location: ' . acp_url($module, $params));
	exit;
}

const ACP_GROUP_ORDER = [
	'Overview' => 10,
	'Players'  => 20,
	'Content'  => 30,
	'Support'  => 40,
	'Economy'  => 50,
	'Plugins'  => 60,
];

function acp_parse_module_header(string $file): array {
	$head = (string)file_get_contents($file, false, null, 0, 1500);
	$meta = [];

	if (preg_match('~/\*\*(.*?)\*/~s', $head, $block)) {
		foreach (preg_split('~\R~', $block[1]) as $line) {
			if (preg_match('~^\s*\*\s*([A-Za-z]+)\s*:\s*(.+?)\s*$~', $line, $kv)) {
				$meta[strtolower($kv[1])] = $kv[2];
			}
		}
	}

	return $meta;
}

function acp_modules(): array {
	static $modules = null;
	if ($modules !== null) {
		return $modules;
	}

	$modules = [];

	foreach (glob(ACP_ROOT . '/modules/*.php') ?: [] as $file) {
		$key = basename($file, '.php');
		if ($key === '' || $key[0] === '_') {
			continue;
		}

		$meta = acp_parse_module_header($file);

		$modules[$key] = [
			'key'         => $key,
			'file'        => $file,
			'title'       => $meta['title'] ?? ucwords(str_replace('_', ' ', $key)),
			'icon'        => $meta['icon'] ?? 'fa-square-o',
			'group'       => $meta['group'] ?? 'Other',
			'order'       => isset($meta['order']) ? (int)$meta['order'] : 100,
			'description' => $meta['description'] ?? '',
			'url'         => $meta['url'] ?? null,
			'target'      => $meta['target'] ?? null,
		];
	}

	// Modules contributed by enabled plugins, listed beside the built-in ones.
	// Their key is prefixed with the plugin name, so a plugin can never shadow
	// a core module by choosing the same filename.
	if (function_exists('znote_plugin_admin_modules')) {
		foreach (znote_plugin_admin_modules() as $key => $file) {
			$meta = acp_parse_module_header($file);

			$modules[$key] = array(
				'key'         => $key,
				'file'        => $file,
				'title'       => $meta['title'] ?? ucwords(str_replace('_', ' ', $key)),
				'icon'        => $meta['icon'] ?? 'fa-plug',
				'group'       => $meta['group'] ?? 'Plugins',
				'order'       => isset($meta['order']) ? (int)$meta['order'] : 100,
				'description' => $meta['description'] ?? '',
				'url'         => $meta['url'] ?? null,
				'target'      => $meta['target'] ?? null,
			);
		}
	}

	uasort($modules, static function (array $a, array $b): int {
		$ga = ACP_GROUP_ORDER[$a['group']] ?? 900;
		$gb = ACP_GROUP_ORDER[$b['group']] ?? 900;

		return [$ga, $a['order'], strtolower($a['title'])]
			<=> [$gb, $b['order'], strtolower($b['title'])];
	});

	return $modules;
}

function acp_nav_groups(): array {
	$groups = [];
	foreach (acp_modules() as $key => $module) {
		$groups[$module['group']][$key] = $module;
	}
	return $groups;
}

function acp_badge(string $key): ?int {
	$fn = 'acp_badge_' . $key;
	if (!function_exists($fn)) {
		return null;
	}
	$count = (int)$fn();
	return $count > 0 ? $count : null;
}

function acp_count(string $sql): int {
	$row = mysql_select_single($sql);
	return is_array($row) && $row ? (int)reset($row) : 0;
}

function acp_badge_reports(): int {
	return acp_count("SELECT COUNT(*) AS `c` FROM `znote_player_reports` WHERE `status` IN (0, 1, 2);");
}

function acp_badge_helpdesk(): int {
	return acp_count("SELECT COUNT(*) AS `c` FROM `znote_tickets` WHERE `status` <> 'CLOSED';");
}

function acp_badge_gallery(): int {
	return acp_count("SELECT COUNT(*) AS `c` FROM `znote_images` WHERE `status` = 1;");
}

function acp_badge_feedback(): int {
	$cache = new Cache('engine/cache/asideFeedbackCount');
	if (!$cache->hasExpired()) {
		return (int)$cache->load();
	}

	$new = 0;
	$threads = mysql_select_multi("SELECT `id`, `player_id` FROM `znote_forum_threads` WHERE `forum_id`='4' AND `closed`='0';");

	if (is_array($threads)) {
		$staffIds = [];
		$staffs = mysql_select_multi("SELECT `id` FROM `players` WHERE `group_id` > '1';");
		if (is_array($staffs)) {
			foreach ($staffs as $staff) {
				$staffIds[(int)$staff['id']] = true;
			}
		}

		foreach ($threads as $thread) {
			$answered = false;
			$posts = mysql_select_multi("SELECT `id`, `player_id` FROM `znote_forum_posts` WHERE `thread_id`='" . (int)$thread['id'] . "';");

			if (is_array($posts)) {
				foreach ($posts as $post) {
					if (isset($staffIds[(int)$post['player_id']])) {
						$answered = true;
						break;
					}
				}
			}

			if (!$answered) {
				$new++;
			}
		}
	}

	$cache->setContent($new);
	$cache->save();

	return $new;
}

function acp_badge_shop_orders(): int {
	return acp_count("SELECT COUNT(*) AS `c` FROM `znote_shop_orders`;");
}

function acp_flash(string $type, string $message): void {
	$_SESSION['acp_flash'][] = ['type' => $type, 'message' => $message];
}

function acp_flash_success(string $m): void { acp_flash('success', $m); }
function acp_flash_error(string $m): void   { acp_flash('error', $m); }
function acp_flash_info(string $m): void    { acp_flash('info', $m); }

function acp_take_flashes(): array {
	$flashes = $_SESSION['acp_flash'] ?? [];
	unset($_SESSION['acp_flash']);
	return is_array($flashes) ? $flashes : [];
}

function acp_csrf(): string {
	if (empty($_SESSION['acp_csrf'])) {
		$_SESSION['acp_csrf'] = bin2hex(random_bytes(16));
	}
	return $_SESSION['acp_csrf'];
}

function acp_csrf_field(): string {
	return '<input type="hidden" name="csrf_token" value="' . h(acp_csrf()) . '">';
}

function acp_verify_csrf(): bool {
	return !empty($_POST['csrf_token'])
		&& is_string($_POST['csrf_token'])
		&& hash_equals(acp_csrf(), $_POST['csrf_token']);
}

function acp_card_open(string $title = '', string $subtitle = '', string $extraClass = ''): void {
	echo '<section class="acp-card ' . h($extraClass) . '">';
	if ($title !== '') {
		echo '<header class="acp-card-head"><h2>' . h($title) . '</h2>';
		if ($subtitle !== '') {
			echo '<p>' . h($subtitle) . '</p>';
		}
		echo '</header>';
	}
	echo '<div class="acp-card-body">';
}

function acp_card_close(): void {
	echo '</div></section>';
}

function acp_empty(string $message, string $icon = 'fa-inbox'): void {
	echo '<div class="acp-empty"><i class="fa ' . h($icon) . '"></i><p>' . h($message) . '</p></div>';
}

function acp_stat(string $label, $value, string $icon = 'fa-circle-o', ?string $href = null, string $tone = 'blue'): void {
	$display = is_int($value) ? number_format($value) : (string)$value;

	$inner = '<span class="acp-stat-icon"><i class="fa ' . h($icon) . '"></i></span>'
		. '<span class="acp-stat-text">'
		. '<span class="acp-stat-label">' . h($label) . '</span>'
		. '<span class="acp-stat-value">' . h($display) . '</span>'
		. '</span>';

	$class = 'acp-stat acp-stat--' . h($tone);

	echo $href !== null
		? '<a class="' . $class . '" href="' . h($href) . '">' . $inner . '</a>'
		: '<div class="' . $class . '">' . $inner . '</div>';
}
