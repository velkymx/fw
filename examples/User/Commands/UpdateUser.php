<?php

declare(strict_types=1);

namespace Examples\User\Commands;

use Fw\Bus\Command;
use Fw\Domain\UserId;

/**
 * Command to update an existing user.
 */
final readonly class UpdateUser implements Command
{
    public function __construct(
        public UserId $id,
        public ?string $email = null,
        public ?string $name = null,
    ) {}
}
