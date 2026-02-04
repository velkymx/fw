<?php

declare(strict_types=1);

namespace Fw\Core;

final class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private bool $sent = false;
    private string $body = '';

    public function __construct(string $body = '', int $statusCode = 200)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
    }

    private const array STATUS_TEXTS = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function setStatus(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Set a response header.
     *
     * @throws \InvalidArgumentException If header name or value contains invalid characters
     */
    public function header(string $name, string $value): self
    {
        $this->validateHeader($name, $value);
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple response headers.
     *
     * @throws \InvalidArgumentException If any header name or value contains invalid characters
     */
    public function headers(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->validateHeader($name, $value);
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * Validate header name and value to prevent header injection attacks.
     *
     * @throws \InvalidArgumentException If header contains CRLF or other invalid characters
     */
    private function validateHeader(string $name, string $value): void
    {
        // Check for CRLF injection (response splitting attack)
        if (preg_match("/[\r\n]/", $name) || preg_match("/[\r\n]/", $value)) {
            throw new \InvalidArgumentException(
                'Header name or value contains invalid characters (CR/LF not allowed)'
            );
        }

        // Validate header name contains only valid characters (RFC 7230)
        // Token = 1*tchar, tchar = "!" / "#" / "$" / "%" / "&" / "'" / "*"
        //         / "+" / "-" / "." / "^" / "_" / "`" / "|" / "~" / DIGIT / ALPHA
        // Note: While RFC allows many special chars, we restrict to alphanumeric + hyphen
        // for security (underscore excluded as it's often server-specific like X_FORWARDED)
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9\-]*$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid header name: {$name}. Must start with a letter and contain only alphanumeric characters and hyphens."
            );
        }

        // Check for null bytes
        if (str_contains($name, "\0") || str_contains($value, "\0")) {
            throw new \InvalidArgumentException(
                'Header name or value contains null bytes'
            );
        }
    }

    public function contentType(string $type, ?string $charset = 'UTF-8'): self
    {
        $this->headers['Content-Type'] = $charset !== null && $charset !== ''
            ? "$type; charset=$charset"
            : $type;
        return $this;
    }

    public function json(mixed $data, int $flags = JSON_THROW_ON_ERROR): never
    {
        $this->contentType('application/json');
        $this->send(json_encode($data, $flags));
    }

    public function html(string $content): never
    {
        $this->contentType('text/html');
        $this->send($content);
    }

    public function text(string $content): never
    {
        $this->contentType('text/plain');
        $this->send($content);
    }

    /**
     * Redirect to a URL.
     *
     * By default, only allows same-origin redirects to prevent open redirect attacks.
     * Pass $allowExternal = true to explicitly allow external URLs (use with caution).
     *
     * @param string $url The URL to redirect to
     * @param int $code HTTP status code (301, 302, 303, 307, 308)
     * @param bool $allowExternal Set to true to allow external URLs (dangerous if URL is user-controlled)
     * @throws \InvalidArgumentException If URL is external and $allowExternal is false
     */
    public function redirect(string $url, int $code = 302, bool $allowExternal = false): never
    {
        // Validate redirect URL to prevent open redirect attacks
        if (!$allowExternal && !$this->isSafeRedirectUrl($url)) {
            throw new \InvalidArgumentException(
                'Redirect URL must be same-origin. Use $allowExternal = true for external URLs.'
            );
        }

        $this->setStatus($code);
        $this->header('Location', $url);
        $this->send();
    }

    /**
     * Check if a URL is safe for redirect (same-origin or relative).
     */
    private function isSafeRedirectUrl(string $url): bool
    {
        // Empty URL is not safe
        if ($url === '') {
            return false;
        }

        // Relative URLs starting with / are safe
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // Check for protocol-relative URLs (//evil.com) - not safe
        if (str_starts_with($url, '//')) {
            return false;
        }

        // Parse the URL
        $parsed = parse_url($url);

        // Relative URLs without scheme/host are safe
        if (!isset($parsed['scheme']) && !isset($parsed['host'])) {
            return true;
        }

        // If it has a host, must match current host
        if (isset($parsed['host'])) {
            return $this->isSameOrigin($url);
        }

        return true;
    }

    /**
     * Redirect back to the previous page.
     *
     * Only redirects to same-origin URLs to prevent open redirect attacks.
     * Falls back to the provided URL (default '/') if referer is missing or external.
     */
    public function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        if ($referer !== null && $this->isSameOrigin($referer)) {
            $this->redirect($referer);
        }

        $this->redirect($fallback);
    }

    /**
     * Check if a URL is same-origin (prevents open redirect attacks).
     */
    private function isSameOrigin(string $url): bool
    {
        $parsed = parse_url($url);

        // Relative URLs are always safe
        if (!isset($parsed['host'])) {
            return true;
        }

        // Get current host
        $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

        // Strip port from current host for comparison
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);
        $refererHost = preg_replace('/:\d+$/', '', $parsed['host']);

        // Must match exactly (case-insensitive)
        return strcasecmp($currentHost, $refererHost) === 0;
    }

    public function noContent(): never
    {
        $this->setStatus(204);
        $this->send();
    }

    /**
     * Allowed base directories for file downloads.
     * @var list<string>
     */
    private static array $downloadBasePaths = [];

    /**
     * Configure allowed base directories for downloads.
     *
     * Only directories that exist and can be resolved are accepted.
     * Invalid paths are silently ignored.
     *
     * @param list<string> $paths Absolute paths to allowed directories
     */
    public static function setDownloadBasePaths(array $paths): void
    {
        self::$downloadBasePaths = [];
        foreach ($paths as $path) {
            $realPath = realpath($path);
            // Only accept paths that:
            // 1. Can be resolved (realpath succeeds)
            // 2. Are actual directories
            if ($realPath !== false && is_dir($realPath)) {
                self::$downloadBasePaths[] = rtrim($realPath, '/');
            }
        }
    }

    /**
     * Send a file download response.
     *
     * SECURITY: Downloads are DENIED by default unless base paths are configured
     * via setDownloadBasePaths(). This prevents arbitrary file disclosure.
     *
     * @param string $path Absolute path to the file
     * @param string|null $name Optional filename for Content-Disposition
     * @throws \RuntimeException If download base paths are not configured
     * @throws \InvalidArgumentException If path is outside allowed directories
     */
    public function download(string $path, ?string $name = null): never
    {
        // SECURITY: Require explicit configuration of allowed download paths
        // This is a default-deny approach to prevent arbitrary file disclosure
        if (self::$downloadBasePaths === []) {
            throw new \RuntimeException(
                'File downloads are disabled. Configure allowed paths with ' .
                'Response::setDownloadBasePaths() before using download().'
            );
        }

        // Resolve the real path to prevent directory traversal
        $realPath = realpath($path);

        if ($realPath === false || !is_file($realPath)) {
            $this->setStatus(404);
            $this->sendHeaders();
            echo 'File not found';
            exit;
        }

        // Validate path is within allowed directories
        $allowed = false;
        foreach (self::$downloadBasePaths as $basePath) {
            // Use realpath() comparison to handle case-insensitive filesystems
            // (e.g., macOS HFS+, Windows NTFS)
            // Both paths are already resolved via realpath()
            $basePathReal = realpath($basePath);
            if ($basePathReal === false) {
                continue;
            }

            // Use DIRECTORY_SEPARATOR to prevent /tmp matching /tmp_secret
            // Compare using binary-safe comparison after realpath normalization
            if (str_starts_with($realPath, $basePathReal . DIRECTORY_SEPARATOR)
                || $realPath === $basePathReal) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $this->setStatus(403);
            $this->sendHeaders();
            echo 'Access denied';
            exit;
        }

        // Sanitize filename for Content-Disposition header (RFC 5987)
        $name ??= basename($realPath);
        $name = preg_replace('/[^\x20-\x7E]/', '', $name) ?? 'download'; // ASCII only
        $name = str_replace(['"', '\\', '/'], '', $name); // Remove dangerous chars

        $mime = mime_content_type($realPath) ?: 'application/octet-stream';

        $this->header('Content-Type', $mime);
        // Use both filename and filename* for compatibility
        $encodedName = rawurlencode($name);
        $this->header('Content-Disposition', "attachment; filename=\"{$name}\"; filename*=UTF-8''{$encodedName}");
        $this->header('Content-Length', (string) filesize($realPath));

        $this->sendHeaders();
        readfile($realPath);
        exit;
    }

    public function cache(int $seconds, bool $public = true): self
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('Cache seconds must be non-negative');
        }

        $directive = $public ? 'public' : 'private';
        $this->header('Cache-Control', "$directive, max-age=$seconds");
        $this->header('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
        return $this;
    }

    public function noCache(): self
    {
        $this->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->header('Pragma', 'no-cache');
        $this->header('Expires', '0');
        return $this;
    }

    /**
     * Set CORS headers.
     *
     * @param string $origin The allowed origin (required - no default wildcard for security)
     * @param array $methods Allowed HTTP methods
     * @param array $headers Allowed request headers
     * @param bool $credentials Whether to allow credentials (cookies, auth headers)
     */
    public function cors(
        string $origin,
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        bool $credentials = false,
    ): self {
        // Warn against wildcard with credentials (browser will reject this anyway)
        if ($origin === '*' && $credentials) {
            throw new \InvalidArgumentException(
                'CORS: Cannot use wildcard origin with credentials. Specify an explicit origin.'
            );
        }

        $this->header('Access-Control-Allow-Origin', $origin);
        $this->header('Access-Control-Allow-Methods', implode(', ', $methods));
        $this->header('Access-Control-Allow-Headers', implode(', ', $headers));

        if ($credentials) {
            $this->header('Access-Control-Allow-Credentials', 'true');
        }

        return $this;
    }

    /**
     * Add common security headers.
     *
     * @param bool $hsts Enable HSTS (HTTP Strict Transport Security). Only enable in production with HTTPS.
     * @param int $hstsMaxAge HSTS max-age in seconds (default: 1 year)
     * @param bool $hstsIncludeSubdomains Include subdomains in HSTS policy
     * @param bool $hstsPreload Allow inclusion in browser HSTS preload lists
     */
    public function securityHeaders(
        bool $hsts = false,
        int $hstsMaxAge = 31536000,
        bool $hstsIncludeSubdomains = true,
        bool $hstsPreload = false,
    ): self {
        $this->header('X-Content-Type-Options', 'nosniff');
        $this->header('X-Frame-Options', 'SAMEORIGIN');
        $this->header('X-XSS-Protection', '1; mode=block');
        $this->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS - Only enable in production with proper HTTPS setup
        // WARNING: Once enabled, browsers will refuse HTTP for the duration of max-age
        if ($hsts) {
            $hstsValue = "max-age={$hstsMaxAge}";
            if ($hstsIncludeSubdomains) {
                $hstsValue .= '; includeSubDomains';
            }
            if ($hstsPreload) {
                $hstsValue .= '; preload';
            }
            $this->header('Strict-Transport-Security', $hstsValue);
        }

        return $this;
    }

    public function csp(string $policy): self
    {
        $this->header('Content-Security-Policy', $policy);
        return $this;
    }

    private function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $statusText = self::STATUS_TEXTS[$this->statusCode] ?? 'Unknown';
        header("HTTP/1.1 {$this->statusCode} {$statusText}");

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Send response and terminate (traditional PHP mode).
     */
    public function send(?string $content = null): never
    {
        $this->emit($content);
        exit;
    }

    /**
     * Emit response without terminating (worker mode compatible).
     *
     * Use this in environments like FrankenPHP worker mode, RoadRunner,
     * or any persistent PHP runtime where exit() would kill the worker.
     */
    public function emit(?string $content = null): void
    {
        if ($this->sent) {
            return;
        }

        $this->sent = true;
        $body = $content ?? $this->body;

        // Set Content-Length so clients know when response is complete
        if (!isset($this->headers['Content-Length'])) {
            $this->headers['Content-Length'] = (string) strlen($body);
        }

        // Set Content-Type if not already set
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'text/html; charset=UTF-8';
        }

        $this->sendHeaders();
        echo $body;

        // Flush output buffers
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
