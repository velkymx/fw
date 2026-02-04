<?php

declare(strict_types=1);

namespace Fw\Core;

use DateTimeImmutable;
use Fw\Events\Event;

/**
 * Event dispatched when the application has finished booting.
 *
 * All services are initialized and ready at this point.
 */
final readonly class ApplicationBooted implements Event
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public Application $app,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
