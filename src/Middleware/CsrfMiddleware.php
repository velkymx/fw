<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const array EXCLUDED_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        if (in_array($request->method, self::EXCLUDED_METHODS, true)) {
            return $next($request);
        }

        $token = $request->post('_csrf_token')
            ?? $request->header('X-CSRF-TOKEN');

        if (!$this->app->csrf->validate($token)) {
            $response = $this->app->response
                ->setStatus(403)
                ->securityHeaders();

            // Return appropriate response format based on request type
            if ($request->wantsJson()) {
                return $response
                    ->contentType('application/json')
                    ->setBody(json_encode([
                        'error' => 'CSRF token mismatch',
                        'message' => 'The CSRF token is missing or invalid.',
                    ], JSON_THROW_ON_ERROR));
            }

            return $response
                ->contentType('text/plain')
                ->setBody('CSRF token mismatch');
        }

        return $next($request);
    }
}
