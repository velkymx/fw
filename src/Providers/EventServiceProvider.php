<?php

declare(strict_types=1);

namespace Fw\Providers;

use Fw\Core\ServiceProvider;
use Fw\Events\EventDispatcher;
use Fw\Events\EventSubscriber;

/**
 * Framework Event Service Provider.
 *
 * Registers the EventDispatcher and provides hooks for
 * subscribing listeners and subscribers.
 *
 * Application-level event providers should extend this class
 * and override the $listen and $subscribe properties.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Event to listener mappings.
     *
     * @var array<class-string, list<class-string|callable>>
     */
    protected array $listen = [];

    /**
     * Event subscriber classes.
     *
     * @var list<class-string<EventSubscriber>>
     */
    protected array $subscribe = [];

    public function register(): void
    {
        // EventDispatcher is already created in Application
        // This provider just registers listeners
    }

    public function boot(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);

        // Register event listeners
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        // Register event subscribers
        foreach ($this->subscribe as $subscriber) {
            $dispatcher->subscribe($subscriber);
        }
    }

    /**
     * Get the registered listeners.
     *
     * @return array<class-string, list<class-string|callable>>
     */
    public function getListeners(): array
    {
        return $this->listen;
    }

    /**
     * Get the registered subscribers.
     *
     * @return list<class-string<EventSubscriber>>
     */
    public function getSubscribers(): array
    {
        return $this->subscribe;
    }
}
