<?php

const ZNOTE_UPDATE_SCHEMA = 1;
const ZNOTE_UPDATE_REPOSITORY = 'Open-Games-Community/ZnoteX';
const ZNOTE_UPDATE_MAX_BYTES = 268435456;

function znote_update_root(): string
{
	return dirname(__DIR__, 2);
}

function znote_update_storage(): string
{
	return dirname(__DIR__) . '/update';
}

function znote_update_current_version(): string
{
	return (string)require dirname(__DIR__) . '/version.php';
}

function znote_update_api_url(): string
{
	return 'https://api.github.com/repos/' . ZNOTE_UPDATE_REPOSITORY . '/releases/latest';
}

function znote_update_public_key(): string
{
	$file = dirname(__DIR__) . '/update-public.pem';
	return is_file($file) ? (string)file_get_contents($file) : '';
}

function znote_update_prepare_storage(): bool
{
	$directories = array(
		znote_update_storage(),
		znote_update_storage() . '/downloads',
		znote_update_storage() . '/staging',
		znote_update_storage() . '/backups',
	);

	foreach ($directories as $directory) {
		if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
			return false;
		}
	}

	$deny = znote_update_storage() . '/.htaccess';
	if (!is_file($deny)) {
		file_put_contents($deny, "Require all denied\nDeny from all\n");
	}

	return true;
}

function znote_update_url_allowed(string $url): bool
{
	$parts = parse_url($url);
	if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
		return false;
	}

	$host = strtolower((string)($parts['host'] ?? ''));
	return in_array($host, array('api.github.com', 'github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'), true);
}

function znote_update_http(string $url, int $maxBytes = 4194304): array
{
	if (!znote_update_url_allowed($url)) {
		return array('ok' => false, 'error' => 'Untrusted download address.');
	}

	if (!function_exists('curl_init')) {
		return array('ok' => false, 'error' => 'The PHP cURL extension is required.');
	}

	$handle = curl_init($url);
	$data = '';
	$tooLarge = false;
	$untrustedRedirect = false;
	$options = array(
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 5,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 90,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_USERAGENT => 'ZnoteX-Updater/' . znote_update_current_version(),
		CURLOPT_HTTPHEADER => array('Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'),
		CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$untrustedRedirect): int {
			if (preg_match('/^Location:\s*(\S+)/i', trim($header), $match) && str_contains($match[1], '://') && !znote_update_url_allowed($match[1])) {
				$untrustedRedirect = true;
				return 0;
			}
			return strlen($header);
		},
		CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$data, &$tooLarge, $maxBytes): int {
			if (strlen($data) + strlen($chunk) > $maxBytes) {
				$tooLarge = true;
				return 0;
			}
			$data .= $chunk;
			return strlen($chunk);
		},
	);
	if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
		$options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
		$options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
	}
	if (function_exists('znote_cainfo')) {
		$cainfo = znote_cainfo();
		if ($cainfo !== '') {
			$options[CURLOPT_CAINFO] = $cainfo;
		}
	}
	curl_setopt_array($handle, $options);

	$success = curl_exec($handle);
	$status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
	$error = curl_error($handle);
	curl_close($handle);

	if ($tooLarge) {
		return array('ok' => false, 'error' => 'The downloaded file exceeds the allowed size.');
	}
	if ($untrustedRedirect) {
		return array('ok' => false, 'error' => 'GitHub returned an untrusted redirect address.');
	}
	if ($success === false || $status < 200 || $status >= 300) {
		return array('ok' => false, 'error' => $error !== '' ? $error : 'HTTP error ' . $status . '.');
	}

	return array('ok' => true, 'data' => $data);
}

function znote_update_asset(array $release, string $name): ?array
{
	foreach (($release['assets'] ?? array()) as $asset) {
		if (is_array($asset) && (string)($asset['name'] ?? '') === $name) {
			return $asset;
		}
	}
	return null;
}

