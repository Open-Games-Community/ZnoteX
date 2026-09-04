<?php

function znote_bbcode_url(string $url): string {
	$plain = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

	if ($plain === '' || preg_match('/[\x00-\x1f\s<>"\']/', $plain)) {
		return '';
	}
	if (!preg_match('#^(https?://[^/]+|/(?!/))#i', $plain)) {
		return '';
	}

	return htmlspecialchars($plain, ENT_QUOTES, 'UTF-8');
}

function znote_bbcode_color(string $color): string {
	$plain = trim(html_entity_decode($color, ENT_QUOTES, 'UTF-8'));

	if (preg_match('/^#[0-9a-f]{3,8}$/i', $plain)) return $plain;
	if (preg_match('/^[a-z]{3,20}$/i', $plain)) return strtolower($plain);
	if (preg_match('/^rgba?\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*(,\s*(0|1|0?\.[0-9]+)\s*)?\)$/i', $plain)) return $plain;

	return '';
}

/**
 * For text stored WITHOUT HTML escaping - news and changelog, which the admin
 * panel writes through esc() (SQL escaping only). Escaping here means an admin
 * typing <script> gets text, not script, while BBCode still renders.
 *
 * Forum posts are already escaped by sanitize() on save, so they use
 * znote_bbcode() directly - running this on them would double-escape.
 */
function znote_bbcode_raw(?string $text): string {
	return znote_bbcode(htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'));
}

function znote_bbcode_count_images(?string $text): int {
	return preg_match_all('/\[img(?:=[^\]]*)?\]/i', (string)$text);
}

function znote_bbcode(?string $text): string {
	$text = (string)$text;
	if ($text === '') {
		return '';
	}

	$codes = array();
	$text = preg_replace_callback('/\[code\](.*?)\[\/code\]/is', static function ($m) use (&$codes) {
		$codes[] = $m[1];
		return "\x00CODE" . (count($codes) - 1) . "\x00";
	}, $text);

	foreach (array('b' => 'strong', 'i' => 'em', 'u' => 'u', 's' => 'del') as $tag => $html) {
		for ($i = 0; $i < 4; $i++) {
			$out = preg_replace('/\[' . $tag . '\](.*?)\[\/' . $tag . '\]/is', '<' . $html . '>$1</' . $html . '>', $text);
			if ($out === null || $out === $text) break;
			$text = $out;
		}
	}

	foreach (array('left', 'center', 'right', 'justify') as $align) {
		$text = preg_replace(
			'/\[' . $align . '\](.*?)\[\/' . $align . '\]/is',
			'<div class="zbb-align" style="text-align:' . $align . '">$1</div>',
			$text
		);
	}

	$text = preg_replace_callback('/\[color=([^\]]{1,30})\](.*?)\[\/color\]/is', static function ($m) {
		$color = znote_bbcode_color($m[1]);
		return ($color === '') ? $m[2] : '<span style="color:' . $color . '">' . $m[2] . '</span>';
	}, $text);

	$sizes = array(1 => '0.7em', 2 => '0.85em', 3 => '1em', 4 => '1.2em', 5 => '1.5em', 6 => '2em', 7 => '2.5em');
	$text = preg_replace_callback('/\[size=([0-9]{1,2})\](.*?)\[\/size\]/is', static function ($m) use ($sizes) {
		$size = $sizes[(int)$m[1]] ?? null;
		return ($size === null) ? $m[2] : '<span style="font-size:' . $size . '">' . $m[2] . '</span>';
	}, $text);

	$text = preg_replace_callback('/\[img(?:=[^\]]*)?\]([^\[]+?)\[\/img\]/is', static function ($m) {
		$url = znote_bbcode_url($m[1]);
		return ($url === '') ? '' : '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">'
			. '<img src="' . $url . '" alt="" class="zbb-img"></a>';
	}, $text);

	$text = preg_replace_callback('/\[(?:url|link)=([^\]]+?)\](.*?)\[\/(?:url|link)\]/is', static function ($m) {
		$url = znote_bbcode_url($m[1]);
		return ($url === '') ? $m[2] : '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $m[2] . '</a>';
	}, $text);

	$text = preg_replace_callback('/\[(?:url|link)\]([^\[]+?)\[\/(?:url|link)\]/is', static function ($m) {
		$url = znote_bbcode_url($m[1]);
		return ($url === '') ? $m[1] : '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
	}, $text);

	$text = preg_replace_callback('/\[youtube\]([^\[]+?)\[\/youtube\]/is', static function ($m) {
		$id = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
		if (preg_match('#(?:youtu\.be/|v=|embed/)([A-Za-z0-9_-]{6,20})#', $id, $found)) {
			$id = $found[1];
		}
		if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
			return '';
		}
		return '<div class="zbb-video"><iframe src="https://www.youtube.com/embed/' . $id
			. '" frameborder="0" allowfullscreen></iframe></div>';
	}, $text);

	$text = preg_replace_callback('/\[quote(?:=([^\]]{1,40}))?\](.*?)\[\/quote\]/is', static function ($m) {
		$who = trim($m[1] ?? '');
		$head = ($who !== '') ? '<cite>' . $who . ' wrote:</cite>' : '';
		return '<blockquote class="zbb-quote">' . $head . $m[2] . '</blockquote>';
	}, $text);

	$text = preg_replace('/\[\*\](.*?)\[\/\*\]/is', '<li>$1</li>', $text);
	$text = preg_replace('/\[li\](.*?)\[\/li\]/is', '<li>$1</li>', $text);
	$text = preg_replace('/\[\*\]\s*([^\[\r\n]*)/i', '<li>$1</li>', $text);
	$text = preg_replace('/\[(?:ul|list)(?:=[^\]]*)?\](.*?)\[\/(?:ul|list)\]/is', '<ul class="zbb-list">$1</ul>', $text);
	$text = preg_replace('/\[ol\](.*?)\[\/ol\]/is', '<ol class="zbb-list">$1</ol>', $text);

	$text = nl2br($text, false);

	$text = preg_replace('#<br>\s*(</?(?:ul|ol|li|blockquote|div|cite)[^>]*>)#i', '$1', $text);
	$text = preg_replace('#(</?(?:ul|ol|li|blockquote|div|cite)[^>]*>)\s*<br>#i', '$1', $text);

	$text = preg_replace_callback("/\x00CODE([0-9]+)\x00/", static function ($m) use ($codes) {
		return '<pre class="zbb-code"><code>' . ($codes[(int)$m[1]] ?? '') . '</code></pre>';
	}, $text);

	return $text;
}
