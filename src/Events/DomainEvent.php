<?php

declare(strict_types=1);

namespace Fw\Events;

use DateTimeImmutable;
use Fw\Support\Str;

/**
 * Base class for domain events.
 *
 * Provides common functionality for events including
 * automatic timestamp and unique event ID.
 *
 * Usage:
 *     final readonly class UserCreated extends DomainEvent
 *     {
 *         public function __construct(
 *             public UserId $userId,
 *             public Email $email
 *         ) {
 *             parent::__construct();
 *         }
 *     }
 */
abstract readonly class DomainEvent implements Event
{
    /**
     * Unique event identifier.
     */
    public string $eventId;

    /**
     * When the event occurred.
     */
    public DateTimeImmutable $occurredAt;

    public function __construct(?DateTimeImmutable $occurredAt = null)
    {
        $this->eventId = Str::uuid();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Get the event name (class name without namespace).
     */
    public function eventName(): string
    {
        return Str::afterLast(static::class, '\\');
    }
}
