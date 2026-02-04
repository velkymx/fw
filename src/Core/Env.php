<?php

declare(strict_types=1);

namespace Fw\Core;

/**
 * Environment variable loader and accessor.
 *
 * Loads variables from .env files and provides a clean API for accessing them.
 * Supports type casting and default values.
 */
final class Env
{
    private static bool $loaded = false;
    private static array $variables = [];

    /**
     * Load environment variables from a .env file.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            // Skip comments
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Remove quotes if present
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Store in our cache
            self::$variables[$key] = $value;

            // Also set in $_ENV and putenv for compatibility
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Check our cache first
        if (isset(self::$variables[$key])) {
            return self::castValue(self::$variables[$key]);
        }

        // Fall back to $_ENV
        if (isset($_ENV[$key])) {
            return self::castValue($_ENV[$key]);
        }

        // Fall back to getenv
        $value = getenv($key);
        if ($value !== false) {
            return self::castValue($value);
        }

        return $default;
    }

    /**
     * Get a string environment variable.
     */
    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key);
        return $value !== null ? (string) $value : $default;
    }

    /**
     * Get an integer environment variable.
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value !== null ? (int) $value : $default;
    }

    /**
     * Get a boolean environment variable.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string) $value);

        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Get an array environment variable (comma-separated).
     */
    public static function array(string $key, array $default = []): array
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return array_map('trim', explode(',', (string) $value));
    }

    /**
     * Check if an environment variable is set.
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Get a required environment variable (throws if not set).
     */
    public static function require(string $key): mixed
    {
        $value = self::get($key);

        if ($value === null) {
            throw new \RuntimeException("Required environment variable '{$key}' is not set");
        }

        return $value;
    }

    /**
     * Cast string values to appropriate PHP types.
     */
    private static function castValue(string $value): mixed
    {
        $lower = strtolower($value);

        // Boolean values
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }

        // Null
        if ($lower === 'null' || $lower === '') {
            return null;
        }

        // Numeric values
        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        return $value;
    }

    /**
     * Check if .env has been loaded.
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    /**
     * Clear all loaded environment variables (useful for testing).
     */
    public static function clear(): void
    {
        self::$variables = [];
        self::$loaded = false;
    }
}

/**
 * Helper function to get environment variables.
 */
function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}