function znote_update_verify_manifest(string $json, string $signature): array
{
	$key = znote_update_public_key();
	if ($key === '' || !function_exists('openssl_verify')) {
		return array('ok' => false, 'error' => 'The update signature verifier is unavailable.');
	}

	$decodedSignature = base64_decode(trim($signature), true);
	if ($decodedSignature === false || openssl_verify($json, $decodedSignature, $key, OPENSSL_ALGO_SHA256) !== 1) {
		return array('ok' => false, 'error' => 'The update.json signature is invalid.');
	}

	$manifest = json_decode($json, true);
	if (!is_array($manifest) || (int)($manifest['schema'] ?? 0) !== ZNOTE_UPDATE_SCHEMA) {
		return array('ok' => false, 'error' => 'The update manifest is invalid or unsupported.');
	}

	$version = trim((string)($manifest['version'] ?? ''));
	$package = $manifest['package'] ?? null;
	$files = $manifest['files'] ?? null;
	if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)
		|| !is_array($package)
		|| !preg_match('/^[A-Za-z0-9._-]+\.zip$/', (string)($package['name'] ?? ''))
		|| !preg_match('/^[a-f0-9]{64}$/', (string)($package['sha256'] ?? ''))
		|| !is_array($files)
		|| $files === array()
	) {
		return array('ok' => false, 'error' => 'The signed manifest has missing or invalid fields.');
	}

	foreach ($files as $path => $hash) {
		if (!is_string($path) || znote_update_path($path) !== $path || !preg_match('/^[a-f0-9]{64}$/', (string)$hash)) {
			return array('ok' => false, 'error' => 'The signed file list is invalid.');
		}
	}

	return array('ok' => true, 'manifest' => $manifest);
}

function znote_update_path(string $path): string
{
	$path = str_replace('\\', '/', trim($path));
	$path = trim($path, '/');
	$parts = explode('/', $path);
	if ($path === '' || preg_match('/[\x00-\x1F\x7F:*?"<>|]/', $path)) {
		return '';
	}
	foreach ($parts as $part) {
		if ($part === '' || $part === '.' || $part === '..') {
			return '';
		}
	}
	return implode('/', $parts);
}

function znote_update_protected(string $path): bool
{
	$path = strtolower(znote_update_path($path));
	foreach (array('config.php', 'config.local.php', 'plugins/', 'layouts/', 'engine/cache/', 'engine/img/theme/', 'engine/update/', 'install/', 'release/', '.git/', '.github/') as $protected) {
		if ($path === rtrim($protected, '/') || str_starts_with($path, $protected)) {
			return true;
		}
	}
	return false;
}

function znote_update_latest(bool $refresh = false): array
{
	if (!znote_update_prepare_storage()) {
		return array('ok' => false, 'error' => 'The update storage directory cannot be created.');
	}

	$cacheFile = znote_update_storage() . '/latest.json';
	if (!$refresh && is_file($cacheFile) && filemtime($cacheFile) >= time() - 900) {
		$cached = json_decode((string)file_get_contents($cacheFile), true);
		if (is_array($cached)) {
			return $cached;
		}
	}

	$response = znote_update_http(znote_update_api_url());
	if (!$response['ok']) {
		return $response;
	}
	$release = json_decode($response['data'], true);
	if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) {
		return array('ok' => false, 'error' => 'GitHub did not return a stable release.');
	}

	$jsonAsset = znote_update_asset($release, 'update.json');
	$signatureAsset = znote_update_asset($release, 'update.json.sig');
	if ($jsonAsset === null || $signatureAsset === null) {
		return array('ok' => false, 'error' => 'The release is missing update.json or update.json.sig.');
	}

	$jsonResponse = znote_update_http((string)($jsonAsset['browser_download_url'] ?? ''));
	$signatureResponse = znote_update_http((string)($signatureAsset['browser_download_url'] ?? ''), 65536);
	if (!$jsonResponse['ok']) {
		return $jsonResponse;
	}
	if (!$signatureResponse['ok']) {
		return $signatureResponse;
	}

	$verified = znote_update_verify_manifest($jsonResponse['data'], $signatureResponse['data']);
	if (!$verified['ok']) {
		return $verified;
	}

	$manifest = $verified['manifest'];
	$tag = ltrim((string)($release['tag_name'] ?? ''), 'vV');
	if ($tag !== (string)$manifest['version']) {
		return array('ok' => false, 'error' => 'The GitHub tag does not match the signed version.');
	}

	$packageAsset = znote_update_asset($release, (string)$manifest['package']['name']);
	if ($packageAsset === null) {
		return array('ok' => false, 'error' => 'The signed ZIP package is missing from the release.');
	}

	$result = array(
		'ok' => true,
		'available' => version_compare((string)$manifest['version'], znote_update_current_version(), '>'),
		'current' => znote_update_current_version(),
		'manifest' => $manifest,
		'package_url' => (string)($packageAsset['browser_download_url'] ?? ''),
		'release_url' => (string)($release['html_url'] ?? ''),
	);
	file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	return $result;
}

