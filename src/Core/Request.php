<?php

declare(strict_types=1);

namespace Fw\Core;

use Fw\Security\Sanitizer;

final class Request
{
    public private(set) string $method;
    public private(set) string $uri;
    public private(set) string $fullUri;

    private array $query;
    private array $post;
    private array $server;
    private array $files;
    private array $headers;
    private ?string $rawBody = null;

    /**
     * Maximum allowed request body size in bytes (default 10MB).
     */
    private static int $maxBodySize = 10 * 1024 * 1024;

    /**
     * Trusted proxy IP addresses or CIDR ranges.
     * Only trust X-Forwarded-* headers from these sources.
     * @var list<string>
     */
    private static array $trustedProxies = [];

    public function __construct()
    {
        $this->fullUri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->uri = $this->parseUri($this->fullUri);
        $this->query = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
        $this->headers = $this->parseHeaders();

        // Support method spoofing via _method field (for PUT, PATCH, DELETE from forms)
        $this->method = $this->resolveMethod();
    }

    /**
     * Resolve the actual HTTP method, supporting _method override.
     */
    private function resolveMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Only allow method spoofing for POST requests
        if ($method === 'POST' && isset($this->post['_method'])) {
            $spoofed = strtoupper($this->post['_method']);

            // Only allow valid spoofable methods
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    private function parseUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/');
    }

