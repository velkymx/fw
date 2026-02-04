<?php

declare(strict_types=1);

namespace Fw\Core;

use DateTimeImmutable;
use Fw\Events\Event;

/**
 * Event dispatched when a request is received.
 *
 * Dispatched before routing begins. Useful for logging, analytics, etc.
 */
final readonly class RequestReceived implements Event
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public Request $request,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