function znote_update_check(string $label, bool $ok, string $detail): array
{
	return array('label' => $label, 'ok' => $ok, 'detail' => $detail);
}

function znote_update_remove_tree(string $path): void
{
	$base = realpath(znote_update_storage());
	$target = realpath($path);
	if ($base === false || $target === false || $target === $base || !str_starts_with(strtolower($target), strtolower($base . DIRECTORY_SEPARATOR))) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iterator as $item) {
		if ($item->isDir() && !$item->isLink()) {
			rmdir($item->getPathname());
		} else {
			unlink($item->getPathname());
		}
	}
	rmdir($target);
}

function znote_update_download_package(array $latest): array
{
	$manifest = $latest['manifest'];
	$name = (string)$manifest['package']['name'];
	$file = znote_update_storage() . '/downloads/' . $name;
	if (is_file($file) && hash_file('sha256', $file) === (string)$manifest['package']['sha256']) {
		return array('ok' => true, 'file' => $file);
	}

	$response = znote_update_http((string)$latest['package_url'], ZNOTE_UPDATE_MAX_BYTES);
	if (!$response['ok']) {
		return $response;
	}
	if (hash('sha256', $response['data']) !== (string)$manifest['package']['sha256']) {
		return array('ok' => false, 'error' => 'The ZIP checksum does not match the signed manifest.');
	}
	if (file_put_contents($file, $response['data'], LOCK_EX) === false) {
		return array('ok' => false, 'error' => 'The ZIP package cannot be saved.');
	}
	return array('ok' => true, 'file' => $file);
}

function znote_update_extract(string $zipFile, array $files, string $destination): array
{
	if (!class_exists('ZipArchive')) {
		return array('ok' => false, 'error' => 'The PHP Zip extension is required.');
	}
	if (is_dir($destination)) {
		znote_update_remove_tree($destination);
	}
	if (!mkdir($destination, 0750, true) && !is_dir($destination)) {
		return array('ok' => false, 'error' => 'The staging directory cannot be created.');
	}

	$zip = new ZipArchive();
	if ($zip->open($zipFile) !== true) {
		return array('ok' => false, 'error' => 'The ZIP package cannot be opened.');
	}
	$seen = array();
	for ($index = 0; $index < $zip->numFiles; $index++) {
		$raw = (string)$zip->getNameIndex($index);
		if (str_ends_with(str_replace('\\', '/', $raw), '/')) {
			continue;
		}
		$path = znote_update_path($raw);
		if ($path === '' || znote_update_protected($path) || !array_key_exists($path, $files) || isset($seen[$path])) {
			$zip->close();
			return array('ok' => false, 'error' => 'The ZIP contains an unexpected or protected file: ' . $raw);
		}
		$contents = $zip->getFromIndex($index);
		if (!is_string($contents) || hash('sha256', $contents) !== (string)$files[$path]) {
			$zip->close();
			return array('ok' => false, 'error' => 'A packaged file failed checksum validation: ' . $path);
		}
		$target = $destination . '/' . $path;
		$directory = dirname($target);
		if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
			$zip->close();
			return array('ok' => false, 'error' => 'A staging directory cannot be created.');
		}
		if (file_put_contents($target, $contents, LOCK_EX) === false) {
			$zip->close();
			return array('ok' => false, 'error' => 'A staged file cannot be written: ' . $path);
		}
		$seen[$path] = true;
	}
	$zip->close();

	if (count($seen) !== count($files)) {
		return array('ok' => false, 'error' => 'The ZIP does not contain every signed file.');
	}
	return array('ok' => true, 'directory' => $destination);
}

function znote_update_migration_safe(string $sql): bool
{
	$clean = preg_replace('~/\*.*?\*/~s', ' ', $sql) ?? $sql;
	$clean = preg_replace('/^\s*--.*$/m', ' ', $clean) ?? $clean;
	if (preg_match('/\b(DROP|TRUNCATE|RENAME|CHANGE|MODIFY|DELETE|UPDATE|REPLACE)\b/i', $clean)) {
		return false;
	}
	foreach (array_filter(array_map('trim', explode(';', $clean))) as $statement) {
		if (!preg_match('/^(CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS|ALTER\s+TABLE\s+[^\s]+\s+ADD\b|INSERT\s+IGNORE\b)/i', $statement)) {
			return false;
		}
	}
	return true;
}

