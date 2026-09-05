<?php

function translate_catalog(): array {
	return array(
		'en'    => array('name' => 'English',    'native' => 'English',    'flag' => 'uk'),
		'pt_br' => array('name' => 'Portuguese', 'native' => 'Português',  'flag' => 'pt'),
		'es'    => array('name' => 'Spanish',    'native' => 'Español',    'flag' => 'es'),
		'pl'    => array('name' => 'Polish',     'native' => 'Polski',     'flag' => 'pl'),
		'de'    => array('name' => 'German',     'native' => 'Deutsch',    'flag' => 'de')
	);
}

function translate_sanitize(string $code): string {
	$code = strtolower(preg_replace('/[^a-zA-Z_]/', '', $code) ?? '');
	return isset(translate_catalog()[$code]) ? $code : '';
}

function translate_default(): string {
	$code = translate_sanitize((string)(config('language') ?? 'en'));
	return ($code !== '') ? $code : 'en';
}

function translate_enabled(): array {
	$configured = config('languages_enabled');

	if (is_string($configured)) {
		$decoded = json_decode($configured, true);
		$configured = is_array($decoded) ? $decoded : explode(',', $configured);
	}

	$enabled = array();

	if (is_array($configured)) {
		foreach ($configured as $code) {
			$clean = translate_sanitize((string)$code);
			if ($clean !== '' && !in_array($clean, $enabled, true)) {
				$enabled[] = $clean;
			}
		}
	}

	if (!$enabled) {
		$enabled = array_keys(translate_catalog());
	}

	$default = translate_default();
	if (!in_array($default, $enabled, true)) {
		array_unshift($enabled, $default);
	}

	return $enabled;
}

function translate_from_browser(): string {
	$header = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
	if ($header === '') {
		return '';
	}

	$enabled = translate_enabled();
	$ranked  = array();

	foreach (explode(',', $header) as $part) {
		$bits = explode(';q=', trim($part));
		$tag  = strtolower(str_replace('-', '_', trim($bits[0])));
		$q    = isset($bits[1]) ? (float)$bits[1] : 1.0;

		if ($tag === '') {
			continue;
		}

		$ranked[] = array('tag' => $tag, 'q' => $q);
	}

	usort($ranked, function ($a, $b) { return $b['q'] <=> $a['q']; });

	foreach ($ranked as $entry) {
		$tag = $entry['tag'];

		if (in_array($tag, $enabled, true)) {
			return $tag;
		}

		$base = explode('_', $tag)[0];

		foreach ($enabled as $code) {
			if ($code === $base || strpos($code, $base . '_') === 0) {
				return $code;
			}
		}
	}

	return '';
}

function translate_active(?string $set = null): string {
	static $active = null;

	if ($set !== null) {
		$active = $set;
		return $active;
	}

	if ($active !== null) {
		return $active;
	}

	$enabled = translate_enabled();

	$fromUrl = translate_sanitize((string)($_GET['lang'] ?? ''));
	if ($fromUrl !== '' && in_array($fromUrl, $enabled, true)) {
		translate_remember($fromUrl);
		return $active = $fromUrl;
	}

	$prefix    = (string)($GLOBALS['config']['session_prefix'] ?? '');
	$fromStore = translate_sanitize((string)($_SESSION[$prefix . 'language'] ?? $_COOKIE[$prefix . 'language'] ?? ''));
	if ($fromStore !== '' && in_array($fromStore, $enabled, true)) {
		return $active = $fromStore;
	}

	$fromBrowser = translate_from_browser();
	if ($fromBrowser !== '') {
		return $active = $fromBrowser;
	}

	return $active = translate_default();
}

function translate_remember(string $code): void {
	$code = translate_sanitize($code);
	if ($code === '' || !in_array($code, translate_enabled(), true)) {
		return;
	}

	$prefix = (string)($GLOBALS['config']['session_prefix'] ?? '');

	if (session_status() === PHP_SESSION_ACTIVE) {
		$_SESSION[$prefix . 'language'] = $code;
	}

	if (!headers_sent()) {
		setcookie($prefix . 'language', $code, array(
			'expires'  => time() + (365 * 86400),
			'path'     => '/',
			'samesite' => 'Lax'
		));
	}

	$_COOKIE[$prefix . 'language'] = $code;
}

function translate_load(string $code): array {
	static $loaded = array();

	$code = translate_sanitize($code);
	if ($code === '') {
		return array();
	}

	if (isset($loaded[$code])) {
		return $loaded[$code];
	}

	$strings = array();

	$file = __DIR__ . '/../../locale/' . $code . '.php';
	if (is_file($file)) {
		$core = include $file;
		if (is_array($core)) {
			$strings = $core;
		}
	}

	return $loaded[$code] = $strings + translate_plugin_strings($code);
}

function translate_plugin_strings(string $code): array {
	if (!function_exists('znote_plugins')) {
		return array();
	}

	$strings = array();

	foreach (znote_plugins() as $name => $plugin) {
		if (empty($plugin['enabled'])) {
			continue;
		}

		$file = rtrim((string)($plugin['path'] ?? ''), '/\\') . '/locale/' . $code . '.php';
		if (!is_file($file)) {
			continue;
		}

		$loaded = include $file;
		if (is_array($loaded)) {
			$strings += $loaded;
		}
	}

	return $strings;
}

