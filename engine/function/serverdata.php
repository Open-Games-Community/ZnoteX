<?php

const ZNOTE_SERVERDATA_DIR      = 'engine/XML';
const ZNOTE_SERVERDATA_MONSTERS = 'engine/XML/monster';
const ZNOTE_SERVERDATA_MAX_XML  = 33554432;
const ZNOTE_SERVERDATA_MAX_LUA  = 1048576;
const ZNOTE_SERVERDATA_MAX_ZIP  = 33554432;
const ZNOTE_SERVERDATA_MAX_UNZIP = 134217728;
const ZNOTE_SERVERDATA_MAX_FILES = 4000;

function serverdata_root(): string
{
	return dirname(__DIR__, 2);
}

/** "8M" and friends, as php.ini writes them, in bytes. */
function serverdata_ini_bytes(string $value): int
{
	$value = trim($value);
	if ($value === '') {
		return 0;
	}

	$number = (int)$value;

	switch (strtolower(substr($value, -1))) {
		case 'g': return $number * 1073741824;
		case 'm': return $number * 1048576;
		case 'k': return $number * 1024;
	}

	return $number;
}

function serverdata_human_size(int $bytes): string
{
	if ($bytes >= 1048576) {
		return number_format($bytes / 1048576, 1) . ' MB';
	}
	if ($bytes >= 1024) {
		return number_format($bytes / 1024, 1) . ' KB';
	}

	return $bytes . ' B';
}

function serverdata_dir(): string
{
	return serverdata_root() . '/' . ZNOTE_SERVERDATA_DIR;
}

function serverdata_monster_dir(): string
{
	return serverdata_root() . '/' . ZNOTE_SERVERDATA_MONSTERS;
}

function serverdata_file(string $name): string
{
	return serverdata_dir() . '/' . $name;
}

function serverdata_sources(): array
{
	return array(
		// A TFS config.lua carries the MySQL password, and engine/XML is served
		// by the web server, so the file itself is never kept: it is read once
		// from the upload and only the whitelisted values survive.
		'config' => array(
			'label'  => 'Server information',
			'file'   => 'config.lua',
			'cache'  => 'engine/cache/luaconfig',
			'accept' => '.lua',
			'page'   => 'serverinfo.php',
			'store'  => false,
			'help'   => 'Your server config.lua. Only the whitelisted settings are kept - the file itself is parsed and discarded, never stored where it could be downloaded.'
		),
		'stages' => array(
			'label'  => 'Experience stages',
			'file'   => 'stages.xml',
			'cache'  => 'engine/cache/stages',
			'accept' => '.xml',
			'page'   => 'serverinfo.php',
			'help'   => 'data/XML/stages.xml. Only used when stages are enabled in it or in config.lua.'
		),
		'items' => array(
			'label'  => 'Items',
			'file'   => 'items.xml',
			'cache'  => 'engine/cache/items',
			'accept' => '.xml',
			'page'   => 'items.php',
			'help'   => 'data/items/items.xml. Only equipable items (slotType or weaponType) are published.'
		),
		'spells' => array(
			'label'  => 'Spells',
			'file'   => 'spells.xml',
			'cache'  => 'engine/cache/spells',
			'accept' => '.xml',
			'page'   => 'spells.php',
			'help'   => 'data/spells/spells.xml. Monster spells and house spells are filtered out.'
		),
		'creatures' => array(
			'label'  => 'Monsters',
			'file'   => 'monster/monsters.xml',
			'cache'  => 'engine/cache/creatures',
			'accept' => '.xml,.zip',
			'page'   => 'creatures.php',
			'help'   => 'monsters.xml only lists names and file paths. Upload a .zip of data/monster/ to get health, experience, speed and race.'
		)
	);
}

function serverdata_cache(string $key): ?Cache
{
	$sources = serverdata_sources();
	if (!isset($sources[$key])) {
		return null;
	}

	$cache = new Cache($sources[$key]['cache']);
	$cache->useMemory(false);
	$cache->setExpiration(PHP_INT_MAX);

	return $cache;
}

