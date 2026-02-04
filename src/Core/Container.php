<?php

declare(strict_types=1);

namespace Fw\Core;

use Closure;
use InvalidArgumentException;
use Fw\Support\Option;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Simple dependency injection container.
 *
 * Features:
 * - Singleton and factory bindings
 * - Auto-wiring via reflection
 * - Interface to implementation bindings
 * - Contextual binding
 *
 * @example
 *     $container = new Container();
 *
 *     // Bind interface to implementation
 *     $container->bind(UserRepository::class, DatabaseUserRepository::class);
 *
 *     // Bind singleton
 *     $container->singleton(EventDispatcher::class, fn($c) => new EventDispatcher($c->get(...)));
 *
 *     // Resolve with auto-wiring
 *     $handler = $container->get(CreateUserHandler::class);
 */
final class Container
{
    private static ?self $instance = null;

    /**
     * Guard flag for fiber-safe singleton initialization.
     */
    private static bool $initializing = false;

    /** @var array<class-string, Closure|class-string> */
    private array $bindings = [];

    /** @var array<class-string, object> */
    private array $instances = [];

    /** @var array<class-string, bool> */
    private array $singletons = [];

    /**
     * Cached reflection metadata for constructor parameters.
     * Structure: [class => [['name' => name, 'type' => type|null, 'builtin' => bool, 'nullable' => bool, 'hasDefault' => bool, 'default' => value], ...]]
     * @var array<class-string, array<int, array{name: string, type: ?string, builtin: bool, nullable: bool, hasDefault: bool, default: mixed}>|null>
     */
    private array $reflectionCache = [];

    /**
     * Classes currently being reflected (Fiber concurrency guard).
     * @var array<class-string, int>
     */
    private array $reflectionInitializing = [];

    /**
     * Get the singleton container instance.
     *
     * Fiber-safe: uses a guard flag to prevent multiple instances
     * being created if multiple fibers call getInstance() simultaneously
     * during initial bootstrap.
     *
     * Note: This is the bootstrap entry point. For application code,
     * prefer constructor injection over calling getInstance().
     */
    public static function getInstance(): self
    {
        // Fast path: already initialized
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Fiber-safe initialization guard
        if (self::$initializing) {
            // Another fiber is initializing - spin wait
            $spins = 0;
            while (self::$initializing && self::$instance === null && $spins < 1000) {
                if (\Fiber::getCurrent() !== null) {
                    \Fiber::suspend();
                } else {
                    usleep(100);
                }
                $spins++;
            }

            // After waiting, instance should be ready
            if (self::$instance !== null) {
                return self::$instance;
            }
        }

        // Claim initialization
        self::$initializing = true;

        try {
            // Double-check after claiming
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        } finally {
            self::$initializing = false;
        }
    }

    /**
     * Set the singleton instance.
     */
    public static function setInstance(?self $container): void
    {
        self::$instance = $container;
    }

