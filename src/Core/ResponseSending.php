<?php

declare(strict_types=1);

namespace Fw\Core;

use DateTimeImmutable;
use Fw\Events\Event;

/**
 * Event dispatched just before the response is sent.
 *
 * Useful for final modifications, logging, cleanup.
 */
final readonly class ResponseSending implements Event
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public Response $response,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
