<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Auth\Auth;
use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;
use Fw\Http\ApiResponse;

/**
 * SPA cookie-based authentication middleware.
 *
 * Uses existing session authentication with CSRF protection.
 * Validates CSRF token from X-XSRF-TOKEN header.
 * Enforces same-origin via Referer/Origin header check.
 */
final class SpaAuthMiddleware implements MiddlewareInterface
{
    private Application $app;
    private array $config;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $configPath = dirname(__DIR__, 2) . '/config/api.php';
        $this->config = file_exists($configPath) ? require $configPath : [];
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        // Validate origin/referer for same-origin enforcement
        if (!$this->validateOrigin($request)) {
            return $this->forbidden('Cross-origin requests not allowed');
        }

        // Check session authentication
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        // Validate CSRF token for non-GET requests
        if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            if (!$this->validateCsrf($request)) {
                return $this->forbidden('CSRF token mismatch');
            }
        }

        return $next($request);
    }

    /**
     * Validate the request origin against the whitelist.
     */
    private function validateOrigin(Request $request): bool
    {
        $allowedDomains = $this->config['spa_domains'] ?? [];

        // If no domains configured, only allow in development mode
        if (empty($allowedDomains)) {
            $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
            $debug = filter_var(
                $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false,
                FILTER_VALIDATE_BOOLEAN
            );

            // Only allow open access in development
            if ($env === 'local' || $env === 'development' || $debug === true) {
                error_log(
                    'SpaAuthMiddleware: No spa_domains configured, allowing all origins (development mode). ' .
                    'Configure spa_domains in production.'
                );
                return true;
            }

            // In production without config, reject all cross-origin requests
            error_log(
                'SpaAuthMiddleware: No spa_domains configured in production. ' .
                'Set API_SPA_DOMAINS environment variable or configure spa_domains in config/api.php'
            );
            return false;
        }

        // Check Origin header first, then Referer
        $origin = $request->header('origin');
        $referer = $request->header('referer');

        $checkUrl = $origin ?? $referer;

        if ($checkUrl === null) {
            // No origin info - could be same-origin or direct request
            // Allow for now, CSRF check will catch issues
            return true;
        }

        $parsed = parse_url($checkUrl);
        $host = $parsed['host'] ?? null;

        if ($host === null) {
            return false;
        }

        return in_array($host, $allowedDomains, true);
    }

    /**
     * Validate CSRF token from X-XSRF-TOKEN header.
     */
    private function validateCsrf(Request $request): bool
    {
        // Get CSRF token from header
        $token = $request->header('x-xsrf-token')
            ?? $request->header('x-csrf-token');

        if ($token === null) {
            return false;
        }

        return $this->app->csrf->validate($token);
    }

    /**
     * Return a 401 Unauthorized JSON response.
     */
    private function unauthorized(string $detail): array
    {
        $api = new ApiResponse($this->app->response);

        $response = $api->unauthorized($detail, $this->app->request->uri);

        $this->app->response->setStatus($api->getStatus());

        foreach ($api->getHeaders() as $name => $value) {
            $this->app->response->header($name, $value);
        }

        return $response;
    }

    /**
     * Return a 403 Forbidden JSON response.
     */
    private function forbidden(string $detail): array
    {
        $api = new ApiResponse($this->app->response);

        $response = $api->forbidden($detail, $this->app->request->uri);

        $this->app->response->setStatus($api->getStatus());

        foreach ($api->getHeaders() as $name => $value) {
            $this->app->response->header($name, $value);
        }

        return $response;
    }
}