function serverdata_load(string $key)
{
	$cache = serverdata_cache($key);
	if ($cache === null) {
		return false;
	}

	$loaded = $cache->load();

	return is_array($loaded) ? $loaded : false;
}

function serverdata_store(string $key, array $value): bool
{
	$cache = serverdata_cache($key);
	if ($cache === null) {
		return false;
	}

	$cache->setContent($value);
	serverdata_drop_derived($key);

	return $cache->save();
}

function serverdata_forget(string $key): void
{
	$sources = serverdata_sources();
	if (!isset($sources[$key])) {
		return;
	}

	@unlink(serverdata_root() . '/' . $sources[$key]['cache'] . Cache::EXT);
	serverdata_drop_derived($key);
}

/**
 * monster_loot.php builds its own cache out of items.xml and the monster files,
 * so it goes stale the moment either of those is written or removed.
 */
function serverdata_drop_derived(string $key): void
{
	if ($key === 'items' || $key === 'creatures') {
		@unlink(serverdata_root() . '/engine/cache/monster_loot' . Cache::EXT);
	}
}

function serverdata_parse_items(string $path)
{
	$xml = @simplexml_load_file($path);
	if ($xml === false) {
		return false;
	}

	$typeAttributes = array();
	$items          = array();

	foreach ($xml as $type => $item) {
		if (!isset($typeAttributes[$type])) {
			$typeAttributes[$type] = array();
		}

		$attributes = array();
		foreach ($item->attributes() as $name => $value) {
			$attributes["$name"] = "$value";
		}

		unset($attributes['plural'], $attributes['editorsuffix'], $attributes['article']);

		foreach (array_keys($attributes) as $attribute) {
			if (!in_array($attribute, $typeAttributes[$type], true)) {
				$typeAttributes[$type][] = $attribute;
			}
		}

		$keys           = array();
		$itemAttributes = array();

		foreach ($item as $node) {
			foreach ($node->attributes() as $name => $value) {
				if ($name === 'key') {
					$keys[] = "$value";
				}
			}
		}

		$current = null;
		foreach ($item as $node) {
			foreach ($node->attributes() as $name => $value) {
				$value = "$value";
				if (in_array($value, $keys, true)) {
					$current = $value;
				} else if ($current !== null) {
					$itemAttributes[$current] = $value;
				}
			}
		}

		if (!isset($itemAttributes['slotType']) && !isset($itemAttributes['weaponType'])) {
			continue;
		}

		$id = $attributes['id'] ?? ($attributes['name'] ?? null);
		if ($id === null) {
			continue;
		}

		$items[$type][$id] = array('attributes' => $itemAttributes);
		foreach ($typeAttributes[$type] as $attribute) {
			$items[$type][$id][$attribute] = $attributes[$attribute] ?? false;
		}
	}

	return $items;
}

