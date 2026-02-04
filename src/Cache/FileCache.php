<?php

declare(strict_types=1);

namespace Fw\Cache;

/**
 * File-based cache driver.
 *
 * Persists cache across requests using the filesystem.
 * Good for development and single-server deployments.
 */
final class FileCache implements CacheInterface
{
    private string $path;
    private int $defaultTtl;

    public function __construct(string $path, int $defaultTtl = 3600)
    {
        $this->path = rtrim($path, '/');
        $this->defaultTtl = $defaultTtl;

        // Handle race condition: another process might create the directory
        if (!is_dir($this->path) && !@mkdir($this->path, 0755, true) && !is_dir($this->path)) {
            throw new \RuntimeException("Failed to create cache directory: {$this->path}");
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            // Invalid cache format - delete and return default
            $this->delete($key);
            return $default;
        }

        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);

        // Create directory if needed - handle race condition properly
        // mkdir with recursive=true returns false if dir already exists,
        // so we check is_dir after to handle concurrent creation
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $data = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : time() + $this->defaultTtl,
        ];

        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return file_put_contents($file, $json, LOCK_EX) !== false;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        $this->deleteDirectory($this->path);
        mkdir($this->path, 0755, true);
        return true;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key, $this);

        if ($value !== $this) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function getMany(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        return $results;
    }

    public function setMany(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * Remove expired cache files.
     */
    public function gc(): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                $content = file_get_contents($file->getPathname());
                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (is_array($data) && isset($data['expires']) && $data['expires'] < time()) {
                        unlink($file->getPathname());
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function getFilePath(string $key): string
    {
        // Use SHA256 for collision resistance (SHA1 is cryptographically broken)
        $hash = hash('sha256', $key);
        $dir = substr($hash, 0, 2);
        return "{$this->path}/{$dir}/{$hash}.cache";
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
