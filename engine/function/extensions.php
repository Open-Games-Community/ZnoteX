<?php

const ZNOTE_EXTENSION_API_VERSION = '1.0.0';

function znote_extension_version_matches(string $version, string $constraint): bool
{
	$version = ltrim(trim($version), 'vV');
	$constraint = trim($constraint);

	if ($version === '' || $constraint === '' || $constraint === '*') {
		return true;
	}

	foreach (preg_split('/\s*\|\|\s*/', $constraint) ?: array() as $alternative) {
		$alternative = preg_replace('/(>=|<=|>|<|==|=|!=)\s+/', '$1', trim($alternative)) ?? '';
		$parts = preg_split('/[\s,]+/', $alternative, -1, PREG_SPLIT_NO_EMPTY) ?: array();
		$matches = $parts !== array();

		foreach ($parts as $part) {
			if (!znote_extension_version_part_matches($version, $part)) {
				$matches = false;
				break;
			}
		}

		if ($matches) {
			return true;
		}
	}

	return false;
}

function znote_extension_version_part_matches(string $version, string $constraint): bool
{
	if ($constraint === '' || $constraint === '*') {
		return true;
	}

	if ($constraint[0] === '^') {
		return znote_extension_version_caret_matches($version, $constraint);
	}

	if ($constraint[0] === '~') {
		return znote_extension_version_tilde_matches($version, $constraint);
	}

	if (str_contains($constraint, '*') || str_contains(strtolower($constraint), 'x')) {
		return znote_extension_version_wildcard_matches($version, $constraint);
	}

	return znote_extension_version_operator_matches($version, $constraint);
}

function znote_extension_version_caret_matches(string $version, string $constraint): bool
{
	$minimum = substr($constraint, 1);
	$segments = array_map('intval', explode('.', $minimum));
	$major = $segments[0] ?? 0;
	$minor = $segments[1] ?? 0;
	$patch = $segments[2] ?? 0;
	$maximum = $major > 0
		? ($major + 1) . '.0.0'
		: ($minor > 0 ? '0.' . ($minor + 1) . '.0' : '0.0.' . ($patch + 1));

	return version_compare($version, $minimum, '>=') && version_compare($version, $maximum, '<');
}

function znote_extension_version_tilde_matches(string $version, string $constraint): bool
{
	$minimum = substr($constraint, 1);
	$rawSegments = explode('.', $minimum);
	$segments = array_map('intval', $rawSegments);
	$major = $segments[0] ?? 0;
	$minor = $segments[1] ?? 0;
	$maximum = count($rawSegments) >= 3
		? $major . '.' . ($minor + 1) . '.0'
		: ($major + 1) . '.0.0';

	return version_compare($version, $minimum, '>=') && version_compare($version, $maximum, '<');
}

function znote_extension_version_wildcard_matches(string $version, string $constraint): bool
{
	$segments = explode('.', str_replace(array('X', 'x'), '*', $constraint));
	$fixed = array();

	foreach ($segments as $segment) {
		if ($segment === '*') {
			break;
		}
		$fixed[] = max(0, (int)$segment);
	}

	if ($fixed === array()) {
		return true;
	}

	$minimumParts = array_pad($fixed, 3, 0);
	$maximumParts = $minimumParts;
	$index = count($fixed) - 1;
	$maximumParts[$index]++;
	for ($i = $index + 1; $i < 3; $i++) {
		$maximumParts[$i] = 0;
	}

	$minimum = implode('.', $minimumParts);
	$maximum = implode('.', $maximumParts);
	return version_compare($version, $minimum, '>=') && version_compare($version, $maximum, '<');
}

function znote_extension_version_operator_matches(string $version, string $constraint): bool
{
	if (!preg_match('/^(>=|<=|>|<|==|=|!=)?(.+)$/', $constraint, $match)) {
		return false;
	}

	$operator = $match[1] !== '' ? $match[1] : '=';
	return version_compare($version, ltrim(trim($match[2]), 'vV'), $operator);
}

function znote_extension_requirements(array $manifest): array
{
	$requires = $manifest['requires'] ?? array();

	if (is_string($requires)) {
		$requires = trim($requires);
		return $requires === '' ? array() : array('znotex' => '>=' . ltrim($requires, 'vV'));
	}

	if (!is_array($requires)) {
		return array();
	}

	if (isset($requires['znote']) && !isset($requires['znotex'])) {
		$requires['znotex'] = $requires['znote'];
	}

	return $requires;
}

function znote_extension_compatibility(array $manifest): array
{
	$requires = znote_extension_requirements($manifest);
	$versions = array(
		'znotex' => (string)($GLOBALS['version'] ?? '0.0.0'),
		'php' => PHP_VERSION,
		'api' => ZNOTE_EXTENSION_API_VERSION,
	);
	$errors = array();

	foreach ($versions as $component => $current) {
		$declared = $requires[$component] ?? '';
		$constraint = is_string($declared) || is_numeric($declared) ? trim((string)$declared) : '';
		if ($declared !== '' && $constraint === '') {
			$errors[] = 'Invalid ' . $component . ' version requirement.';
			continue;
		}
		if ($constraint !== '' && !znote_extension_version_matches($current, $constraint)) {
			$errors[] = 'Requires ' . ($component === 'znotex' ? 'ZnoteX' : strtoupper($component))
				. ' ' . $constraint . '; running ' . $current . '.';
		}
	}

	$extensions = $requires['extensions'] ?? array();
	if (is_string($extensions)) {
		$extensions = preg_split('/[\s,]+/', $extensions, -1, PREG_SPLIT_NO_EMPTY) ?: array();
	}
	if (is_array($extensions)) {
		foreach ($extensions as $extension) {
			$extension = strtolower(trim((string)$extension));
			if ($extension !== '' && !extension_loaded($extension)) {
				$errors[] = 'Requires PHP extension ' . $extension . '.';
			}
		}
	}

	return array(
		'compatible' => $errors === array(),
		'errors' => $errors,
		'requires' => $requires,
		'versions' => $versions,
	);
}