function serverdata_parse_spells(string $path)
{
	$xml = @simplexml_load_file($path);
	if ($xml === false) {
		return false;
	}

	$typeAttributes = array();
	$spells         = array();

	foreach ($xml as $type => $spell) {
		if (!isset($typeAttributes[$type])) {
			$typeAttributes[$type] = array();
		}

		$attributes = array();
		foreach ($spell->attributes() as $name => $value) {
			$attributes["$name"] = "$value";
		}

		unset($attributes['script'], $attributes['spellid'], $attributes['function']);

		if (isset($attributes['level'])) {
			$attributes['lvl'] = $attributes['level'];
		}
		if (isset($attributes['magiclevel'])) {
			$attributes['maglv'] = $attributes['magiclevel'];
		}

		foreach (array_keys($attributes) as $attribute) {
			if (!in_array($attribute, $typeAttributes[$type], true)) {
				$typeAttributes[$type][] = $attribute;
			}
		}

		$vocations = array();
		foreach ($spell->vocation as $vocation) {
			foreach ($vocation->attributes() as $name => $value) {
				if ("$name" === 'name') {
					$id          = vocation_name_to_id("$value");
					$vocations[] = ($id !== false) ? $id : "$value";
				} else if ("$name" === 'id') {
					$vocations[] = (int)"$value";
				}
			}
		}

		$words = $attributes['words'] ?? '';
		$name  = $attributes['name'] ?? '';

		if (substr($words, 0, 3) === '###' || substr($name, 0, 5) === 'House' || $name === '') {
			continue;
		}

		$spells[$type][$name] = array('vocations' => $vocations);
		foreach ($typeAttributes[$type] as $attribute) {
			$spells[$type][$name][$attribute] = $attributes[$attribute] ?? false;
		}
	}

	foreach (array_keys($spells) as $type) {
		usort($spells[$type], static function (array $a, array $b): int {
			if (isset($a['lvl'], $b['lvl'])) {
				return (int)$a['lvl'] - (int)$b['lvl'];
			}
			if (isset($a['maglv'], $b['maglv'])) {
				return (int)$a['maglv'] - (int)$b['maglv'];
			}
			return -1;
		});
	}

	return $spells;
}

function serverdata_parse_stages(string $path)
{
	$xml = @simplexml_load_file($path);
	if ($xml === false) {
		return false;
	}

	$stages = array();
	foreach ($xml->config->attributes() as $name => $value) {
		$stages["$name"] = "$value";
	}

	$stages['stages'] = array();
	foreach ($xml->stage as $stage) {
		$row = array();
		foreach ($stage->attributes() as $name => $value) {
			$row["$name"] = "$value";
		}
		$stages['stages'][] = $row;
	}

	return $stages;
}

function serverdata_config_whitelist(): array
{
	return array(
		'worldType', 'hotkeyAimbotEnabled', 'protectionLevel', 'killsToRedSkull', 'killsToBlackSkull',
		'pzLocked', 'removeChargesFromRunes', 'timeToDecreaseFrags', 'whiteSkullTime',
		'stairJumpExhaustion', 'experienceByKillingPlayers', 'expFromPlayersLevelRange',
		'loginProtocolPort', 'maxPlayers', 'motd', 'onePlayerOnlinePerAccount', 'deathLosePercent',
		'housePriceEachSQM', 'houseRentPeriod', 'marketOfferDuration', 'premiumToCreateMarketOffer',
		'maxMarketOffersAtATimePerPlayer', 'allowChangeOutfit', 'freePremium',
		'kickIdlePlayerAfterMinutes', 'rateExp', 'rateSkill', 'rateLoot', 'rateMagic', 'rateSpawn',
		'staminaSystem', 'experienceStages'
	);
}

/**
 * Arithmetic on a config.lua right-hand side, without eval(). Only digits and
 * the four operators are accepted, so nothing from the file can ever run as
 * code - the previous implementation eval()'d whatever it found here.
 */
