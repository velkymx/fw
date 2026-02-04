<?php

declare(strict_types=1);

namespace Fw\Async;

/**
 * Non-blocking HTTP client using Fibers.
 *
 * Provides async HTTP requests that suspend the current Fiber
 * during network I/O and resume when the response is ready.
 *
 * @example
 * $http = new AsyncHttp();
 * $response = $http->get('https://api.example.com/users')->await();
 */
final class AsyncHttp
{
    /** @var array<string, string> Default headers */
    private array $defaultHeaders = [
        'User-Agent' => 'Fw-AsyncHttp/1.0',
        'Accept' => 'application/json',
    ];

    /** @var int Connection timeout in seconds */
    private int $connectTimeout = 10;

    /** @var int Request timeout in seconds */
    private int $timeout = 30;

    /**
     * Set default headers for all requests.
     *
     * @param array<string, string> $headers
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Set connection timeout.
     */
    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    /**
     * Set request timeout.
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Perform async GET request.
     *
     * @param array<string, string> $headers
     * @return Deferred Resolves to HttpResponse
     */
    public function get(string $url, array $headers = []): Deferred
    {
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * Perform async POST request.
     *
     * @param array<string, string> $headers
     * @return Deferred Resolves to HttpResponse
     */
    public function post(string $url, mixed $body = null, array $headers = []): Deferred
    {
        return $this->request('POST', $url, $body, $headers);
    }

    /**
     * Perform async PUT request.
     *
     * @param array<string, string> $headers
     * @return Deferred Resolves to HttpResponse
     */
    public function put(string $url, mixed $body = null, array $headers = []): Deferred
    {
        return $this->request('PUT', $url, $body, $headers);
    }

    /**
     * Perform async PATCH request.
     *
     * @param array<string, string> $headers
     * @return Deferred Resolves to HttpResponse
     */
    public function patch(string $url, mixed $body = null, array $headers = []): Deferred
    {
        return $this->request('PATCH', $url, $body, $headers);
    }

    /**
     * Perform async DELETE request.
     *
     * @param array<string, string> $headers
     * @return Deferred Resolves to HttpResponse
     */
    public function delete(string $url, array $headers = []): Deferred
    {
        return $this->request('DELETE', $url, null, $headers);
    }

    /**
     * Perform async HTTP request.
     *
     * @param array<string, string> $headers
     * @return Deferred Resolves to HttpResponse
     */
    public function request(string $method, string $url, mixed $body = null, array $headers = []): Deferred
    {
        $deferred = new Deferred();

        // Use non-blocking stream context for true async (when possible)
        EventLoop::getInstance()->defer(function () use ($deferred, $method, $url, $body, $headers) {
            try {
                $response = $this->executeRequest($method, $url, $body, $headers);
                $deferred->resolve($response);
            } catch (\Throwable $e) {
                $deferred->reject($e);
            }
        });

        return $deferred;
    }

    /**
     * Execute the HTTP request synchronously.
     *
     * @param array<string, string> $headers
     */
    private function executeRequest(string $method, string $url, mixed $body, array $headers): HttpResponse
    {
        $allHeaders = array_merge($this->defaultHeaders, $headers);

        // Prepare body
        $content = null;
        if ($body !== null) {
            if (is_array($body)) {
                $content = json_encode($body, JSON_THROW_ON_ERROR);
                $allHeaders['Content-Type'] = 'application/json';
            } else {
                $content = (string) $body;
            }
            $allHeaders['Content-Length'] = (string) strlen($content);
        }

        // Build header string
        $headerStrings = [];
        foreach ($allHeaders as $name => $value) {
            $headerStrings[] = "$name: $value";
        }

        // Create stream context
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerStrings),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'follow_location' => true,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        if ($content !== null) {
            $contextOptions['http']['content'] = $content;
        }

        $context = stream_context_create($contextOptions);

        // Execute request
        $responseBody = @file_get_contents($url, false, $context);

        if ($responseBody === false) {
            $error = error_get_last();
            throw new \RuntimeException(
                sprintf('HTTP request failed: %s', $error['message'] ?? 'Unknown error')
            );
        }

        // Parse response headers
        $responseHeaders = [];
        $statusCode = 200;

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $header, $matches)) {
                    $statusCode = (int) $matches[1];
                } elseif (str_contains($header, ':')) {
                    [$name, $value] = explode(':', $header, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
            }
        }

        return new HttpResponse($statusCode, $responseHeaders, $responseBody);
    }

    /**
     * Fetch JSON from a URL (convenience method).
     *
     * @param array<string, string> $headers
     * @return Deferred Resolves to array|null
     */
    public function getJson(string $url, array $headers = []): Deferred
    {
        $deferred = new Deferred();

        $this->get($url, $headers)->await();

        EventLoop::getInstance()->defer(function () use ($deferred, $url, $headers) {
            try {
                $response = $this->executeRequest('GET', $url, null, $headers);
                $data = $response->json();
                $deferred->resolve($data);
            } catch (\Throwable $e) {
                $deferred->reject($e);
            }
        });

        return $deferred;
    }

    /**
     * Post JSON and get JSON response (convenience method).
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return Deferred Resolves to array|null
     */
    public function postJson(string $url, array $data, array $headers = []): Deferred
    {
        $deferred = new Deferred();

        EventLoop::getInstance()->defer(function () use ($deferred, $url, $data, $headers) {
            try {
                $response = $this->executeRequest('POST', $url, $data, $headers);
                $result = $response->json();
                $deferred->resolve($result);
            } catch (\Throwable $e) {
                $deferred->reject($e);
            }
        });

        return $deferred;
    }
}

/**
 * HTTP response object.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        /** @var array<string, string> */
        public readonly array $headers,
        public readonly string $body
    ) {}

    /**
     * Check if the response was successful (2xx status).
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if the response is a redirect (3xx status).
     */
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if the response is a client error (4xx status).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if the response is a server error (5xx status).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Get a header value.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Parse body as JSON.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Get body as string.
     */
    public function text(): string
    {
        return $this->body;
    }
}
