<?php

declare(strict_types=1);

/**
 * Service Provider Configuration
 *
 * Register all service providers for the application. Framework
 * providers are loaded first, followed by application providers.
 *
 * Providers are registered in order, then booted in order.
 * Boot happens after all providers are registered.
 */

return [
    // Framework Providers
    Fw\Providers\EventServiceProvider::class,
    Fw\Providers\BusServiceProvider::class,
    Fw\Providers\MiddlewareServiceProvider::class,
    Fw\Providers\DatabaseServiceProvider::class,
    Fw\Providers\CacheServiceProvider::class,

    // Application Providers
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
];
