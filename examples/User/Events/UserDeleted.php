<?php

declare(strict_types=1);

namespace Examples\User\Events;

use Fw\Domain\Email;
use Fw\Domain\UserId;
use Fw\Events\DomainEvent;

/**
 * Domain event when a user is deleted.
 */
final readonly class UserDeleted extends DomainEvent
{
    public function __construct(
        public UserId $userId,
        public Email $email,
    ) {
        parent::__construct(aggregateId: $userId->value);
    }
}
