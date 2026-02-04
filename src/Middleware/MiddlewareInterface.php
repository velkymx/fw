<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Core\Request;
use Fw\Core\Response;

interface MiddlewareInterface
{
    /**
     * Process the request through this middleware.
     *
     * @param Request $request The incoming request
     * @param callable $next The next middleware or handler in the pipeline
     * @return Response|string|array The response
     */
    public function handle(Request $request, callable $next): Response|string|array;
}
