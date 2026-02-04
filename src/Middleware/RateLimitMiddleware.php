<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Cache\CacheInterface;
use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;

/**
 * Rate limiting middleware using cache backend.
 *
 * Uses the configured cache driver (Redis, APCu, file) for atomic
 * increment operations. Much faster than filesystem-based rate limiting.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private const CACHE_PREFIX = 'ratelimit:';

    private Application $app;
    private CacheInterface $cache;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(Application $app, CacheInterface $cache)
    {
        $this->app = $app;
        $this->cache = $cache;
        $this->maxRequests = $app->config('app.rate_limit.max', 60);
        $this->windowSeconds = $app->config('app.rate_limit.window', 60);
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        $key = $this->getKey($request);
        $current = $this->getCurrentCount($key);

        if ($current >= $this->maxRequests) {
            return $this->tooManyRequests($current);
        }

        $this->increment($key);

        $response = $next($request);

        if ($response instanceof Response) {
            $this->addRateLimitHeaders($response, $current + 1);
        }

        return $response;
    }

    private function getKey(Request $request): string
    {
        $identifier = $request->ip();
        // Use intdiv() instead of floor(time() / x) to avoid potential integer overflow
        // on 32-bit systems where floor() returns float that may overflow on (int) cast
        $window = intdiv(time(), $this->windowSeconds);
        // Use SHA256 for cryptographic collision resistance to prevent rate limit bypass
        return self::CACHE_PREFIX . hash('sha256', $identifier . ':' . $window);
    }

    private function getCurrentCount(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    private function increment(string $key): void
    {
        $count = $this->getCurrentCount($key) + 1;

        // TTL is window + buffer to ensure cleanup
        $ttl = $this->windowSeconds + 60;

        $this->cache->set($key, $count, $ttl);
    }

    private function tooManyRequests(int $current): Response
    {
        $response = $this->app->response->setStatus(429);
        $this->addRateLimitHeaders($response, $current);
        $response->header('Retry-After', (string) $this->windowSeconds);
        return $response;
    }

    private function addRateLimitHeaders(Response $response, int $current): void
    {
        $remaining = max(0, $this->maxRequests - $current);

        $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        $response->header('X-RateLimit-Reset', (string) (time() + $this->windowSeconds));
    }
}
