<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Auth\Auth;
use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        if (!Auth::check()) {
            if ($request->isAjax() || $request->isJson()) {
                return $this->app->response
                    ->setStatus(401)
                    ->securityHeaders();
            }

            // Validate and store intended URL to prevent open redirect
            $this->storeIntendedUrl($request->uri);

            $this->app->response->redirect('/login');
        }

        return $next($request);
    }

    /**
     * Store intended URL in session after validation.
     *
     * Only stores same-origin URLs to prevent open redirect attacks.
     */
    private function storeIntendedUrl(string $url): void
    {
        if ($this->isSafeRedirectUrl($url)) {
            $_SESSION['_intended_url'] = $url;
        }
    }

    /**
     * Check if a URL is safe for redirect (same-origin or relative path).
     */
    private function isSafeRedirectUrl(string $url): bool
    {
        // Empty URL is not safe
        if ($url === '') {
            return false;
        }

        // Protocol-relative URLs (//evil.com) are not safe
        if (str_starts_with($url, '//')) {
            return false;
        }

        // Relative paths starting with / are safe
        if (str_starts_with($url, '/')) {
            return true;
        }

        // Parse the URL
        $parsed = parse_url($url);

        // Relative URLs without scheme/host are safe
        if (!isset($parsed['scheme']) && !isset($parsed['host'])) {
            return true;
        }

        // If it has a host, it's external - not safe
        if (isset($parsed['host'])) {
            return false;
        }

        return true;
    }
}