    private function parseHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }

        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['content-type'] = $this->server['CONTENT_TYPE'];
        }

        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $this->server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->query[$key]) || isset($this->post[$key]);
    }

    public function query(): array
    {
        return $this->query;
    }

    public function postData(): array
    {
        return $this->post;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get the client IP address.
     *
     * Only trusts X-Forwarded-For/X-Client-IP headers when the request
     * comes from a configured trusted proxy.
     */
    public function ip(): string
    {
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        // Only trust forwarded headers if request is from a trusted proxy
        if (self::$trustedProxies !== [] && $this->isFromTrustedProxy($remoteAddr)) {
            // X-Forwarded-For can contain multiple IPs: client, proxy1, proxy2
            // The leftmost is the original client (if proxies are trusted)
            if (isset($this->server['HTTP_X_FORWARDED_FOR'])) {
                $forwardedFor = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
                $clientIp = trim($forwardedFor[0]);
                if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    return $clientIp;
                }
            }

            if (isset($this->server['HTTP_CLIENT_IP'])) {
                $clientIp = trim($this->server['HTTP_CLIENT_IP']);
                if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    return $clientIp;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Check if the remote address is a trusted proxy.
     */
    private function isFromTrustedProxy(string $ip): bool
    {
        foreach (self::$trustedProxies as $trusted) {
            // Exact match
            if ($trusted === $ip) {
                return true;
            }

            // CIDR notation check
            if (str_contains($trusted, '/') && $this->ipInCidr($ip, $trusted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     *
     * Supports both IPv4 and IPv6 addresses.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        // Detect IP version
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        // inet_pton returns false for invalid IPs
        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        // Ensure both are same IP version (same binary length)
        if (strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        // Calculate the mask
        $ipLength = strlen($ipBinary) * 8; // 32 for IPv4, 128 for IPv6

        // Validate bits is reasonable for this IP version
        if ($bits < 0 || $bits > $ipLength) {
            return false;
        }

        // Build binary mask
        $mask = str_repeat("\xff", (int) ($bits / 8));
        if ($bits % 8 !== 0) {
            $mask .= chr(0xff << (8 - ($bits % 8)));
        }
        $mask = str_pad($mask, strlen($ipBinary), "\x00");

        // Compare masked values
        return ($ipBinary & $mask) === ($subnetBinary & $mask);
    }

    /**
     * Configure trusted proxy addresses.
     *
     * @param list<string> $proxies IP addresses or CIDR ranges
     */
    public static function setTrustedProxies(array $proxies): void
    {
        self::$trustedProxies = $proxies;
    }

    /**
     * Configure maximum request body size.
     */
    public static function setMaxBodySize(int $bytes): void
    {
        self::$maxBodySize = $bytes;
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Check if the request was made over HTTPS.
     *
     * Only trusts X-Forwarded-Proto header from configured trusted proxies.
     */
    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') === 'on') {
            return true;
        }

        // Only trust forwarded proto from trusted proxies
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? '';
        if (self::$trustedProxies !== [] && $this->isFromTrustedProxy($remoteAddr)) {
            return ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        }

        return false;
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function isJson(): bool
    {
        return str_contains($this->header('content-type', ''), 'application/json');
    }

    /**
     * Check if the client expects a JSON response.
     *
     * Returns true if the Accept header contains 'application/json'.
     */
    public function expectsJson(): bool
    {
        $accept = $this->header('accept', '');

        return str_contains($accept, 'application/json')
            || str_contains($accept, '*/*');
    }

    /**
     * Alias for expectsJson() - check if client wants JSON response.
     *
     * Also returns true for AJAX requests and API paths.
     */
    public function wantsJson(): bool
    {
        if ($this->expectsJson()) {
            return true;
        }

        if ($this->isAjax()) {
            return true;
        }

        // Check if the request path starts with /api
        if (str_starts_with($this->uri, '/api')) {
            return true;
        }

        return false;
    }

    public function json(): ?array
    {
        if (!$this->isJson()) {
            return null;
        }

        $body = $this->rawBody();
        $data = json_decode($body, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Read timeout in seconds for body streaming.
     */
    private static int $readTimeout = 30;

    /**
     * Configure read timeout for body streaming.
     */
    public static function setReadTimeout(int $seconds): void
    {
        self::$readTimeout = $seconds;
    }

    /**
     * Get the raw request body.
     *
     * Uses streaming read with timeout to prevent:
     * - Memory exhaustion from oversized payloads
     * - Slowloris-style DoS attacks (very slow data transmission)
     *
     * @throws \RuntimeException If body exceeds maximum allowed size or timeout
     */
    public function rawBody(): string
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }

        // Check Content-Length header first (early rejection)
        // Use filter_var to safely parse the header and prevent integer overflow attacks
        $rawContentLength = $this->server['CONTENT_LENGTH'] ?? null;
        $contentLength = 0;

        if ($rawContentLength !== null) {
            $contentLength = filter_var(
                $rawContentLength,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX]]
            );

            // If filter_var returns false, treat as invalid/potentially malicious
            if ($contentLength === false) {
                throw new \RuntimeException(
                    'Invalid Content-Length header: must be a positive integer'
                );
            }

            if ($contentLength > self::$maxBodySize) {
                throw new \RuntimeException(
                    "Request body too large: {$contentLength} bytes exceeds maximum of " . self::$maxBodySize
                );
            }
        }

        // Stream-read with size validation and timeout to prevent DoS
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            return $this->rawBody = '';
        }

        // Set read timeout to prevent slowloris attacks
        stream_set_timeout($stream, self::$readTimeout);

        $body = '';
        $chunkSize = 8192; // 8KB chunks
        $startTime = microtime(true);
        $readTimeout = (float) self::$readTimeout;

        try {
            while (!feof($stream)) {
                // Check wall-clock timeout BEFORE blocking read
                // Use microtime for sub-second precision
                $elapsed = microtime(true) - $startTime;
                if ($elapsed > $readTimeout) {
                    throw new \RuntimeException(
                        'Request body read timeout: exceeded ' . self::$readTimeout . ' seconds'
                    );
                }

                $chunk = fread($stream, $chunkSize);

                // Check for stream-level timeout
                $meta = stream_get_meta_data($stream);
                if ($meta['timed_out']) {
                    throw new \RuntimeException(
                        'Request body read timeout: client sending data too slowly'
                    );
                }

                if ($chunk === false) {
                    break;
                }

                $body .= $chunk;

                // Check size after each chunk to fail fast
                if (strlen($body) > self::$maxBodySize) {
                    throw new \RuntimeException(
                        'Request body too large: exceeds maximum of ' . self::$maxBodySize . ' bytes'
                    );
                }
            }
        } finally {
            fclose($stream);
        }

        return $this->rawBody = $body;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');

        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    public function sanitized(string $key, mixed $default = null): mixed
    {
        $value = $this->input($key, $default);

        if (is_string($value)) {
            return Sanitizer::html($value);
        }

        return $value;
    }
}
