<?php

declare(strict_types=1);

namespace Fw\Core;

use Fw\Lifecycle\Component;

/**
 * Represents a matched route with handler and parameters.
 *
 * This is returned by Router::dispatch() on successful route match.
 */
final readonly class RouteMatch
{
    /**
     * @param callable|array|class-string<Component> $handler Route handler
     * @param array<string, mixed> $params Captured route parameters
     * @param array<string|callable> $middleware Route middleware stack
     */
    public function __construct(
        public mixed $handler,
        public array $params = [],
        public array $middleware = [],
    ) {}

    /**
     * Check if handler is a Component class.
     */
    public function isComponent(): bool
    {
        return is_string($this->handler)
            && class_exists($this->handler)
            && is_subclass_of($this->handler, Component::class);
    }

    /**
     * Check if handler is a controller array [Controller::class, 'method'].
     */
    public function isController(): bool
    {
        return is_array($this->handler) && count($this->handler) === 2;
    }

    /**
     * Check if handler is a callable (closure or invokable).
     */
    public function isCallable(): bool
    {
        return is_callable($this->handler);
    }
}