function znote_extension_relative_path(string $path): string
{
	$path = trim(str_replace('\\', '/', trim($path)), '/');
	$segments = array_values(array_filter(explode('/', $path), static fn(string $part): bool => $part !== ''));

	if ($path === ''
		|| preg_match('/[\x00-\x1F\x7F?#%<>"\x3A;]/u', $path)
		|| in_array('..', $segments, true)
		|| in_array('.', $segments, true)
	) {
		return '';
	}

	return implode('/', $segments);
}

function znote_extension_url_path(string $path): string
{
	$path = znote_extension_relative_path($path);
	return $path === '' ? '' : implode('/', array_map('rawurlencode', explode('/', $path)));
}

final class ZnoteExtensionApi
{
	private string $kind;
	private string $name;

	private function __construct(string $kind, string $name)
	{
		$this->kind = $kind;
		$this->name = $name;
	}

	public static function plugin(string $name): self
	{
		$name = function_exists('znote_plugin_sanitize') ? znote_plugin_sanitize($name) : '';
		if ($name === '') {
			throw new InvalidArgumentException('Invalid plugin name.');
		}

		return new self('plugin', $name);
	}

	public static function theme(string $name): self
	{
		$name = function_exists('theme_sanitize') ? theme_sanitize($name) : '';
		if ($name === '') {
			throw new InvalidArgumentException('Invalid theme name.');
		}

		return new self('theme', $name);
	}

	public function apiVersion(): string
	{
		return ZNOTE_EXTENSION_API_VERSION;
	}

	public function znoteVersion(): string
	{
		return (string)($GLOBALS['version'] ?? '0.0.0');
	}

	public function kind(): string
	{
		return $this->kind;
	}

	public function name(): string
	{
		return $this->name;
	}

	public function config(string $path = '', mixed $default = null): mixed
	{
		$value = $GLOBALS['config'] ?? array();
		if ($path === '') {
			return $value;
		}

		foreach (explode('.', $path) as $segment) {
			if (!is_array($value) || !array_key_exists($segment, $value)) {
				return $default;
			}
			$value = $value[$segment];
		}

		return $value;
	}

	public function setting(string $key, ?string $default = null): ?string
	{
		$key = $this->settingKey($key);
		return function_exists('setting') ? setting($key, $default) : $default;
	}

	public function setSetting(string $key, string $value): bool
	{
		return function_exists('setting_set') && setting_set($this->settingKey($key), $value);
	}

	public function database(): mixed
	{
		return function_exists('db') ? db() : null;
	}

	public function cache(string $key, ?int $lifespan = null, ?bool $memory = null): Cache
	{
		$key = strtolower(trim($key));
		if (!preg_match('/^[a-z0-9_.-]{1,100}$/', $key)) {
			throw new InvalidArgumentException('Invalid cache key.');
		}

		$cache = new Cache('engine/cache/extensions/' . $this->kind . '/' . $this->name . '/' . $key);
		if ($lifespan !== null) {
			$cache->setExpiration($lifespan);
		}
		if ($memory !== null) {
			$cache->useMemory($memory);
		}

		return $cache;
	}

	public function on(string $hook, callable $callback, int $priority = 10): void
	{
		znote_hook_register($hook, $callback, $priority);
	}

	public function dispatch(string $hook, array $data = array()): void
	{
		znote_hook($hook, $data);
	}

	public function collect(string $hook, array $data = array()): string
	{
		return znote_hook_collect($hook, $data);
	}

	public function filter(string $hook, mixed $value, array $data = array()): mixed
	{
		return znote_hook_filter($hook, $value, $data);
	}

	public function allows(string $hook, array $data = array()): bool
	{
		return znote_hook_allows($hook, $data);
	}

	public function url(string $page = ''): string
	{
		return $this->kind === 'plugin'
			? znote_plugin_url($this->name, $page)
			: theme_url($this->name);
	}

	public function asset(string $file): string
	{
		$file = znote_extension_relative_path($file);
		if ($file === '') {
			return '';
		}

		return $this->kind === 'plugin'
			? znote_plugin_asset($this->name, $file)
			: 'layouts/' . $this->name . '/assets/' . znote_extension_url_path($file);
	}

	public function themeOption(string $key, string $default = ''): string
	{
		return function_exists('theme_option') ? theme_option($key, $default, $this->kind === 'theme' ? $this->name : null) : $default;
	}

	private function settingKey(string $key): string
	{
		$key = strtolower(trim($key));
		if (!preg_match('/^[a-z0-9_.-]{1,100}$/', $key)) {
			throw new InvalidArgumentException('Invalid setting key.');
		}

		return $this->kind . ':' . $this->name . ':setting:' . $key;
	}
}

function znote_plugin_api(string $plugin): ZnoteExtensionApi
{
	return ZnoteExtensionApi::plugin($plugin);
}

function znote_theme_api(?string $theme = null): ZnoteExtensionApi
{
	return ZnoteExtensionApi::theme($theme ?? theme_active());
}
