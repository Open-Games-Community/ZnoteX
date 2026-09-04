<?php

const ZNOTE_LANDING_DIR = 'landing';

function landing_root(): string
{
	return dirname(__DIR__, 2) . '/' . ZNOTE_LANDING_DIR;
}

function landing_enabled(): bool
{
	return function_exists('setting') && (string)setting('landing.enabled', '') === '1';
}

/** The file inside landing/ that is served. */
function landing_file(): string
{
	$file = function_exists('setting') ? (string)setting('landing.file', '') : '';
	$file = trim($file);

	return ($file !== '' && landing_is_valid_file($file)) ? $file : 'index.html';
}

/** One path segment, .html or .php, never escaping landing/. */
function landing_is_valid_file(string $file): bool
{
	if (strpos($file, '/') !== false || strpos($file, '\\') !== false || strpos($file, '..') !== false) {
		return false;
	}

	return (bool)preg_match('/^[A-Za-z0-9._-]{1,64}\.(html?|php)$/', $file);
}

/** Every page landing/ offers, for the admin picker. */
function landing_available_files(): array
{
	$files = array();

	foreach (glob(landing_root() . '/*.{html,htm,php}', GLOB_BRACE) ?: array() as $path) {
		$name = basename($path);
		if (landing_is_valid_file($name)) {
			$files[] = $name;
		}
	}

	sort($files);

	return $files;
}

function landing_path(): string
{
	return landing_root() . '/' . landing_file();
}

function landing_ready(): bool
{
	return landing_enabled() && is_file(landing_path());
}

/**
 * The landing folder's URL, worked out from the running script so this keeps
 * working when ZnoteX lives in a subdirectory.
 */
function landing_base_url(): string
{
	$dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
	$dir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');

	return $dir . '/' . ZNOTE_LANDING_DIR . '/';
}

/**
 * A visitor who asked for the real site, remembered for the rest of the visit
 * so every link back to the front page does not drop them on the landing page
 * again.
 */
function landing_bypassed(): bool
{
	if (isset($_GET['site'])) {
		$_SESSION['landing_bypass'] = true;
		return true;
	}

	return !empty($_SESSION['landing_bypass']);
}

/**
 * Serve the landing page in place of the front page, and stop. Does nothing
 * unless the feature is on, the file is there, and the visitor has not asked
 * for the real site with ?site=1.
 */
function landing_serve(): void
{
	if (!landing_ready() || landing_bypassed()) {
		return;
	}

	$path = landing_path();

	if (strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
		ob_start();
		include $path;
		$html = (string)ob_get_clean();
	} else {
		$html = (string)file_get_contents($path);
	}

	echo landing_apply_base($html);
	exit;
}

/**
 * The page is served from the site root, so its own relative css/, img/ and js/
 * would resolve one folder too high. A <base> fixes every one of them at once.
 * Links that must leave the landing page use a leading slash and are unaffected.
 */
function landing_apply_base(string $html): string
{
	if (stripos($html, '<base ') !== false) {
		return $html;
	}

	$base = '<base href="' . htmlspecialchars(landing_base_url(), ENT_QUOTES, 'UTF-8') . '">';

	if (preg_match('~<head\b[^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE)) {
		$at = $m[0][1] + strlen($m[0][0]);
		return substr($html, 0, $at) . "\n\t" . $base . substr($html, $at);
	}

	return $base . $html;
}
