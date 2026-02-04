<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Auth\TokenGuard;
use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;
use Fw\Http\ApiResponse;

/**
 * API authentication middleware.
 *
 * Validates Bearer tokens from the Authorization header.
 * Returns JSON 401 responses following RFC 9457 Problem Details.
 * Does not use sessions or redirects.
 */
final class ApiAuthMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        // Check for Bearer token
        $token = $request->bearerToken();

        if ($token === null) {
            return $this->unauthorized('No API token provided');
        }

        // Attempt token authentication
        $user = TokenGuard::authenticate($request);

        if ($user === null) {
            return $this->unauthorized('Invalid or expired API token');
        }

        return $next($request);
    }

    /**
     * Return a 401 Unauthorized JSON response.
     */
    private function unauthorized(string $detail): array
    {
        $api = new ApiResponse($this->app->response);

        $response = $api->unauthorized($detail, $this->app->request->uri);

        // Set status and headers
        $this->app->response->setStatus($api->getStatus());

        foreach ($api->getHeaders() as $name => $value) {
            $this->app->response->header($name, $value);
        }

        return $response;
    }
}
