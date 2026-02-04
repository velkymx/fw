<?php

declare(strict_types=1);

namespace Fw\Security;

final class Sanitizer
{
    public static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function stripTags(string $value, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            return strip_tags($value);
        }

        $allowed = implode('', array_map(fn($tag) => "<$tag>", $allowedTags));
        return strip_tags($value, $allowed);
    }

    public static function alphanumeric(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? '';
    }

    public static function alpha(string $value): string
    {
        return preg_replace('/[^a-zA-Z]/', '', $value) ?? '';
    }

    public static function numeric(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    public static function slug(string $value): string
    {
        $value = preg_replace('/[^\w\s-]/', '', strtolower(trim($value))) ?? '';
        return preg_replace('/[\s_]+/', '-', $value) ?? '';
    }

    public static function filename(string $value): string
    {
        $value = basename($value);
        $value = preg_replace('/[^\w\s.\-]/', '', $value) ?? '';
        $value = preg_replace('/\.{2,}/', '.', $value) ?? '';
        return trim($value, '. ');
    }

    /**
     * Sanitize and validate a filesystem path.
     *
     * Returns the resolved real path if it exists, null otherwise.
     * This is secure because it only returns paths that can be
     * verified to exist on the filesystem.
     *
     * @param string $value The path to sanitize
     * @param string|null $basePath Optional base directory to restrict paths to
     * @return string|null The resolved real path, or null if invalid/not found
     */
    public static function path(string $value, ?string $basePath = null): ?string
    {
        // Reject paths with obvious traversal attempts
        if (preg_match('/\.\.|[<>:"|?*\x00]/', $value)) {
            return null;
        }

        // Attempt to resolve to real path - this is the only safe approach
        $realPath = realpath($value);

        if ($realPath === false) {
            // Path doesn't exist or can't be resolved - reject it
            // Never return unverified paths as they could bypass security
            return null;
        }

        // If base path restriction is specified, verify the path is within it
        if ($basePath !== null) {
            $realBasePath = realpath($basePath);
            if ($realBasePath === false) {
                return null;
            }

            // Ensure the resolved path starts with the base path
            if (!str_starts_with($realPath, $realBasePath . DIRECTORY_SEPARATOR)
                && $realPath !== $realBasePath) {
                return null;
            }
        }

        return $realPath;
    }

    /**
     * Sanitize email address.
     *
     * Removes invalid characters and validates format.
     * Returns empty string if invalid.
     */
    public static function email(string $value): string
    {
        $value = trim(strtolower($value));

        // Remove characters not allowed in email addresses
        $value = preg_replace('/[^a-z0-9.!#$%&\'*+\/=?^_`{|}~@-]/i', '', $value) ?? '';

        // Validate the result is a proper email
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $value;
    }

    /**
     * Dangerous URL schemes that must never be allowed.
     * @var list<string>
     */
    private const DANGEROUS_SCHEMES = [
        'javascript',
        'vbscript',
        'data',
        'file',
        'blob',
    ];

    /**
     * Unicode characters used for text direction/formatting attacks.
     * These can be used to visually hide malicious content.
     */
    private const array UNICODE_CONTROL_CHARS = [
        "\u{200B}", // Zero Width Space
        "\u{200C}", // Zero Width Non-Joiner
        "\u{200D}", // Zero Width Joiner
        "\u{200E}", // Left-to-Right Mark
        "\u{200F}", // Right-to-Left Mark
        "\u{202A}", // Left-to-Right Embedding
        "\u{202B}", // Right-to-Left Embedding
        "\u{202C}", // Pop Directional Formatting
        "\u{202D}", // Left-to-Right Override
        "\u{202E}", // Right-to-Left Override (used in URL obfuscation)
        "\u{2060}", // Word Joiner
        "\u{2061}", // Function Application
        "\u{2062}", // Invisible Times
        "\u{2063}", // Invisible Separator
        "\u{2064}", // Invisible Plus
        "\u{FEFF}", // Zero Width No-Break Space (BOM)
    ];

    /**
     * Sanitize URL.
     *
     * Only allows http/https URLs. Returns empty string if invalid.
     * Explicitly rejects dangerous schemes like javascript:, data:, etc.
     */
    public static function url(string $value): string
    {
        $value = trim($value);

        // Remove ASCII control characters, null bytes, and whitespace
        $value = preg_replace('/[\x00-\x1F\x7F\s]/', '', $value) ?? '';

        // Remove Unicode control characters used for text direction attacks
        // These can visually hide malicious schemes like javascript:
        $value = str_replace(self::UNICODE_CONTROL_CHARS, '', $value);

        // Normalize to lowercase for scheme checking (URLs are case-insensitive in scheme)
        $lowercaseValue = mb_strtolower($value);

        // Explicitly reject dangerous schemes (defense-in-depth)
        foreach (self::DANGEROUS_SCHEMES as $scheme) {
            if (str_starts_with($lowercaseValue, $scheme . ':')) {
                return '';
            }
        }

        // Must start with http:// or https:// (case-insensitive via the 'i' flag)
        if (!preg_match('/^https?:\/\//i', $value)) {
            return '';
        }

        // Validate as URL using PHP's filter
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }

        // Final check: parse the URL and verify scheme is http or https
        $parsed = parse_url($value);
        if ($parsed === false || !isset($parsed['scheme'])) {
            return '';
        }

        $scheme = mb_strtolower($parsed['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        return $value;
    }

    /**
     * Sanitize to integer.
     *
     * Extracts numeric characters (and leading minus) and converts to int.
     */
    public static function int(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $value = (string) $value;

        // Extract optional minus sign and digits
        if (preg_match('/^-?\d+/', preg_replace('/[^\d-]/', '', $value) ?? '', $matches)) {
            return (int) $matches[0];
        }

        return 0;
    }

    /**
     * Sanitize to float.
     *
     * Extracts numeric characters, decimal point, and leading minus.
     */
    public static function float(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $value = (string) $value;

        // Keep only digits, decimal point, and minus sign
        $cleaned = preg_replace('/[^\d.\-]/', '', $value) ?? '';

        // Handle multiple decimal points (keep only first)
        $parts = explode('.', $cleaned, 3);
        if (count($parts) > 2) {
            $cleaned = $parts[0] . '.' . $parts[1];
        }

        return (float) $cleaned;
    }

    public static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function trim(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Safely decode JSON string.
     *
     * Uses a reasonable depth limit to prevent billion laughs attacks.
     *
     * @param string $value JSON string
     * @param int $maxDepth Maximum nesting depth (default 32)
     */
    public static function json(string $value, int $maxDepth = 32): ?array
    {
        try {
            $decoded = json_decode($value, true, $maxDepth, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }

    public static function array(array $data, array $rules): array
    {
        $result = [];

        foreach ($rules as $key => $sanitizer) {
            if (!isset($data[$key])) {
                continue;
            }

            $value = $data[$key];

            if (is_callable($sanitizer)) {
                $result[$key] = $sanitizer($value);
            } elseif (is_string($sanitizer) && method_exists(self::class, $sanitizer)) {
                $result[$key] = self::$sanitizer($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