function serverdata_eval_number(string $expression)
{
	$expression = trim($expression);
	if ($expression === '' || !preg_match('/^[0-9+\-*\/(). ]+$/', $expression)) {
		return null;
	}
	if (preg_match('/^-?\d+$/', $expression)) {
		return (int)$expression;
	}
	if (preg_match('/^-?\d*\.\d+$/', $expression)) {
		return (float)$expression;
	}

	$position = 0;
	$chars    = str_split(str_replace(' ', '', $expression));
	$length   = count($chars);

	$parseExpression = null;

	$parsePrimary = static function () use (&$chars, &$position, $length, &$parseExpression) {
		if ($position < $length && $chars[$position] === '(') {
			$position++;
			$value = $parseExpression();
			if ($position >= $length || $chars[$position] !== ')') {
				throw new RuntimeException('Unbalanced brackets.');
			}
			$position++;
			return $value;
		}

		$sign = 1;
		while ($position < $length && ($chars[$position] === '-' || $chars[$position] === '+')) {
			if ($chars[$position] === '-') {
				$sign = -$sign;
			}
			$position++;
		}

		$number = '';
		while ($position < $length && (ctype_digit($chars[$position]) || $chars[$position] === '.')) {
			$number .= $chars[$position++];
		}
		if ($number === '' || !is_numeric($number)) {
			throw new RuntimeException('Not a number.');
		}

		return $sign * (strpos($number, '.') !== false ? (float)$number : (int)$number);
	};

	$parseTerm = static function () use (&$chars, &$position, $length, $parsePrimary) {
		$value = $parsePrimary();
		while ($position < $length && ($chars[$position] === '*' || $chars[$position] === '/')) {
			$operator = $chars[$position++];
			$right    = $parsePrimary();
			if ($operator === '/') {
				if ((float)$right === 0.0) {
					throw new RuntimeException('Division by zero.');
				}
				$value /= $right;
			} else {
				$value *= $right;
			}
		}
		return $value;
	};

	$parseExpression = static function () use (&$chars, &$position, $length, $parseTerm) {
		$value = $parseTerm();
		while ($position < $length && ($chars[$position] === '+' || $chars[$position] === '-')) {
			$operator = $chars[$position++];
			$right    = $parseTerm();
			$value    = ($operator === '+') ? $value + $right : $value - $right;
		}
		return $value;
	};

	try {
		$value = $parseExpression();
	} catch (Throwable $e) {
		return null;
	}

	return ($position === $length) ? $value : null;
}

function serverdata_parse_config(string $content, ?string &$error = null)
{
	$error = null;

	if (trim($content) === '') {
		$error = 'The config.lua is empty.';
		return false;
	}

	$first = strpos($content, '{');
	if ($first !== false) {
		$last = strripos($content, '}');
		if ($last === false) {
			$error = 'Syntax error in config.lua: an opening { has no matching }.';
			return false;
		}
		$content = substr($content, 0, $first) . substr($content, $last + 1);
	}

	$whitelist = array_fill_keys(serverdata_config_whitelist(), true);
	$values    = array();

	foreach (preg_split('/\R/', $content) ?: array() as $line) {
		if (strpos($line, '=') === false) {
			continue;
		}

		$comment = strpos($line, '--');
		if ($comment !== false) {
			$line = substr($line, 0, $comment);
		}

		$line = trim($line);
		if ($line === '') {
			continue;
		}

		$parts = explode('=', $line, 2);
		if (count($parts) < 2) {
			continue;
		}

		$key = trim($parts[0]);
		$raw = trim($parts[1]);

		if (!isset($whitelist[$key])) {
			continue;
		}

		$lower = strtolower($raw);
		if ($lower === 'true' || $lower === 'false') {
			$values[$key] = ($lower === 'true');
			continue;
		}

		if (strpos($raw, '"') !== false || strpos($raw, "'") !== false) {
			$values[$key] = trim(str_replace(array('"', "'"), '', $raw));
			continue;
		}

		if (array_key_exists($raw, $values)) {
			$values[$key] = $values[$raw];
			continue;
		}

		$number = serverdata_eval_number($raw);
		if ($number !== null) {
			$values[$key] = $number;
		}
	}

	if (!$values) {
		$error = 'No recognised settings were found. Is this really a config.lua?';
		return false;
	}

	return $values;
}

function serverdata_creature_source(): array
{
	$local = serverdata_monster_dir();
	if (is_file($local . '/monsters.xml')) {
		return array(
			'index' => $local . '/monsters.xml',
			'dir'   => $local,
			'label' => ZNOTE_SERVERDATA_MONSTERS
		);
	}

	$path = rtrim((string)($GLOBALS['config']['server_path'] ?? ''), '/\\');
	if ($path === '') {
		$path = 'misc';
	}

	return array(
		'index' => $path . '/data/monster/monsters.xml',
		'dir'   => $path . '/data/monster',
		'label' => $path . '/data/monster'
	);
}

