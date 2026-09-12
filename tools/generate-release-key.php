<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$outputRoot = rtrim((string)($argv[1] ?? ''), '/\\');
$publicKeyFile = (string)($argv[2] ?? '');
$privateKeyFile = $outputRoot . '/.signing/private.pem';
$options = array(
	'config' => __DIR__ . '/openssl.cnf',
	'private_key_bits' => 3072,
	'private_key_type' => OPENSSL_KEYTYPE_RSA,
);

if ($outputRoot === '' || $publicKeyFile === '' || (is_file($privateKeyFile) && filesize($privateKeyFile) > 0)) {
	fwrite(STDERR, "Invalid key destination or an existing private key.\n");
	exit(1);
}

$key = openssl_pkey_new($options);
if ($key === false || !openssl_pkey_export($key, $privatePem, null, $options)) {
	fwrite(STDERR, "OpenSSL could not generate the private key.\n");
	exit(1);
}
$details = openssl_pkey_get_details($key);
if (!is_array($details) || file_put_contents($privateKeyFile, $privatePem, LOCK_EX) === false || file_put_contents($publicKeyFile, $details['key'], LOCK_EX) === false) {
	fwrite(STDERR, "The signing keys could not be saved.\n");
	exit(1);
}

echo $privateKeyFile . PHP_EOL;
echo $publicKeyFile . PHP_EOL;
