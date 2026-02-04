<?php

declare(strict_types=1);

namespace Fw\Events;

/**
 * Interface for event subscribers.
 *
 * Subscribers can register multiple event listeners in a single class.
 * This is useful for grouping related event handlers.
 *
 * Example:
 *     final class UserEventSubscriber implements EventSubscriber
 *     {
 *         public function subscribe(EventDispatcher $dispatcher): void
 *         {
 *             $dispatcher->listen(UserCreated::class, [$this, 'onUserCreated']);
 *             $dispatcher->listen(UserDeleted::class, [$this, 'onUserDeleted']);
 *         }
 *
 *         public function onUserCreated(UserCreated $event): void
 *         {
 *             // Handle user created
 *         }
 *
 *         public function onUserDeleted(UserDeleted $event): void
 *         {
 *             // Handle user deleted
 *         }
 *     }
 */
interface EventSubscriber
{
    /**
     * Register event listeners with the dispatcher.
     */
    public function subscribe(EventDispatcher $dispatcher): void;
}