function znote_update_preflight(array $latest): array
{
	$checks = array();
	if (empty($latest['ok'])) {
		return array('ok' => false, 'checks' => array(znote_update_check('Release metadata', false, (string)($latest['error'] ?? 'Unknown error.'))));
	}

	$manifest = $latest['manifest'];
	$version = (string)$manifest['version'];
	$newer = version_compare($version, znote_update_current_version(), '>');
	$checks[] = znote_update_check('Version', $newer, $newer ? 'Version ' . $version . ' is newer than ' . znote_update_current_version() . '.' : 'No newer stable version is available.');
	$checks[] = znote_update_check('Digital signature', true, 'update.json has a valid ZnoteX RSA/SHA-256 signature.');

	$requires = is_array($manifest['requirements'] ?? null) ? $manifest['requirements'] : array();
	$phpConstraint = (string)($requires['php'] ?? '>=8.1');
	$phpOk = function_exists('znote_extension_version_matches') && znote_extension_version_matches(PHP_VERSION, $phpConstraint);
	$checks[] = znote_update_check('PHP version', $phpOk, 'Required ' . $phpConstraint . '; running ' . PHP_VERSION . '.');

	$requiredExtensions = is_array($requires['extensions'] ?? null) ? $requires['extensions'] : array('curl', 'json', 'openssl', 'zip');
	foreach ($requiredExtensions as $extension) {
		$name = strtolower(trim((string)$extension));
		$loaded = $name !== '' && extension_loaded($name);
		$checks[] = znote_update_check('PHP extension: ' . $name, $loaded, $loaded ? 'Loaded.' : 'Missing from this PHP installation.');
	}

	$storageOk = znote_update_prepare_storage() && is_writable(znote_update_storage());
	$rootOk = is_writable(znote_update_root());
	$checks[] = znote_update_check('Update storage', $storageOk, $storageOk ? 'Writable.' : 'engine/update is not writable.');
	$checks[] = znote_update_check('Website files', $rootOk, $rootOk ? 'The website root is writable.' : 'The website root is not writable by PHP.');

	$free = @disk_free_space(znote_update_root());
	$needed = max(52428800, (int)($manifest['package']['size'] ?? 0) * 3);
	$diskOk = is_float($free) && $free >= $needed;
	$checks[] = znote_update_check('Disk space', $diskOk, $diskOk ? 'At least ' . number_format($needed / 1048576, 0) . ' MB is available.' : 'At least ' . number_format($needed / 1048576, 0) . ' MB of free space is required.');

	$download = $storageOk ? znote_update_download_package($latest) : array('ok' => false, 'error' => 'Storage is unavailable.');
	$checks[] = znote_update_check('ZIP checksum', !empty($download['ok']), !empty($download['ok']) ? 'The downloaded package matches its signed SHA-256 checksum.' : (string)($download['error'] ?? 'Download failed.'));

	$stage = znote_update_storage() . '/staging/' . preg_replace('/[^0-9A-Za-z._-]/', '-', $version);
	$extracted = !empty($download['ok']) ? znote_update_extract((string)$download['file'], $manifest['files'], $stage) : array('ok' => false, 'error' => 'The ZIP was not validated.');
	$checks[] = znote_update_check('Package contents', !empty($extracted['ok']), !empty($extracted['ok']) ? count($manifest['files']) . ' signed files validated; no protected or unexpected file found.' : (string)($extracted['error'] ?? 'Validation failed.'));

	$migrationOk = true;
	$migrationDetail = 'No database migration is required.';
	$migrations = is_array($manifest['migrations'] ?? null) ? $manifest['migrations'] : array();
	if ($migrations !== array()) {
		$migrationDetail = count($migrations) . ' additive migration(s) validated.';
		foreach ($migrations as $migration) {
			$path = is_array($migration) ? znote_update_path((string)($migration['file'] ?? '')) : '';
			$type = is_array($migration) ? (string)($migration['type'] ?? '') : '';
			$file = $stage . '/' . $path;
			if ($type !== 'expand' || $path === '' || !is_file($file) || !znote_update_migration_safe((string)file_get_contents($file))) {
				$migrationOk = false;
				$migrationDetail = 'A migration is missing, destructive or not marked as expand-only.';
				break;
			}
		}
	}
	$checks[] = znote_update_check('Database migrations', $migrationOk, $migrationDetail);

	$conflicts = array();
	$installedFile = znote_update_storage() . '/installed.json';
	if (is_file($installedFile)) {
		$installed = json_decode((string)file_get_contents($installedFile), true);
		foreach (($installed['files'] ?? array()) as $path => $hash) {
			$target = znote_update_root() . '/' . znote_update_path((string)$path);
			if (is_file($target) && hash_file('sha256', $target) !== (string)$hash) {
				$conflicts[] = (string)$path;
			}
		}
	}
	$checks[] = znote_update_check('Local modifications', $conflicts === array(), $conflicts === array() ? 'No locally modified managed file will be overwritten.' : 'Modified files would be overwritten: ' . implode(', ', array_slice($conflicts, 0, 8)));

	$ok = true;
	foreach ($checks as $check) {
		if (!$check['ok']) {
			$ok = false;
		}
	}
	return array('ok' => $ok, 'checks' => $checks, 'stage' => $stage, 'manifest' => $manifest);
}

