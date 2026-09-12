<?php

class Cache
{
    protected string $_file;
    protected string $_key;
    protected string $_prefix;
    protected int $_lifespan = 0;
    protected mixed $_content = null;
    protected bool $_memory = false;
    protected bool $_canMemory = false;

    private const FORMAT = 'ZNOTEX_CACHE_V1:';
    public const EXT = '.cache';

    public function __construct(string $file)
    {
        $cfg = function_exists('config')
            ? config('cache')
            : ($GLOBALS['config']['cache'] ?? array());

        if (!is_array($cfg)) {
            $cfg = array();
        }

        $this->_lifespan = max(0, (int)($cfg['lifespan'] ?? 60));
        $this->_prefix = self::normalizePrefix((string)($cfg['prefix'] ?? 'znote_'));
        $this->_canMemory = self::memoryAvailable();
        $this->_memory = !empty($cfg['memory']) && $this->_canMemory;
        $this->_file = $file . self::EXT;
        $logicalName = str_replace('\\', '/', $this->_file);
        $this->_key = $this->_prefix . 'cache:' . hash('sha256', $logicalName);
    }

    public static function memoryAvailable(): bool
    {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store')) {
            return false;
        }

        if (function_exists('apcu_enabled')) {
            return apcu_enabled();
        }