function serverdata_parse_monsters(string $indexPath, string $dir, ?string &$error = null)
{
	$error = null;

	$index = @simplexml_load_file($indexPath);
	if ($index === false) {
		$error = 'Could not read ' . basename($indexPath) . '.';
		return false;
	}

	$creatures = array();
	$missing   = 0;

	foreach ($index->monster as $entry) {
		$file = (string)$entry['file'];
		if ($file === '' || strpos($file, '..') !== false) {
			continue;
		}

		$monster = @simplexml_load_file($dir . '/' . $file);
		if ($monster === false) {
			$missing++;
			$name = trim((string)$entry['name']);
			if ($name !== '') {
				$creatures[] = array(
					'name'       => $name,
					'health'     => 0,
					'experience' => 0,
					'speed'      => 0,
					'race'       => '',
					'looktype'   => 0
				);
			}
			continue;
		}

		$creatures[] = array(
			'name'       => (string)($entry['name'] ?? $monster['name']),
			'health'     => isset($monster->health) ? (int)$monster->health['max'] : 0,
			'experience' => (int)$monster['experience'],
			'speed'      => (int)$monster['speed'],
			'race'       => (string)$monster['race'],
			'looktype'   => isset($monster->look) ? (int)$monster->look['type'] : 0
		);
	}

	if (!$creatures) {
		$error = 'The index lists no monsters.';
		return false;
	}

	usort($creatures, static function (array $a, array $b): int {
		return strcasecmp($a['name'], $b['name']);
	});

	if ($missing) {
		$error = $missing . ' of ' . count($creatures) . ' monster files were missing, so those rows have no stats. '
			. 'Upload a .zip of data/monster/ to fill them in.';
	}

	return $creatures;
}

function serverdata_rebuild(string $key, ?string &$error = null): bool
{
	$error   = null;
	$sources = serverdata_sources();

	if (!isset($sources[$key])) {
		$error = 'Unknown data source.';
		return false;
	}

	if ($key === 'creatures') {
		$source = serverdata_creature_source();
		if (!is_file($source['index'])) {
			$error = 'No monsters.xml at ' . $source['label'] . '.';
			return false;
		}

		$warning   = null;
		$creatures = serverdata_parse_monsters($source['index'], $source['dir'], $warning);
		if ($creatures === false) {
			$error = $warning;
			return false;
		}

		if (!serverdata_store($key, $creatures)) {
			$error = 'Could not write ' . $sources[$key]['cache'] . Cache::EXT . '.';
			return false;
		}

		$error = $warning;
		return true;
	}

	if (($sources[$key]['store'] ?? true) === false) {
		$error = 'The ' . $sources[$key]['file'] . ' is not kept on disk, so there is nothing to re-read. Upload it again.';
		return false;
	}

	$path = serverdata_file($sources[$key]['file']);
	if (!is_file($path)) {
		$error = 'No ' . ZNOTE_SERVERDATA_DIR . '/' . $sources[$key]['file'] . ' to read.';
		return false;
	}

	$parser = 'serverdata_parse_' . $key;
	$parsed = $parser($path);
	if ($parsed === false) {
		$error = ZNOTE_SERVERDATA_DIR . '/' . $sources[$key]['file'] . ' is not valid XML.';
	}

	if ($parsed === false) {
		return false;
	}

	if (!serverdata_store($key, $parsed)) {
		$error = 'Could not write ' . $sources[$key]['cache'] . Cache::EXT . '.';
		return false;
	}

	return true;
}

function serverdata_count(string $key, $data): int
{
	if (!is_array($data)) {
		return 0;
	}

	if ($key === 'creatures' || $key === 'config') {
		return count($data);
	}
	if ($key === 'stages') {
		return count($data['stages'] ?? array());
	}

	$total = 0;
	foreach ($data as $group) {
		$total += is_array($group) ? count($group) : 1;
	}

	return $total;
}

