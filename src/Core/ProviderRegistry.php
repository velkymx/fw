<?php

declare(strict_types=1);

namespace Fw\Core;

use Fw\Log\Logger;

/**
 * Service provider registry.
 *
 * Manages the registration and booting of service providers.
 * Providers have two lifecycle phases:
 *
 * 1. Register - Bind services to the container (no resolving)
 * 2. Boot - Initialize services (can resolve from container)
 *
 * @example
 *     $registry = new ProviderRegistry($app, $container, $logger);
 *     $registry->load(BASE_PATH . '/config/providers.php');
 *     $registry->register();
 *     // ... other initialization ...
 *     $registry->boot();
 */
final class ProviderRegistry
{
    /**
     * Registered service providers.
     * @var list<ServiceProvider>
     */
    private array $providers = [];

    /**
     * Whether providers have been registered.
     */
    private bool $registered = false;

    /**
     * Whether providers have been booted.
     */
    private bool $booted = false;

    public function __construct(
        private Application $app,
        private Container $container,
        private Logger $log,
    ) {}

    /**
     * Load provider classes from a configuration file.
     *
     * @param string $path Path to providers.php configuration file
     * @return list<class-string<ServiceProvider>> Provider class names
     */
    public function load(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $providers = require $path;

        return is_array($providers) ? $providers : [];
    }

    /**
     * Add a provider class to the registry.
     *
     * @param class-string<ServiceProvider> $providerClass
     */
    public function add(string $providerClass): self
    {
        if (!class_exists($providerClass)) {
            $this->log->warning('Provider class not found: {class}', [
                'class' => $providerClass,
            ]);
            return $this;
        }

        $provider = new $providerClass($this->app, $this->container);
        $this->providers[] = $provider;

        // If already registered, register this provider immediately
        if ($this->registered) {
            $provider->register();
        }

        // If already booted, boot this provider immediately
        if ($this->booted) {
            $provider->boot();
        }

        return $this;
    }

    /**
     * Add multiple provider classes.
     *
     * @param list<class-string<ServiceProvider>> $providerClasses
     */
    public function addMany(array $providerClasses): self
    {
        foreach ($providerClasses as $providerClass) {
            $this->add($providerClass);
        }

        return $this;
    }

    /**
     * Load and add providers from a configuration file.
     *
     * @param string $path Path to providers.php configuration file
     */
    public function loadFrom(string $path): self
    {
        $providerClasses = $this->load($path);
        return $this->addMany($providerClasses);
    }

    /**
     * Register all providers.
     *
     * Calls register() on each provider. This phase should only
     * bind services to the container, not resolve them.
     */
    public function register(): self
    {
        if ($this->registered) {
            return $this;
        }

        foreach ($this->providers as $provider) {
            $provider->register();
        }

        $this->registered = true;

        return $this;
    }

    /**
     * Boot all providers.
     *
     * Calls boot() on each provider. This phase can resolve
     * services from the container and perform initialization.
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        // Ensure providers are registered first
        if (!$this->registered) {
            $this->register();
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;

        return $this;
    }

    /**
     * Get all registered providers.
     *
     * @return list<ServiceProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Get a provider by class name.
     *
     * @template T of ServiceProvider
     * @param class-string<T> $class
     * @return T|null
     */
    public function get(string $class): ?ServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof $class) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Check if a provider is registered.
     *
     * @param class-string<ServiceProvider> $class
     */
    public function has(string $class): bool
    {
        return $this->get($class) !== null;
    }

    /**
     * Check if providers have been registered.
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Check if providers have been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }
}