    /**
     * Reset the singleton instance (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Bind an abstract to a concrete implementation.
     *
     * @param class-string $abstract
     * @param Closure|class-string|null $concrete
     */
    public function bind(string $abstract, Closure|string|null $concrete = null): self
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
        return $this;
    }

    /**
     * Bind a singleton (only instantiated once).
     *
     * @param class-string $abstract
     * @param Closure|class-string|null $concrete
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): self
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
        return $this;
    }

    /**
     * Register an existing instance.
     *
     * @param class-string $abstract
     */
    public function instance(string $abstract, object $instance): self
    {
        $this->instances[$abstract] = $instance;
        return $this;
    }

    /**
     * Check if a binding exists.
     *
     * @param class-string $abstract
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Resolve an instance from the container.
     *
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    public function get(string $abstract): object
    {
        // Return existing instance if available
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Resolve the concrete binding or use the abstract itself
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // If it's a closure, execute it to get the instance
        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } else {
            // It's a string, attempt to build it (auto-wire)
            // Here, $concrete should always be a concrete class name or instantiable.
            if (!class_exists($concrete)) {
                throw new InvalidArgumentException("Class {$concrete} does not exist");
            }
            // Use reflection to build the concrete class
            $object = $this->buildClass($concrete);
        }

        // Cache if singleton
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Try to resolve, returning Option.
     *
     * @template T
     * @param class-string<T> $abstract
     * @return Option<T>
     */
    public function tryGet(string $abstract): Option
    {
        try {
            return Option::some($this->get($abstract));
        } catch (\Throwable) {
            return Option::none();
        }
    }

    /**
     * Build a concrete class or execute a closure.
     * This method acts as a dispatcher for internal building logic.
     *
     * @param Closure|class-string $concrete
     */
    private function build(Closure|string $concrete): object
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }
        // If it's a string, it must be a concrete class at this point.
        return $this->buildClass($concrete);
    }

    /**
     * Build a concrete class with auto-wiring via reflection.
     *
     * Uses cached reflection metadata when available to avoid
     * repeated reflection overhead.
     *
     * @param class-string $concrete
     */
    private function buildClass(string $concrete): object
    {
        // Get cached parameter info or build it
        $params = $this->getReflectionCache($concrete);

        // No constructor parameters
        if ($params === null) {
            return new $concrete();
        }

        $dependencies = [];

        foreach ($params as $param) {
            // No type hint
            if ($param['type'] === null) {
                if ($param['hasDefault']) {
                    $dependencies[] = $param['default'];
                    continue;
                }
                throw new InvalidArgumentException(
                    "Cannot resolve parameter \${$param['name']} in {$concrete}"
                );
            }

            // Built-in types
            if ($param['builtin']) {
                if ($param['hasDefault']) {
                    $dependencies[] = $param['default'];
                    continue;
                }
                throw new InvalidArgumentException(
                    "Cannot resolve built-in type {$param['type']} for \${$param['name']} in {$concrete}"
                );
            }

            // Resolve from container
            try {
                $dependencies[] = $this->get($param['type']);
            } catch (\Throwable $e) {
                if ($param['hasDefault']) {
                    $dependencies[] = $param['default'];
                } elseif ($param['nullable']) {
                    $dependencies[] = null;
                } else {
                    throw $e;
                }
            }
        }

        return new $concrete(...$dependencies);
    }

    /**
     * Get cached reflection data for a class, building if necessary.
     *
     * Thread-safe for Fiber concurrency using atomic initialization pattern.
     * Reflection is idempotent, so concurrent initialization is safe.
     *
     * @param class-string $concrete
     * @return array<int, array{name: string, type: ?string, builtin: bool, nullable: bool, hasDefault: bool, default: mixed}>|null
     */
    private function getReflectionCache(string $concrete): ?array
    {
        // Fast path: already cached
        if (array_key_exists($concrete, $this->reflectionCache)) {
            return $this->reflectionCache[$concrete];
        }

        // Fiber concurrency guard - use unique token to track ownership
        $initToken = spl_object_id(new \stdClass());
        $spinCount = 0;
        $maxSpins = 1000;

        while (true) {
            // Check if now cached (another Fiber may have finished)
            if (array_key_exists($concrete, $this->reflectionCache)) {
                return $this->reflectionCache[$concrete];
            }

            // Try to claim initialization
            if (!isset($this->reflectionInitializing[$concrete])) {
                $this->reflectionInitializing[$concrete] = $initToken;
                break;
            }

            // Another Fiber is initializing - wait
            if (++$spinCount > $maxSpins) {
                // Timeout: force proceed (reflection is idempotent)
                $this->reflectionInitializing[$concrete] = $initToken;
                break;
            }

            // Yield to other Fibers
            if (\Fiber::getCurrent() !== null) {
                \Fiber::suspend();
            } else {
                usleep(100);
            }
        }

        try {
            // Double-check cache after acquiring "lock"
            if (array_key_exists($concrete, $this->reflectionCache)) {
                return $this->reflectionCache[$concrete];
            }

            $reflector = new ReflectionClass($concrete);

            if (!$reflector->isInstantiable()) {
                throw new InvalidArgumentException("Class {$concrete} is not instantiable");
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                $this->reflectionCache[$concrete] = null;
                return null;
            }

            $params = [];

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                $paramInfo = [
                    'name' => $parameter->getName(),
                    'type' => null,
                    'builtin' => false,
                    'nullable' => false,
                    'hasDefault' => $parameter->isDefaultValueAvailable(),
                    'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                ];

                if ($type instanceof ReflectionNamedType) {
                    $paramInfo['type'] = $type->getName();
                    $paramInfo['builtin'] = $type->isBuiltin();
                    $paramInfo['nullable'] = $type->allowsNull();
                }

                $params[] = $paramInfo;
            }

            $this->reflectionCache[$concrete] = $params;
            return $params;
        } finally {
            // Only clear if we own the lock
            if (($this->reflectionInitializing[$concrete] ?? null) === $initToken) {
                unset($this->reflectionInitializing[$concrete]);
            }
        }
    }

    /**
     * Call a method with auto-wired parameters.
     *
     * @param array<string, mixed> $parameters Named parameters to override
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;

            if (is_string($class)) {
                $class = $this->get($class);
            }

            $reflector = new \ReflectionMethod($class, $method);
            $callback = [$class, $method];
        } else {
            $reflector = new \ReflectionFunction($callback);
        }

        $dependencies = [];

        foreach ($reflector->getParameters() as $parameter) {
            $name = $parameter->getName();

            // Check for named parameter
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($type?->allowsNull()) {
                $dependencies[] = null;
            } else {
                throw new InvalidArgumentException(
                    "Cannot resolve parameter \${$name}"
                );
            }
        }

        return $callback(...$dependencies);
    }

    /**
     * Create a new instance with auto-wiring (ignores singletons).
     *
     * @template T
     * @param class-string<T> $abstract
     * @param array<string, mixed> $parameters
     * @return T
     */
    public function make(string $abstract, array $parameters = []): object
    {
        $concrete = $this->bindings[$abstract] ?? $abstract;

        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        if (!class_exists($concrete)) {
            throw new InvalidArgumentException("Class {$concrete} does not exist");
        }

        $reflector = new ReflectionClass($concrete);
        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($type?->allowsNull()) {
                $dependencies[] = null;
            } else {
                throw new InvalidArgumentException(
                    "Cannot resolve parameter \${$name} in {$concrete}"
                );
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Flush all instances (useful for testing).
     */
    public function flush(): void
    {
        $this->instances = [];
    }

    /**
     * Clear the reflection cache.
     *
     * Call between requests in worker mode to prevent stale cache
     * or memory accumulation from dynamic class loading.
     */
    public function clearReflectionCache(): void
    {
        $this->reflectionCache = [];
        $this->reflectionInitializing = [];
    }

    /**
     * Full reset for worker mode - clears instances and reflection cache.
     */
    public function resetForWorker(): void
    {
        $this->flush();
        $this->clearReflectionCache();
    }

    /**
     * Get the resolver function for use with buses.
     */
    public function resolver(): Closure
    {
        return fn(string $class) => $this->get($class);
    }
}
