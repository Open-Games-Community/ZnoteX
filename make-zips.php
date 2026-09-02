<?php
/**
 * Package the installed themes into the zip files the catalogue points at,
 * and regenerate index.json from each theme's own theme.json.
 *
 * Run it from the ZnoteX root:
 *
 *   php upload_github/make-zips.php
 *
 * It writes into upload_github/, which is what you push to the layouts branch.
 * Nothing outside that folder is touched.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Edit these to match your repository, then never again.
//
// GITHUB_OWNER is whoever owns the repo - a personal account or an
// organisation. GitHub makes no distinction in the URL: it is simply the name
// that appears after github.com/, which you can read off your remote:
//
//   git remote -v   ->   https://github.com/<owner>/<repo>.git
// ---------------------------------------------------------------------------
const GITHUB_OWNER = 'Open-Games-Community';
const GITHUB_REPO = 'ZnoteX';
const GITHUB_BRANCH = 'layouts';

// Themes that stay local: these ship with ZnoteX itself and are documentation,
// not something anyone downloads. Any other folder in layouts/ gets packaged.
const SKIP = array('default', '_example', '_childexample');

// ---------------------------------------------------------------------------

// Where ZnoteX lives, and where the archives are written.
//
// This script sits on the layouts branch, next to the archives it produces,
// which is a different folder from the ZnoteX code it packages. So the code
// root is passed in, and defaults to the sibling folder:
//
//   cd f:/znotex-layouts
//   php make-zips.php ../ZnoteX
//
$root = isset($argv[1]) ? rtrim(strtr($argv[1], '\\', '/'), '/') : dirname(__DIR__);
$out  = __DIR__;

if (!is_dir($root . '/layouts')) {
	fwrite(STDERR, "No layouts/ folder in {$root}." . PHP_EOL
		. "Pass the path to your ZnoteX install:  php make-zips.php ../ZnoteX" . PHP_EOL);
	exit(1);
}

$rawUrl = 'https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/' . GITHUB_BRANCH;
$webUrl = 'https://github.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/tree/' . GITHUB_BRANCH;

if (!class_exists('ZipArchive')) {
	fwrite(STDERR, "The zip extension is not enabled. Add extension=zip to php.ini.\n");
	exit(1);
}

/** Every file under $dir, relative to it. */
function collect(string $dir): array {
	$files = array();
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($it as $path => $info) {
		if ($info->isFile()) {
			$files[] = str_replace('\\', '/', substr($path, strlen($dir) + 1));
		}
	}
	sort($files);
	return $files;
}

$catalogue = array();
$themesDir = $root . '/layouts';

foreach (glob($themesDir . '/*', GLOB_ONLYDIR) ?: array() as $dir) {
	$key = basename($dir);

	if (in_array($key, SKIP, true) || $key[0] === '.') {
		continue;
	}
	if (!is_file($dir . '/shells/default.php')) {
		printf("  skipped %-20s no shells/default.php\n", $key);
		continue;
	}

	// ---- the archive ----
	$zipPath = $out . '/' . $key . '.zip';
	@unlink($zipPath);

	$zip = new ZipArchive();
	if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
		printf("  FAILED  %-20s cannot create %s\n", $key, $zipPath);
		continue;
	}

	$files = collect($dir);
	foreach ($files as $rel) {
		// No wrapping folder: the installer accepts both, this is just tidier.
		$zip->addFile($dir . '/' . $rel, $rel);
	}
	$zip->close();

	// ---- the catalogue entry ----
	$manifest = array();
	if (is_file($dir . '/theme.json')) {
		$decoded = json_decode((string)file_get_contents($dir . '/theme.json'), true);
		if (is_array($decoded)) {
			$manifest = $decoded;
		}
	}

	$entry = array(
		'key'         => $key,
		'name'        => (string)($manifest['name'] ?? ucfirst($key)),
		'version'     => (string)($manifest['version'] ?? '1.0.0'),
		'author'      => (string)($manifest['author'] ?? ''),
		'description' => (string)($manifest['description'] ?? ''),
		'download'    => $rawUrl . '/' . $key . '.zip',
		'url'         => $webUrl . '/' . $key,
	);

	// Ship the screenshot next to the archive so the catalogue can show it.
	if (is_file($dir . '/screenshot.png')) {
		copy($dir . '/screenshot.png', $out . '/' . $key . '.png');
		$entry['screenshot'] = $rawUrl . '/' . $key . '.png';
	}

	$catalogue[] = $entry;

	printf("  packaged %-20s %5d files  %6.1f KB\n", $key, count($files), filesize($zipPath) / 1024);
}

file_put_contents(
	$out . '/index.json',
	json_encode($catalogue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "\n  index.json written with " . count($catalogue) . " theme(s)\n";
echo "  catalogue URL for config.php:\n";
echo "    " . $rawUrl . "/index.json\n";
