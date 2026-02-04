<?php

declare(strict_types=1);

namespace Fw\Core;

use Fw\Async\EventLoop;
use Fw\Bus\CommandBus;
use Fw\Bus\QueryBus;
use Fw\Database\Connection;
use Fw\Events\EventDispatcher;
use Fw\Log\Logger;
use Fw\Security\Csrf;
use Fw\Cache\CacheInterface;

/**
 * Application - the main framework orchestrator.
 *
 * This class bootstraps the application and coordinates between
 * the various subsystems. It delegates to specialized classes:
 *
 * - Config: Configuration loading and access
 * - ProviderRegistry: Service provider management
 * - HttpKernel: Request/response lifecycle
 * - ErrorHandler: Exception and error handling
 *
 * @example
 *     // In public/index.php
 *     $app = Application::getInstance();
 *     $app->run();
 */
final class Application
{
    private static ?Application $instance = null;

    // Core services (public for controller access)
    public private(set) Request $request;
    public private(set) Response $response;
    public private(set) Router $router;
    public private(set) View $view;
    public private(set) ?Connection $db = null;
    public private(set) Csrf $csrf;
    public private(set) Logger $log;
    public private(set) Container $container;
    public private(set) EventDispatcher $events;
    public private(set) ?CommandBus $commands = null;
    public private(set) ?QueryBus $queries = null;

    // Extracted subsystems
    private Config $configRepository;
    private ProviderRegistry $providers;
    private HttpKernel $kernel;
    private ErrorHandler $errorHandler;

    private function __construct()
    {
        // 1. Initialize configuration
        $this->configRepository = new Config(BASE_PATH);
        $this->configRepository->load();

        // 2. Initialize container
        $this->container = Container::getInstance();
        $this->registerCoreServices();

        // 3. Initialize logging and events (resolved from container for proper DI)
        $this->log = $this->container->get(Logger::class);
        $this->events = new EventDispatcher($this->container->resolver());

        // 4. Initialize request/response cycle
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router();
        $this->csrf = new Csrf(fn() => $this->initSession());

        // 5. Initialize buses
        $this->commands = new CommandBus($this->container->resolver());
        $this->queries = new QueryBus($this->container->resolver());

        // 6. Register core instances in container
        $this->registerContainerInstances();

        // 7. Initialize provider registry and register providers
        $this->providers = new ProviderRegistry($this, $this->container, $this->log);
        $this->providers->loadFrom(BASE_PATH . '/config/providers.php');
        $this->providers->register();

        // 8. Create View (after cache provider is registered)
        $cache = $this->container->get(CacheInterface::class);
        $this->view = new View(
            BASE_PATH . '/app/Views',
            $cache,
            $this->router,
            $this->csrf,
        );

        // Enable view caching for rendered output
        $this->view->enableCache(BASE_PATH . '/storage/cache/views');

        // 9. Boot providers
        $this->providers->boot();

        // 10. Initialize database
        $this->initializeDatabase();

        // 11. Configure queue
        $this->configureQueue();

        // 12. Create error handler and HTTP kernel
        $this->errorHandler = new ErrorHandler(
            $this->response,
            $this->log,
            $this->configRepository,
        );

        $this->kernel = new HttpKernel(
            $this,
            $this->router,
            $this->container,
            $this->events,
            $this->errorHandler,
            $this->configRepository,
        );

        // 13. Emit application booted event
        $this->events->dispatch(new ApplicationBooted($this));
    }

    /**
     * Register core services in the container.
     */
    private function registerCoreServices(): void
    {
        // Register Logger as singleton and sync with static instance
        $this->container->singleton(Logger::class, function () {
            $logger = new Logger();
            Logger::setInstance($logger);
            return $logger;
        });

        // Register EventLoop as singleton and sync with static instance
        $this->container->singleton(EventLoop::class, function () {
            $loop = new EventLoop();
            EventLoop::setInstance($loop);
            return $loop;
        });
    }

    /**
     * Register core instances in the container.
     */
    private function registerContainerInstances(): void
    {
        $this->container->instance(self::class, $this);
        $this->container->instance(Config::class, $this->configRepository);
        $this->container->instance(Request::class, $this->request);
        $this->container->instance(Response::class, $this->response);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Csrf::class, $this->csrf);
        $this->container->instance(EventDispatcher::class, $this->events);
        $this->container->instance(CommandBus::class, $this->commands);
        $this->container->instance(QueryBus::class, $this->queries);
    }

    /**
     * Initialize database connection.
     */
    private function initializeDatabase(): void
    {
        if (!$this->configRepository->get('database.enabled', false)) {
            return;
        }

        $this->db = Connection::getInstance(
            $this->configRepository->section('database')
        );

        $this->container->instance(Connection::class, $this->db);

        // Set connection on model classes
        \Fw\Model\Model::setConnection($this->db);
        \Fw\Database\Model::setConnection($this->db);
        \Fw\Auth\PasswordReset::setConnection($this->db);
    }

    /**
     * Configure the queue system.
     */
    private function configureQueue(): void
    {
        \Fw\Queue\Queue::configure(
            $this->configRepository->section('queue'),
            $this->db
        );
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Run the application.
     *
     * Delegates to the HttpKernel for request handling.
     */
    public function run(): void
    {
        $this->kernel->handle($this->request, $this->response);
    }

    /**
     * Initialize session (called lazily when needed).
     */
    public function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $secure = $this->resolveSecureCookieSetting();

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            session_start();
        }
    }

    /**
     * Resolve the secure cookie setting.
     *
     * In production, forces secure=true and warns if connection is not HTTPS.
     * In development, respects the config setting.
     */
    private function resolveSecureCookieSetting(): bool
    {
        $configSecure = $this->configRepository->get('app.secure_cookies', true);
        $isProduction = $this->configRepository->get('app.env', 'production') === 'production';
        $isHttps = $this->request->isSecure();

        // In production, always use secure cookies
        if ($isProduction) {
            // Warn if production is accessed over HTTP (potential misconfiguration)
            if (!$isHttps && $configSecure) {
                error_log(
                    'Warning: Secure cookies enabled but request is not over HTTPS. ' .
                    'This may indicate a misconfigured proxy or insecure production setup.'
                );
            }
            return true;
        }

        // In development, respect the config but auto-detect if not set
        if ($configSecure === null) {
            return $isHttps;
        }

        return (bool) $configSecure;
    }

    /**
     * Get a configuration value.
     *
     * @deprecated Use config() method or inject Config class
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->configRepository->get($key, $default);
    }

    /**
     * Get the configuration repository.
     */
    public function getConfig(): Config
    {
        return $this->configRepository;
    }

    /**
     * Get the provider registry.
     */
    public function getProviders(): ProviderRegistry
    {
        return $this->providers;
    }

    /**
     * Get the HTTP kernel.
     */
    public function getKernel(): HttpKernel
    {
        return $this->kernel;
    }

    /**
     * Get the error handler.
     */
    public function getErrorHandler(): ErrorHandler
    {
        return $this->errorHandler;
    }
}
