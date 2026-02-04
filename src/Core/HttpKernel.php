<?php

declare(strict_types=1);

namespace Fw\Core;

use Fw\Async\EventLoop;
use Fw\Cache\CacheInterface;
use Fw\Events\EventDispatcher;
use Fw\Lifecycle\Component;
use Fw\Lifecycle\RequestFiber;
use Fw\Middleware\Pipeline;

/**
 * HTTP Kernel - handles the request/response lifecycle.
 *
 * Responsibilities:
 * - Route loading and dispatching
 * - Middleware pipeline execution
 * - Fiber-based async request handling
 * - Response caching for guests
 * - Output sending
 */
final class HttpKernel
{
    public function __construct(
        private Application $app,
        private Router $router,
        private Container $container,
        private EventDispatcher $events,
        private ErrorHandler $errorHandler,
        private Config $config,
    ) {}

    /**
     * Handle the incoming request.
     *
     * This is the main entry point for request processing.
     */
    public function handle(Request $request, Response $response): void
    {
        // Track if this is a cacheable guest request
        $cacheKey = null;

        // Early cache check - fastest path for cached guest responses
        if ($this->tryServeFromCache($request, $response, $cacheKey)) {
            return;
        }

        // Create request context
        $context = RequestContext::create($request);
        $this->container->instance(RequestContext::class, $context);

        try {
            // Load routes
            $this->loadRoutes();

            // Emit request received event
            $this->events->dispatch(new RequestReceived($request));

            // Dispatch route
            $routeResult = $this->router->dispatch($request->method, $request->uri);

            // Handle routing errors
            if ($routeResult->isErr()) {
                $this->errorHandler->handleRoutingError($routeResult->getError());
                return;
            }

            /** @var RouteMatch $match */
            $match = $routeResult->getValue();

            // Store RouteMatch in context for middleware access
            $context->setRouteMatch($match);

            // Build and execute middleware pipeline
            $output = $this->executePipeline($request, $match);

            // Emit response sending event
            $this->events->dispatch(new ResponseSending($response));

            // Cache the response for guests (if cacheable)
            if ($cacheKey !== null) {
                $content = null;
                if (is_string($output)) {
                    $content = $output;
                } elseif ($output instanceof Response) {
                    $content = $output->getBody();
                }
                if ($content !== null && $content !== '') {
                    $this->cacheGuestResponse($cacheKey, $content);
                }
            }

            // Send the output
            $this->sendOutput($output, $response);

        } catch (\Throwable $e) {
            $this->errorHandler->handleException($e, $request);
        } finally {
            RequestContext::clear();
        }
    }

    /**
     * Try to serve response from cache (early, before full request lifecycle).
     *
     * SECURITY: Only serves cached responses to users WITHOUT an active session.
     *
     * @param Request $request
     * @param Response $response
     * @param string|null &$cacheKey Output parameter - set to cache key if cacheable
     * @return bool True if response was served from cache
     */
    private function tryServeFromCache(Request $request, Response $response, ?string &$cacheKey = null): bool
    {
        $cacheKey = null;

        // Only cache GET requests
        if ($request->method !== 'GET') {
            header('X-Cache: SKIP-METHOD-' . $request->method);
            return false;
        }

        // SECURITY: Never serve cached content to users with active sessions
        if (isset($_COOKIE[session_name()])) {
            $this->app->initSession();
            header('X-Cache: SKIP-SESSION');
            return false;
        }

        $cache = $this->container->get(CacheInterface::class);
        // Use SHA256 for cryptographically collision-resistant cache keys
        // Prevents cache poisoning attacks via hash collisions
        // Normalize URI: lowercase, sorted query params to prevent cache fragmentation
        $normalizedUri = $this->normalizeCacheUri($request->uri);
        $key = 'page:guest:' . hash('sha256', $normalizedUri);

        $cached = $cache->get($key);

        if ($cached !== null) {
            header('X-Cache: HIT');
            $response->emit($cached);
            return true;
        }

        // Set cache key for storing response later
        $cacheKey = $key;
        header('X-Cache: MISS');
        return false;
    }

    /**
     * Cache a guest response for future requests.
     *
     * @param string $key Cache key
     * @param string $content Response content
     * @param int $ttl Time to live in seconds (default 60)
     */
    private function cacheGuestResponse(string $key, string $content, int $ttl = 60): void
    {
        $cache = $this->container->get(CacheInterface::class);
        $cache->set($key, $content, $ttl);
    }