function serverdata_status(): array
{
	$status = array();

	foreach (serverdata_sources() as $key => $source) {
		$keeps = ($source['store'] ?? true) !== false;

		if ($key === 'creatures') {
			$origin = serverdata_creature_source();
			$path   = $origin['index'];
			$label  = $origin['label'] . '/monsters.xml';
			$extra  = count(glob(serverdata_monster_dir() . '/{,*/,*/*/}*.xml', GLOB_BRACE) ?: array());
		} else {
			$path  = serverdata_file($source['file']);
			$label = ZNOTE_SERVERDATA_DIR . '/' . $source['file'];
			$extra = 0;
		}

		$data  = serverdata_load($key);
		$cache = serverdata_root() . '/' . $source['cache'] . Cache::EXT;
		$onDisk = $keeps && is_file($path);

		$status[$key] = $source + array(
			'key'         => $key,
			'path'        => $path,
			'path_label'  => $keeps ? $label : 'parsed on upload, not stored',
			'keeps_file'  => $keeps,
			'uploaded'    => $keeps ? $onDisk : ($data !== false),
			'upload_date' => $onDisk ? (int)filemtime($path) : 0,
			'upload_size' => $onDisk ? (int)filesize($path) : 0,
			'cached'      => ($data !== false),
			'cache_date'  => is_file($cache) ? (int)filemtime($cache) : 0,
			'count'       => serverdata_count($key, $data),
			'files'       => $extra
		);
	}

	return $status;
}

function serverdata_store_upload(string $key, string $tmpFile, string $originalName, ?string &$error = null): bool
{
	$error   = null;
	$sources = serverdata_sources();

	if (!isset($sources[$key])) {
		$error = 'Unknown data source.';
		return false;
	}

	$extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
	$allowed   = array_map(static function (string $e): string {
		return ltrim(trim($e), '.');
	}, explode(',', $sources[$key]['accept']));

	if (!in_array($extension, $allowed, true)) {
		$error = 'Expected a ' . implode(' or ', array_map(static function (string $e): string {
			return '.' . $e;
		}, $allowed)) . ' file.';
		return false;
	}

	$size = (int)filesize($tmpFile);
	if ($size < 1) {
		$error = 'The uploaded file is empty.';
		return false;
	}

	$limit = ($extension === 'lua') ? ZNOTE_SERVERDATA_MAX_LUA
		: (($extension === 'zip') ? ZNOTE_SERVERDATA_MAX_ZIP : ZNOTE_SERVERDATA_MAX_XML);

	if ($size > $limit) {
		$error = 'That file is larger than the ' . (int)($limit / 1048576) . ' MB limit for .' . $extension . ' uploads.';
		return false;
	}

	if (($sources[$key]['store'] ?? true) === false) {
		$parsed = serverdata_parse_config((string)file_get_contents($tmpFile), $error);
		if ($parsed === false) {
			return false;
		}
		if (!serverdata_store($key, $parsed)) {
			$error = 'Could not write ' . $sources[$key]['cache'] . Cache::EXT . '.';
			return false;
		}
		return true;
	}

	$dir = ($key === 'creatures') ? serverdata_monster_dir() : serverdata_dir();
	if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
		$error = 'Could not create ' . $dir . '.';
		return false;
	}
	if (!is_writable($dir)) {
		$error = str_replace(serverdata_root() . '/', '', $dir) . '/ is not writable by the web server.';
		return false;
	}

	if ($extension === 'zip') {
		return serverdata_extract_monsters($tmpFile, $error);
	}

	$target = ($key === 'creatures')
		? $dir . '/monsters.xml'
		: serverdata_file($sources[$key]['file']);

	if ($extension === 'xml' && @simplexml_load_file($tmpFile) === false) {
		$error = 'That file is not valid XML.';
		return false;
	}

	if (!@copy($tmpFile, $target)) {
		$error = 'Could not write ' . str_replace(serverdata_root() . '/', '', $target) . '.';
		return false;
	}

	return true;
}