        if (PHP_SAPI === 'cli' && !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOL);
    }

    public static function configuredPrefix(): string
    {
        $cfg = function_exists('config')
            ? config('cache')
            : ($GLOBALS['config']['cache'] ?? array());

        return self::normalizePrefix(is_array($cfg) ? (string)($cfg['prefix'] ?? 'znote_') : 'znote_');
    }

    private static function normalizePrefix(string $prefix): string
    {
        $prefix = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', trim($prefix)) ?? '';
        return substr($prefix !== '' ? $prefix : 'znote_', 0, 64);
    }

    public function setExpiration(int $span): void
    {
        $this->_lifespan = max(0, $span);
    }

    public function useMemory(bool $bool): bool
    {
        $this->_memory = $bool && $this->_canMemory;
        return $this->_memory;
    }

    public function driver(): string
    {
        return $this->_memory ? 'apcu' : 'file';
    }

    public function setContent(mixed $content): void
    {
        $this->_content = $content;
    }

    public function hasExpired(): bool
    {
        if ($this->_memory) {
            return !apcu_exists($this->_key);
        }

        if (!is_file($this->_file)) {
            return true;
        }

        if ($this->_lifespan === 0) {
            return false;
        }

        $modified = filemtime($this->_file);
        return $modified === false || time() >= $modified + $this->_lifespan;
    }

    public function remainingTime(): int
    {
        if ($this->hasExpired()) {
            return 0;
        }

        if ($this->_lifespan === 0) {
            return PHP_INT_MAX;
        }

        if ($this->_memory) {
            $success = false;
            $payload = apcu_fetch($this->_key, $success);
            if (!$success) {
                return 0;
            }

            $envelope = $this->decodeEnvelope($payload);
            return is_array($envelope)
                ? max(0, (int)$envelope['expires'] - time())
                : 0;
        }

        $modified = filemtime($this->_file);
        return $modified === false ? 0 : max(0, ($modified + $this->_lifespan) - time());
    }

    public function save(): bool
    {
        $payload = $this->encodeContent();
        if ($payload === false) {
            return false;
        }

        if ($this->_memory) {
            return apcu_store($this->_key, $payload, $this->_lifespan);
        }

        $directory = dirname($this->_file);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $temporary = tempnam($directory, '.znote-cache-');
        if ($temporary === false) {
            return false;
        }

        $written = file_put_contents($temporary, $payload, LOCK_EX);
        if ($written === false) {
            @unlink($temporary);
            return false;
        }

        if (@rename($temporary, $this->_file)) {
            clearstatcache(true, $this->_file);
            return true;
        }

        $saved = file_put_contents($this->_file, $payload, LOCK_EX) !== false;
        @unlink($temporary);
        clearstatcache(true, $this->_file);
        return $saved;
    }

    public function load(): mixed
    {
        if ($this->_memory) {
            $success = false;
            $payload = apcu_fetch($this->_key, $success);
            return $success ? $this->decodeContent($payload) : false;
        }

        if (!is_file($this->_file)) {
            return false;
        }

        $handle = @fopen($this->_file, 'rb');
        if ($handle === false) {
            return false;
        }

        $payload = false;
        if (flock($handle, LOCK_SH)) {
            $payload = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return $payload === false || $payload === '' ? false : $this->decodeContent($payload);
    }

    public function delete(): bool
    {
        if ($this->_memory) {
            return !apcu_exists($this->_key) || apcu_delete($this->_key);
        }

        if (!is_file($this->_file)) {
            return true;
        }

        $deleted = @unlink($this->_file);
        clearstatcache(true, $this->_file);
        return $deleted;
    }

    private function encodeContent(): string|false
    {
        $envelope = array(
            'version' => 1,
            'expires' => $this->_lifespan === 0 ? 0 : time() + $this->_lifespan,
            'value' => $this->_content,
        );

        try {
            return self::FORMAT . base64_encode(serialize($envelope));
        } catch (Throwable $error) {
            return false;
        }
    }

    private function decodeEnvelope(mixed $payload): array|false
    {
        if (!is_string($payload) || !str_starts_with($payload, self::FORMAT)) {
            return false;
        }

        $decoded = base64_decode(substr($payload, strlen(self::FORMAT)), true);
        if ($decoded === false) {
            return false;
        }

        try {
            $envelope = @unserialize($decoded, array('allowed_classes' => false));
        } catch (Throwable $error) {
            return false;
        }

        return is_array($envelope)
            && ($envelope['version'] ?? null) === 1
            && array_key_exists('value', $envelope)
            ? $envelope
            : false;
    }

    private function decodeContent(mixed $payload): mixed
    {
        $envelope = $this->decodeEnvelope($payload);
        if ($envelope !== false) {
            return $envelope['value'];
        }

        if (!is_string($payload) || $payload === '') {
            return $payload;
        }

        $json = json_decode($payload, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $payload;
    }
}

function znote_cache_stats(): array
{
    $files = 0;
    $bytes = 0;
    $root = realpath('engine/cache');

    if ($root !== false && is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), Cache::EXT)) {
                $files++;
                $bytes += $entry->getSize();
            }
        }
    }

    $cfg = function_exists('config')
        ? config('cache')
        : ($GLOBALS['config']['cache'] ?? array());
    $requestedMemory = is_array($cfg) && !empty($cfg['memory']);

    return array(
        'driver' => $requestedMemory && Cache::memoryAvailable() ? 'APCu' : 'Files',
        'requested_memory' => $requestedMemory,
        'apcu_available' => Cache::memoryAvailable(),
        'files' => $files,
        'bytes' => $bytes,
        'prefix' => Cache::configuredPrefix(),
    );
}

function znote_cache_flush(): int
{
    $removed = 0;
    $root = realpath('engine/cache');

    if ($root !== false && is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), Cache::EXT) && @unlink($entry->getPathname())) {
                $removed++;
            }
        }
    }

    if (Cache::memoryAvailable() && function_exists('apcu_cache_info')) {
        $prefix = Cache::configuredPrefix() . 'cache:';
        $info = apcu_cache_info(false);
        foreach (($info['cache_list'] ?? array()) as $item) {
            $key = (string)($item['info'] ?? '');
            $legacyKey = str_replace('\\', '/', $key);
            if ((str_starts_with($key, $prefix) || str_starts_with($legacyKey, 'engine/cache/')) && apcu_delete($key)) {
                $removed++;
            }
        }
    }

    return $removed;
}
