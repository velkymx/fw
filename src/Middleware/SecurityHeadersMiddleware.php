<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        $response = $next($request);

        if ($response instanceof Response) {
            $this->applySecurityHeaders($response);
        }

        return $response;
    }

    private function applySecurityHeaders(Response $response): void
    {
        $response->header('X-Content-Type-Options', 'nosniff');

        $response->header('X-Frame-Options', 'SAMEORIGIN');

        $response->header('X-XSS-Protection', '1; mode=block');

        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($this->app->request->isSecure()) {
            $response->header(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        $csp = $this->app->config('app.csp');
        if ($csp !== null) {
            $response->header('Content-Security-Policy', $csp);
        }
    }
}