/**
 * Take an upload and make it live in one step. A source that keeps its file
 * still has to be parsed afterwards; one that does not - config.lua - was
 * already published by the upload itself, so re-reading it would fail.
 *
 * $error is a warning, not a failure, when this returns true.
 */
function serverdata_publish_upload(string $key, string $tmpFile, string $originalName, ?string &$error = null): bool
{
	if (!serverdata_store_upload($key, $tmpFile, $originalName, $error)) {
		return false;
	}

	$sources = serverdata_sources();
	if (($sources[$key]['store'] ?? true) === false) {
		$error = null;
		return true;
	}

	return serverdata_rebuild($key, $error);
}

/**
 * Unpack a data/monster/ archive. Only .xml entries are taken, paths are
 * rebased on wherever monsters.xml sits inside the zip, and anything trying to
 * escape the target folder is dropped.
 */
function serverdata_extract_monsters(string $zipFile, ?string &$error = null): bool
{
	$error = null;

	if (!class_exists('ZipArchive')) {
		$error = 'PHP needs the zip extension to unpack a monster archive.';
		return false;
	}

	$zip = new ZipArchive();
	if ($zip->open($zipFile) !== true) {
		$error = 'That .zip could not be opened.';
		return false;
	}

	$index = null;
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
		if (strtolower(basename($name)) === 'monsters.xml') {
			if ($index === null || substr_count($name, '/') < substr_count($index, '/')) {
				$index = $name;
			}
		}
	}

	if ($index === null) {
		$zip->close();
		$error = 'That .zip has no monsters.xml. Zip the contents of your data/monster/ folder.';
		return false;
	}

	$prefix = (strpos($index, '/') === false) ? '' : substr($index, 0, strrpos($index, '/') + 1);
	$dir    = serverdata_monster_dir();

	$total   = 0;
	$written = 0;

	for ($i = 0; $i < $zip->numFiles; $i++) {
		$stat = $zip->statIndex($i);
		if (!$stat) {
			continue;
		}

		$name = str_replace('\\', '/', (string)$stat['name']);
		if (substr($name, -1) === '/' || strtolower((string)pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') {
			continue;
		}
		if ($prefix !== '' && strpos($name, $prefix) !== 0) {
			continue;
		}

		$relative = ($prefix === '') ? $name : substr($name, strlen($prefix));
		if ($relative === '' || strpos($relative, '..') !== false || $relative[0] === '/') {
			continue;
		}

		$total += (int)$stat['size'];
		if ($total > ZNOTE_SERVERDATA_MAX_UNZIP) {
			$zip->close();
			$error = 'That archive unpacks to more than ' . (int)(ZNOTE_SERVERDATA_MAX_UNZIP / 1048576) . ' MB.';
			return false;
		}
		if (++$written > ZNOTE_SERVERDATA_MAX_FILES) {
			$zip->close();
			$error = 'That archive holds more than ' . ZNOTE_SERVERDATA_MAX_FILES . ' XML files.';
			return false;
		}

		$target = $dir . '/' . $relative;
		$parent = dirname($target);

		if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
			continue;
		}

		$contents = $zip->getFromIndex($i);
		if ($contents === false) {
			continue;
		}

		@file_put_contents($target, $contents);
	}

	$zip->close();

	if (!$written) {
		$error = 'No XML files were extracted from that archive.';
		return false;
	}

	return true;
}

function serverdata_clear(string $key): void
{
	$sources = serverdata_sources();
	if (!isset($sources[$key])) {
		return;
	}

	serverdata_forget($key);

	if ($key === 'creatures') {
		serverdata_rmdir(serverdata_monster_dir());
		return;
	}

	@unlink(serverdata_file($sources[$key]['file']));
}

function serverdata_rmdir(string $dir): void
{
	if (!is_dir($dir)) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($items as $item) {
		$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
	}

	@rmdir($dir);
}
