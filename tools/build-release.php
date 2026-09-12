<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit(1);
}

$root = dirname(__DIR__);
$metadataFile = __DIR__ . '/release-metadata.json';
$metadata = json_decode((string)file_get_contents($metadataFile), true);
$version = (string)($argv[1] ?? ($metadata['version'] ?? ''));
$outputRoot = rtrim((string)($argv[2] ?? ($root . '/release')), '/\\');
$current = (string)require $root . '/engine/version.php';

if (!is_array($metadata) || $version === '' || $version !== (string)($metadata['version'] ?? '') || $version !== $current) {
	fwrite(STDERR, "The version must match tools/release-metadata.json and engine/version.php.\n");
	exit(1);
}
if (!class_exists('ZipArchive') || !function_exists('openssl_sign')) {
	fwrite(STDERR, "PHP Zip and OpenSSL are required.\n");
	exit(1);
}

$signingDirectory = $outputRoot . '/.signing';
$privateKeyFile = $signingDirectory . '/private.pem';
$publicKeyFile = $root . '/engine/update-public.pem';
if (!is_dir($signingDirectory) && !mkdir($signingDirectory, 0700, true) && !is_dir($signingDirectory)) {
	fwrite(STDERR, "The signing directory cannot be created.\n");
	exit(1);
}

if (!is_file($privateKeyFile)) {
	fwrite(STDERR, "The private signing key is missing. Run tools/generate-release-key.ps1 once.\n");
	exit(1);
}

$privatePem = (string)file_get_contents($privateKeyFile);
$key = openssl_pkey_get_private($privatePem);
$details = $key !== false ? openssl_pkey_get_details($key) : false;
if ($key === false || !is_array($details) || !is_file($publicKeyFile) || trim((string)file_get_contents($publicKeyFile)) !== trim((string)$details['key'])) {
	fwrite(STDERR, "The private key does not match engine/update-public.pem.\n");
	exit(1);
}

$releaseDirectory = $outputRoot . '/' . $version;
if (!is_dir($releaseDirectory) && !mkdir($releaseDirectory, 0755, true) && !is_dir($releaseDirectory)) {
	fwrite(STDERR, "The release directory cannot be created.\n");
	exit(1);
}
if (is_file(__DIR__ . '/RELEASE.md')) {
	copy(__DIR__ . '/RELEASE.md', $outputRoot . '/README.md');
}

$allowedDirectories = array('admin', 'api', 'assets', 'engine', 'locale', 'SQL', 'vendor');
$excludedPrefixes = array('engine/cache/', 'engine/img/theme/', 'engine/update/');
$files = array();

$accept = static function (string $relative) use ($excludedPrefixes): bool {
	$relative = str_replace('\\', '/', $relative);
	foreach ($excludedPrefixes as $prefix) {
		if (str_starts_with($relative, $prefix)) {
			return false;
		}
	}
	return !str_ends_with($relative, '.znote-update');
};

foreach ($allowedDirectories as $directory) {
	$base = $root . '/' . $directory;
	if (!is_dir($base)) {
		continue;
	}
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $item) {
		if (!$item->isFile() || $item->isLink()) {
			continue;
		}
		$relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
		if ($accept($relative)) {
			$files[$relative] = $item->getPathname();
		}
	}
}

foreach (glob($root . '/*.php') ?: array() as $file) {
	$name = basename($file);
	if (!in_array(strtolower($name), array('config.php', 'config.local.php'), true)) {
		$files[$name] = $file;
	}
}
foreach (array('.htaccess', 'composer.json', 'composer.lock', 'LICENSE', 'README.md', 'config.countries.php') as $name) {
	if (is_file($root . '/' . $name)) {
		$files[$name] = $root . '/' . $name;
	}
}
ksort($files);

$packageName = 'znotex-core-' . $version . '.zip';
$packageFile = $releaseDirectory . '/' . $packageName;
$zip = new ZipArchive();
if ($zip->open($packageFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
	fwrite(STDERR, "The release ZIP cannot be created.\n");
	exit(1);
}
$hashes = array();
foreach ($files as $relative => $file) {
	if (!$zip->addFile($file, $relative)) {
		$zip->close();
		fwrite(STDERR, "A file cannot be added: " . $relative . "\n");
		exit(1);
	}
	$hashes[$relative] = hash_file('sha256', $file);
}
$zip->close();

$manifest = $metadata;
$manifest['schema'] = 1;
$manifest['project'] = 'ZnoteX';
$manifest['version'] = $version;
$manifest['published_at'] = gmdate(DATE_ATOM);
$manifest['package'] = array(
	'name' => $packageName,
	'sha256' => hash_file('sha256', $packageFile),
	'size' => filesize($packageFile),
);
$manifest['installation'] = array(
	'mode' => 'signed-core-update',
	'backup' => true,
	'rollback' => 'files',
	'database_policy' => 'expand-only'
);
$manifest['protected_paths'] = array('config.php', 'config.local.php', 'plugins/', 'layouts/', 'engine/cache/', 'engine/img/theme/', 'engine/update/', 'install/', 'release/');
$manifest['links'] = array(
	'repository' => 'https://github.com/Open-Games-Community/ZnoteX',
	'release' => 'https://github.com/Open-Games-Community/ZnoteX/releases/tag/v' . $version
);
$manifest['files'] = $hashes;

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (!openssl_sign($json, $signature, $key, OPENSSL_ALGO_SHA256)) {
	fwrite(STDERR, "The release manifest cannot be signed.\n");
	exit(1);
}
file_put_contents($releaseDirectory . '/update.json', $json, LOCK_EX);
file_put_contents($releaseDirectory . '/update.json.sig', base64_encode($signature) . "\n", LOCK_EX);

echo $releaseDirectory . PHP_EOL;
echo $packageName . PHP_EOL;
echo count($hashes) . " files\n";