function znote_update_backup(array $files, string $version): array
{
	$id = gmdate('Ymd-His') . '-from-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $version);
	$directory = znote_update_storage() . '/backups/' . $id;
	if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
		return array('ok' => false, 'error' => 'The backup directory cannot be created.');
	}
	$journal = array('id' => $id, 'version' => $version, 'created_at' => gmdate(DATE_ATOM), 'files' => array());
	foreach (array_keys($files) as $path) {
		$source = znote_update_root() . '/' . $path;
		$journal['files'][$path] = is_file($source);
		if (!is_file($source)) {
			continue;
		}
		$target = $directory . '/files/' . $path;
		if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
			return array('ok' => false, 'error' => 'A backup directory cannot be created.');
		}
		if (!copy($source, $target)) {
			return array('ok' => false, 'error' => 'A managed file cannot be backed up: ' . $path);
		}
	}
	file_put_contents($directory . '/backup.json', json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	return array('ok' => true, 'id' => $id, 'directory' => $directory, 'journal' => $journal);
}

function znote_update_restore_backup(array $backup): array
{
	$directory = (string)($backup['directory'] ?? '');
	$journal = $backup['journal'] ?? null;
	if (!is_array($journal) || !is_dir($directory)) {
		return array('ok' => false, 'error' => 'The backup is invalid.');
	}
	foreach (($journal['files'] ?? array()) as $path => $existed) {
		$path = znote_update_path((string)$path);
		if ($path === '' || znote_update_protected($path)) {
			return array('ok' => false, 'error' => 'The backup contains an invalid path.');
		}
		$target = znote_update_root() . '/' . $path;
		if ($existed) {
			$source = $directory . '/files/' . $path;
			if (!is_file($source)) {
				return array('ok' => false, 'error' => 'A backup file is missing: ' . $path);
			}
			if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
				return array('ok' => false, 'error' => 'A destination directory cannot be restored.');
			}
			if (!copy($source, $target)) {
				return array('ok' => false, 'error' => 'A file cannot be restored: ' . $path);
			}
		} elseif (is_file($target)) {
			unlink($target);
		}
	}
	return array('ok' => true);
}

function znote_update_apply_migrations(array $manifest, string $stage): array
{
	$migrations = is_array($manifest['migrations'] ?? null) ? $manifest['migrations'] : array();
	if ($migrations === array()) {
		return array('ok' => true);
	}

	db()->execute('CREATE TABLE IF NOT EXISTS znote_migrations (migration VARCHAR(191) NOT NULL PRIMARY KEY, checksum CHAR(64) NOT NULL, executed_at INT UNSIGNED NOT NULL, execution_time_ms INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
	foreach ($migrations as $migration) {
		$path = znote_update_path((string)($migration['file'] ?? ''));
		$file = $stage . '/' . $path;
		$sql = is_file($file) ? (string)file_get_contents($file) : '';
		$checksum = hash('sha256', $sql);
		$found = db()->fetchOne('SELECT checksum FROM znote_migrations WHERE migration = ?', array($path));
		if (is_array($found)) {
			if ((string)($found['checksum'] ?? '') !== $checksum) {
				return array('ok' => false, 'error' => 'An applied migration has changed: ' . $path);
			}
			continue;
		}
		$started = microtime(true);
		foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
			if (!db()->execute($statement)) {
				return array('ok' => false, 'error' => 'Database migration failed: ' . $path);
			}
		}
		$milliseconds = (int)round((microtime(true) - $started) * 1000);
		db()->execute('INSERT INTO znote_migrations (migration, checksum, executed_at, execution_time_ms) VALUES (?, ?, ?, ?)', array($path, $checksum, time(), $milliseconds));
	}
	return array('ok' => true);
}

