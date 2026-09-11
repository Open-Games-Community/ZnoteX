<?php

class Cache
{
    protected string $_file;
    protected int $_lifespan = 0;
    protected mixed $_content = null;
    protected bool $_memory = false;
    protected bool $_canMemory = false;

    const EXT = '.cache';

    public function __construct(string $file)
    {
        $cfg = function_exists('config')
            ? config('cache')
            : ($GLOBALS['config']['cache'] ?? []);

        $this->_lifespan = (int)($cfg['lifespan'] ?? 60);

        if (function_exists('apcu_fetch')) {
            $this->_canMemory = true;
            $this->_memory = (bool)($cfg['memory'] ?? false);
        }

        $this->_file = $file . self::EXT;

        if (!$this->_canMemory && ($cfg['memory'] ?? false)) {
            die(
                "<p><strong>Configuration error!</strong><br>
                APCu is not enabled.<br>
                Disable memory cache or install php-apcu.</p>"
            );
        }
    }

    public function setExpiration(int $span): void
    {
        $this->_lifespan = $span;
    }

    public function useMemory(bool $bool): bool
    {
        if ($bool && $this->_canMemory) {
            $this->_memory = true;
            return true;
        }
        $this->_memory = false;
        return false;
    }

    public function setContent(mixed $content): void
    {
        $this->_content = is_array($content) ? json_encode($content) : $content;
    }

    public function hasExpired(): bool
    {
        if ($this->_memory) {
            return !apcu_exists($this->_file);
        }

        if (!is_file($this->_file)) {
            return true;
        }

        return time() > filemtime($this->_file) + $this->_lifespan;
    }

    public function remainingTime(): int
    {
        if ($this->_memory && apcu_exists($this->_file)) {
            $info = apcu_cache_info();
            foreach ($info['cache_list'] ?? [] as $item) {
                if (($item['info'] ?? null) === $this->_file) {
                    return max(0, ($item['creation_time'] + $item['ttl']) - time());
                }
            }
            return 0;
        }

        if (!$this->hasExpired()) {
            return max(0, (filemtime($this->_file) + $this->_lifespan) - time());
        }

        return 0;
    }

    public function save(): bool
    {
        if ($this->_memory) {
            return apcu_store($this->_file, $this->_content, $this->_lifespan);
        }

        return file_put_contents($this->_file, (string)$this->_content) !== false;
    }

    public function load(): mixed
    {
        if ($this->_memory) {
            return apcu_fetch($this->_file);
        }

        if (!is_file($this->_file)) {
            return false;
        }

        $content = file_get_contents($this->_file);
        if ($content === false || $content === '') {
            return false;
        }

        $json = json_decode($content, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $json : $content;
    }
}

/**
 * Wipe every on-disk Cache file plus the APCu store so the next read rebuilds
 * from source.
 *
 * Used by the admin "Refresh catalogue" action on the Plugins and Layout pages:
 * refreshing only the remote catalogue is not enough when a stale cache is
 * still hiding a freshly installed or updated plugin/theme, its options or its
 * locale strings. Everything cleared here is rebuilt lazily on the next request.
 *
 * @return int number of cache files removed
 */
function znote_cache_flush(): int
{
    $removed = 0;

    foreach (glob('engine/cache/*' . Cache::EXT) ?: array() as $file) {
        if (is_file($file) && @unlink($file)) {
            $removed++;
        }
    }

    if (function_exists('apcu_clear_cache')) {
        @apcu_clear_cache();
    }

    return $removed;
}