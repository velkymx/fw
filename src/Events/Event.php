<?php

declare(strict_types=1);

namespace Fw\Events;

use DateTimeImmutable;

/**
 * Base interface for domain events.
 *
 * Events represent something that happened in the domain.
 * They are immutable facts about past occurrences.
 *
 * Events should:
 * - Be named in past tense (UserCreated, OrderPlaced)
 * - Be immutable
 * - Contain all relevant data about what happened
 * - Include when it happened
 *
 * Example:
 *     final readonly class UserCreated implements Event
 *     {
 *         public function __construct(
 *             public UserId $userId,
 *             public Email $email,
 *             public DateTimeImmutable $occurredAt = new DateTimeImmutable()
 *         ) {}
 *     }
 */
interface Event
{
    /**
     * Get when the event occurred.
     */
    public function occurredAt(): DateTimeImmutable;
}