function znote_update_copy_files(array $files, string $stage): array
{
	foreach ($files as $path => $hash) {
		$source = $stage . '/' . $path;
		$target = znote_update_root() . '/' . $path;
		$directory = dirname($target);
		if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
			return array('ok' => false, 'error' => 'A destination directory cannot be created: ' . $path);
		}
		$temp = $target . '.znote-update-' . bin2hex(random_bytes(6));
		if (!copy($source, $temp) || hash_file('sha256', $temp) !== $hash) {
			if (is_file($temp)) {
				unlink($temp);
			}
			return array('ok' => false, 'error' => 'A file cannot be prepared: ' . $path);
		}
		if (!rename($temp, $target)) {
			if (!copy($temp, $target)) {
				unlink($temp);
				return array('ok' => false, 'error' => 'A file cannot be installed: ' . $path);
			}
			unlink($temp);
		}
		if (hash_file('sha256', $target) !== $hash) {
			return array('ok' => false, 'error' => 'An installed file failed checksum validation: ' . $path);
		}
	}
	return array('ok' => true);
}

function znote_update_install(array $latest): array
{
	$preflight = znote_update_preflight($latest);
	if (!$preflight['ok']) {
		return array('ok' => false, 'error' => 'The pre-installation check is not fully green.', 'checks' => $preflight['checks']);
	}

	$manifest = $preflight['manifest'];
	$backup = znote_update_backup($manifest['files'], znote_update_current_version());
	if (!$backup['ok']) {
		return $backup;
	}

	$migrations = znote_update_apply_migrations($manifest, $preflight['stage']);
	if (!$migrations['ok']) {
		return $migrations;
	}

	$lock = znote_update_storage() . '/maintenance.lock';
	file_put_contents($lock, json_encode(array('version' => $manifest['version'], 'started_at' => gmdate(DATE_ATOM))));
	try {
		$copied = znote_update_copy_files($manifest['files'], $preflight['stage']);
		if (!$copied['ok']) {
			$restored = znote_update_restore_backup($backup);
			return array('ok' => false, 'error' => $copied['error'] . ($restored['ok'] ? ' The file backup was restored.' : ' Automatic restoration failed: ' . $restored['error']));
		}
		$installed = array(
			'version' => (string)$manifest['version'],
			'installed_at' => gmdate(DATE_ATOM),
			'backup' => (string)$backup['id'],
			'files' => $manifest['files'],
		);
		file_put_contents(znote_update_storage() . '/installed.json', json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		if (function_exists('znote_cache_flush')) {
			znote_cache_flush();
		}
		return array('ok' => true, 'version' => (string)$manifest['version'], 'backup' => (string)$backup['id']);
	} finally {
		if (is_file($lock)) {
			unlink($lock);
		}
	}
}

function znote_update_backups(): array
{
	$result = array();
	foreach (glob(znote_update_storage() . '/backups/*/backup.json') ?: array() as $file) {
		$journal = json_decode((string)file_get_contents($file), true);
		if (is_array($journal)) {
			$result[] = array('directory' => dirname($file), 'journal' => $journal);
		}
	}
	usort($result, static fn(array $a, array $b): int => strcmp((string)$b['journal']['created_at'], (string)$a['journal']['created_at']));
	return $result;
}

function znote_update_rollback_latest(): array
{
	$backups = znote_update_backups();
	if ($backups === array()) {
		return array('ok' => false, 'error' => 'No update backup is available.');
	}
	$lock = znote_update_storage() . '/maintenance.lock';
	file_put_contents($lock, json_encode(array('rollback' => true, 'started_at' => gmdate(DATE_ATOM))));
	try {
		$result = znote_update_restore_backup($backups[0]);
		if ($result['ok']) {
			if (is_file(znote_update_storage() . '/installed.json')) {
				unlink(znote_update_storage() . '/installed.json');
			}
			$result['version'] = (string)$backups[0]['journal']['version'];
		}
		return $result;
	} finally {
		if (is_file($lock)) {
			unlink($lock);
		}
	}
}
