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