function t(string $key, array $vars = array()): string {
	$active = translate_active();

	$strings = translate_load($active);
	$text    = $strings[$key] ?? null;

	if ($text === null && $active !== 'en') {
		$fallback = translate_load('en');
		$text     = $fallback[$key] ?? null;
	}

	if ($text === null) {
		$text = $key;
	}

	if ($vars) {
		$replace = array();
		foreach ($vars as $name => $value) {
			$replace['{' . $name . '}'] = (string)$value;
		}
		$text = strtr($text, $replace);
	}

	return $text;
}

function te(string $key, array $vars = array()): void {
	echo t($key, $vars);
}

if (!function_exists('h')) {
	function h($s): string {
		return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
	}
}

function t_default(string $key, string $fallback, array $vars = array()): string {
	$text = t($key, $vars);
	return ($text === $key) ? $fallback : $text;
}

function translate_flag(string $flag): string {
	if (!preg_match('/^[a-z]{2,3}$/', $flag) || !is_file(__DIR__ . '/../../assets/flags/' . $flag . '.png')) {
		return '';
	}

	return '<img src="assets/flags/' . $flag . '.png" alt="" loading="lazy">';
}

function translate_url(string $code): string {
	$query = $_GET;
	$query['lang'] = $code;

	$path = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');

	return htmlspecialchars(($path === false ? '/' : $path) . '?' . http_build_query($query), ENT_QUOTES, 'UTF-8');
}

function translate_selector(): string {
	$enabled = translate_enabled();

	if (count($enabled) < 2) {
		return '';
	}

	$catalog = translate_catalog();
	$active  = translate_active();
	$current = $catalog[$active] ?? $catalog['en'];

	$items = '';
	foreach ($enabled as $code) {
		$entry = $catalog[$code] ?? null;
		if ($entry === null) {
			continue;
		}

		$items .= '<a href="' . translate_url($code) . '" hreflang="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"'
			. ($code === $active ? ' class="is-active" aria-current="true"' : '') . '>'
			. '<i>' . translate_flag($entry['flag']) . '</i>'
			. '<span>' . htmlspecialchars($entry['native'], ENT_QUOTES, 'UTF-8') . '</span></a>';
	}

	return '<div class="znote-lang" id="znoteLang">'
		. '<button type="button" class="znote-lang-current" aria-haspopup="true" aria-expanded="false" aria-label="'
		. htmlspecialchars(t('language.choose'), ENT_QUOTES, 'UTF-8') . '">'
		. '<i>' . translate_flag($current['flag']) . '</i>'
		. '<span>' . htmlspecialchars(strtoupper(explode('_', $active)[0]), ENT_QUOTES, 'UTF-8') . '</span>'
		. '<em></em></button>'
		. '<div class="znote-lang-menu">' . $items . '</div></div>';
}

function translate_selector_assets(): string {
	return '<style>'
		. '.znote-lang{position:fixed;top:12px;right:12px;z-index:9999;font:500 13px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}'
		. '.znote-lang *{box-sizing:border-box}'
		. '.znote-lang i{display:block;width:20px;height:14px;overflow:hidden;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.25)}'
		. '.znote-lang i img{display:block;width:100%;height:100%;object-fit:cover}'
		. '.znote-lang-current{display:flex;align-items:center;gap:7px;padding:6px 9px;border:0;border-radius:6px;cursor:pointer;'
		. 'background:rgba(20,20,24,.82);color:#f2f2f4;backdrop-filter:blur(6px);box-shadow:0 2px 10px rgba(0,0,0,.3)}'
		. '.znote-lang-current:hover{background:rgba(20,20,24,.95)}'
		. '.znote-lang-current em{width:0;height:0;border:4px solid transparent;border-top-color:currentColor;margin-top:2px}'
		. '.znote-lang-menu{position:absolute;top:calc(100% + 6px);right:0;min-width:170px;padding:5px;border-radius:8px;'
		. 'background:rgba(20,20,24,.96);box-shadow:0 6px 24px rgba(0,0,0,.35);display:none}'
		. '.znote-lang.is-open .znote-lang-menu{display:block}'
		. '.znote-lang-menu a{display:flex;align-items:center;gap:9px;padding:7px 9px;border-radius:5px;color:#e8e8ec;text-decoration:none;white-space:nowrap}'
		. '.znote-lang-menu a:hover{background:rgba(255,255,255,.1)}'
		. '.znote-lang-menu a.is-active{background:rgba(255,255,255,.16)}'
		. '@media(max-width:640px){.znote-lang{top:8px;right:8px}.znote-lang-current span{display:none}}'
		. '@media print{.znote-lang{display:none}}'
		. '</style>'
		. '<script>(function(){var r=document.getElementById("znoteLang");if(!r)return;'
		. 'var b=r.querySelector(".znote-lang-current");'
		. 'b.addEventListener("click",function(e){e.stopPropagation();var o=r.classList.toggle("is-open");b.setAttribute("aria-expanded",o?"true":"false");});'
		. 'document.addEventListener("click",function(){r.classList.remove("is-open");b.setAttribute("aria-expanded","false");});'
		. 'document.addEventListener("keydown",function(e){if(e.key==="Escape"){r.classList.remove("is-open");b.setAttribute("aria-expanded","false");}});'
		. '})();</script>';
}

function translate_selector_rendered(?bool $set = null): bool {
	static $done = false;

	if ($set !== null) {
		$done = $set;
	}

	return $done;
}

function translate_inject(): string {
	if (!config('language_selector') || translate_selector_rendered()) {
		return '';
	}

	$markup = translate_selector();
	if ($markup === '') {
		return '';
	}

	translate_selector_rendered(true);

	return $markup . translate_selector_assets();
}
