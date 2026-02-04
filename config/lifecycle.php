<?php

declare(strict_types=1);

use Fw\Core\Env;

/**
 * Lifecycle Configuration
 *
 * Settings for the Fiber-based request lifecycle system.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Concurrent Fibers
    |--------------------------------------------------------------------------
    |
    | The maximum number of Fibers that can run concurrently. In standard
    | PHP-FPM mode this is typically 1 per request. Higher values are useful
    | for async servers like RoadRunner or Swoole.
    |
    */
    'max_concurrent' => Env::int('LIFECYCLE_MAX_CONCURRENT', 100),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds that a request can take before timing out.
    | This includes all lifecycle phases: initialization, data fetching,
    | rendering, and response.
    |
    */
    'timeout' => Env::int('LIFECYCLE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Data Fetch Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds for the data fetching phase (fetch() hook).
    | This is separate from the overall request timeout to allow for
    | stricter control over async I/O operations.
    |
    */
    'fetch_timeout' => Env::int('LIFECYCLE_FETCH_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable detailed lifecycle logging. When enabled, each hook execution
    | will be logged with timing information. Useful for debugging but
    | adds overhead in production.
    |
    */
    'debug' => Env::bool('LIFECYCLE_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Event Loop Tick Interval
    |--------------------------------------------------------------------------
    |
    | The interval in microseconds between event loop ticks when waiting
    | for I/O. Lower values mean more responsive async operations but
    | higher CPU usage.
    |
    */
    'tick_interval' => Env::int('LIFECYCLE_TICK_INTERVAL', 10000),

    /*
    |--------------------------------------------------------------------------
    | Enable Async by Default
    |--------------------------------------------------------------------------
    |
    | Whether all requests should be processed through the Fiber-based
    | lifecycle by default. When disabled, only Component-based handlers
    | use Fibers; traditional callables run synchronously.
    |
    */
    'async_default' => Env::bool('LIFECYCLE_ASYNC_DEFAULT', true),

    /*
    |--------------------------------------------------------------------------
    | Hook Listeners
    |--------------------------------------------------------------------------
    |
    | Global hook listeners that run for all requests. Each listener is
    | a callable that receives the hook name and component (if applicable).
    |
    | Example:
    | 'listeners' => [
    |     Hook::BEFORE_FETCH->value => [
    |         fn($component) => $component->set('start_time', microtime(true)),
    |     ],
    | ],
    |
    */
    'listeners' => [],
];
