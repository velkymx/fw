<?php

declare(strict_types=1);

namespace Examples\User\Commands;

use Fw\Bus\Command;

/**
 * Command to create a new user.
 *
 * Commands are immutable data transfer objects.
 */
final readonly class CreateUser implements Command
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
