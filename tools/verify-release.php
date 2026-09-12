<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$root = dirname(__DIR__);
$directory = rtrim((string)($argv[1] ?? ''), '/\\');
$jsonFile = $directory . '/update.json';
$signatureFile = $directory . '/update.json.sig';
if (!is_file($jsonFile) || !is_file($signatureFile) || !is_file($root . '/engine/update-public.pem')) {
	fwrite(STDERR, "Release files are missing.\n");
	exit(1);
}

$json = (string)file_get_contents($jsonFile);
$signature = base64_decode(trim((string)file_get_contents($signatureFile)), true);
$manifest = json_decode($json, true);
if ($signature === false || !is_array($manifest) || openssl_verify($json, $signature, (string)file_get_contents($root . '/engine/update-public.pem'), OPENSSL_ALGO_SHA256) !== 1) {
	fwrite(STDERR, "The release signature is invalid.\n");
	exit(1);
}

$package = $directory . '/' . (string)$manifest['package']['name'];
if (!is_file($package) || hash_file('sha256', $package) !== (string)$manifest['package']['sha256'] || filesize($package) !== (int)$manifest['package']['size']) {
	fwrite(STDERR, "The package checksum or size is invalid.\n");
	exit(1);
}

$zip = new ZipArchive();
if ($zip->open($package) !== true) {
	fwrite(STDERR, "The package cannot be opened.\n");
	exit(1);
}
$seen = array();
for ($index = 0; $index < $zip->numFiles; $index++) {
	$path = (string)$zip->getNameIndex($index);
	if (str_ends_with($path, '/')) {
		continue;
	}
	$data = $zip->getFromIndex($index);
	if (!is_string($data) || !isset($manifest['files'][$path]) || hash('sha256', $data) !== (string)$manifest['files'][$path]) {
		$zip->close();
		fwrite(STDERR, "A packaged file is invalid: " . $path . "\n");
		exit(1);
	}
	$seen[$path] = true;
}
$zip->close();
if (count($seen) !== count($manifest['files'])) {
	fwrite(STDERR, "The signed file list does not match the package.\n");
	exit(1);
}

echo "Valid release " . $manifest['version'] . "\n";
echo count($seen) . " signed files\n";
