<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Core\Request;
use Fw\Core\Response;

final class CallableMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function handle(Request $request, callable $next): Response|string|array
    {
        return ($this->callback)($request, $next);
    }
}