    /**
     * Normalize URI for consistent cache keys.
     *
     * - Validates URI structure to prevent cache poisoning
     * - Lowercases the path
     * - Normalizes Unicode characters
     * - Sorts query parameters alphabetically
     * - Removes empty query parameters
     *
     * This prevents cache fragmentation from different query param orders
     * and cache poisoning via URI encoding tricks.
     */
    private function normalizeCacheUri(string $uri): string
    {
        // Reject URIs with suspicious patterns that could cause cache poisoning
        // - Protocol-relative URLs (//evil.com)
        // - URLs with embedded schemes in path (http://evil.com)
        // - Null bytes
        if (str_contains($uri, "\0") ||
            str_starts_with($uri, '//') ||
            preg_match('#^[a-z]+://#i', $uri)) {
            return '/invalid-uri-' . hash('sha256', $uri);
        }

        $parsed = parse_url($uri);

        // parse_url returns false on seriously malformed URIs
        if ($parsed === false) {
            return '/malformed-uri-' . hash('sha256', $uri);
        }

        $path = $parsed['path'] ?? '/';

        // Normalize path: lowercase and normalize Unicode if available
        $path = strtolower($path);

        // Normalize Unicode to NFC form to prevent café vs cafe%CC%81 attacks
        if (class_exists(\Normalizer::class) && !str_starts_with($path, '/invalid') && !str_starts_with($path, '/malformed')) {
            $normalized = \Normalizer::normalize($path, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $path = $normalized;
            }
        }

        // Decode percent-encoded characters for consistent comparison
        // Then re-encode to ensure consistent format
        $path = rawurlencode(rawurldecode($path));
        // Restore path separators
        $path = str_replace('%2F', '/', $path);

        // Normalize and sort query string
        $query = '';
        if (isset($parsed['query']) && $parsed['query'] !== '') {
            parse_str($parsed['query'], $params);

            // Remove empty values, null, and sort
            $params = array_filter($params, fn($v) => $v !== '' && $v !== null && !is_array($v));
            ksort($params);

            if ($params !== []) {
                $query = '?' . http_build_query($params);
            }
        }

        return $path . $query;
    }

    /**
     * Load routes from cache or configuration file.
     */
    private function loadRoutes(): void
    {
        // Try loading from cache first (much faster)
        $cacheFile = BASE_PATH . '/storage/cache/routes.php';
        $this->router->setCacheFile($cacheFile);

        if ($this->router->loadCache()) {
            return; // Routes loaded from cache
        }

        // Fall back to loading from routes.php
        $routesFile = BASE_PATH . '/config/routes.php';

        if (file_exists($routesFile)) {
            $routes = require $routesFile;
            if (is_callable($routes)) {
                $routes($this->router);
            }
        }
    }

    /**
     * Execute the middleware pipeline for a route match.
     */
    private function executePipeline(Request $request, RouteMatch $match): Response|StreamedResponse|string|array
    {
        // Build middleware stack
        $middleware = array_merge(
            $this->router->getGlobalMiddleware(),
            $this->flattenMiddleware($match->middleware)
        );

        // Resolve the handler
        $resolvedHandler = $this->resolveHandler($match->handler);

        // Destination function for middleware pipeline
        $destination = function (Request $request) use ($resolvedHandler, $match): Response|string|array {
            return $this->executeInFiber($resolvedHandler, $match->params);
        };

        // Run through middleware pipeline
        return (new Pipeline($this->app))
            ->through($middleware)
            ->then($destination, $request);
    }

    /**
     * Flatten middleware array, expanding groups into individual middleware.
     *
     * @param array<string|callable> $middleware
     * @return array<string|callable>
     */
    private function flattenMiddleware(array $middleware): array
    {
        $flattened = [];

        foreach ($middleware as $m) {
            $resolved = $this->router->resolveMiddleware($m);

            if (is_array($resolved)) {
                foreach ($resolved as $groupMiddleware) {
                    $flattened[] = $this->router->resolveMiddleware($groupMiddleware);
                }
            } else {
                $flattened[] = $resolved;
            }
        }

        return $flattened;
    }

    /**
     * Execute handler inside a Fiber with lifecycle support.
     */
    private function executeInFiber(Component|callable $handler, array $params): Response|StreamedResponse|string|array
    {
        $loop = $this->container->get(EventLoop::class);
        $request = $this->container->get(Request::class);

        // Create Fiber for this request
        $fiber = new RequestFiber($this->app, $request, $handler, $params);

        // Start the request Fiber
        $fiber->start();

        // Run event loop until request completes
        while (!$fiber->isCompleted()) {
            $loop->tick();

            // Prevent CPU spinning when waiting for async operations
            if (!$fiber->isCompleted()) {
                usleep(100);
            }
        }

        // Handle error if any
        if ($fiber->getError()) {
            throw $fiber->getError();
        }

        return $fiber->getOutput() ?? '';
    }

    /**
     * Resolve handler to Component or callable.
     */
    private function resolveHandler(mixed $handler): Component|callable
    {
        $request = $this->container->get(Request::class);

        // If it's a Component class name, instantiate it
        if (is_string($handler) && class_exists($handler) && is_subclass_of($handler, Component::class)) {
            return new $handler($this->app, $request);
        }

        // If it's a [Controller::class, 'method'] array, wrap in lifecycle
        if (is_array($handler)) {
            return $this->wrapControllerInLifecycle($handler);
        }

        // Callable - return as-is
        if (is_callable($handler)) {
            return $handler;
        }

        throw new \RuntimeException('Invalid route handler');
    }

    /**
     * Wrap traditional controllers in lifecycle for backwards compatibility.
     */
    private function wrapControllerInLifecycle(array $handler): callable
    {
        return function (Request $request, ...$params) use ($handler) {
            [$class, $method] = $handler;

            if (is_string($class)) {
                $class = new $class($this->app);
            }

            return $class->$method($request, ...$params);
        };
    }

    /**
     * Send the output to the client.
     */
    private function sendOutput(Response|StreamedResponse|string|array $output, Response $response): void
    {
        if ($output instanceof StreamedResponse) {
            // Streamed responses handle their own output
            $output->send();
        } elseif ($output instanceof Response) {
            $output->emit();
        } elseif (is_string($output)) {
            $response->emit($output);
        } elseif (is_array($output)) {
            $response->contentType('application/json')->emit(
                json_encode($output, JSON_THROW_ON_ERROR)
            );
        }
    }
}
