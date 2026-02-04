<?php

declare(strict_types=1);

namespace Examples\User\Events;

use Examples\User\User;
use Fw\Events\DomainEvent;

/**
 * Domain event when a user is created.
 */
final readonly class UserCreated extends DomainEvent
{
    public function __construct(
        public User $user,
    ) {
        parent::__construct(aggregateId: $user->id->value);
    }
}
