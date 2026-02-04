<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    private Application $app;
    private array $config;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
            'exposed_headers' => [],
            'max_age' => 86400,
            'supports_credentials' => false,
        ];
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        $origin = $request->header('Origin', '');

        if ($request->method === 'OPTIONS') {
            return $this->handlePreflight($origin);
        }

        $response = $next($request);

        return $this->addCorsHeaders($response, $origin);
    }

    private function handlePreflight(string $origin): Response
    {
        $response = $this->app->response->setStatus(204);

        $this->setCorsHeaders($response, $origin);

        return $response;
    }

    private function addCorsHeaders(Response|string|array $response, string $origin): Response|string|array
    {
        if ($response instanceof Response) {
            $this->setCorsHeaders($response, $origin);
        }

        return $response;
    }

    private function setCorsHeaders(Response $response, string $origin): void
    {
        // Validate configuration: wildcard origin cannot be used with credentials
        // Browsers will reject this combination, so fail early with a clear error
        if (in_array('*', $this->config['allowed_origins'], true) && $this->config['supports_credentials']) {
            throw new \InvalidArgumentException(
                'CORS misconfiguration: Cannot use wildcard origin (*) with credentials. ' .
                'Either specify explicit allowed origins or disable supports_credentials.'
            );
        }

        if ($this->isOriginAllowed($origin)) {
            $allowedOrigin = in_array('*', $this->config['allowed_origins'], true)
                ? '*'
                : $origin;

            $response->header('Access-Control-Allow-Origin', $allowedOrigin);
        }

        $response->header(
            'Access-Control-Allow-Methods',
            implode(', ', $this->config['allowed_methods'])
        );

        $response->header(
            'Access-Control-Allow-Headers',
            implode(', ', $this->config['allowed_headers'])
        );

        if (!empty($this->config['exposed_headers'])) {
            $response->header(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers'])
            );
        }

        $response->header('Access-Control-Max-Age', (string) $this->config['max_age']);

        if ($this->config['supports_credentials']) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->config['allowed_origins'], true)) {
            return true;
        }

        return in_array($origin, $this->config['allowed_origins'], true);
    }
}
