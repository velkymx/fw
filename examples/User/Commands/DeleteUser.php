<?php

declare(strict_types=1);

namespace Examples\User\Commands;

use Fw\Bus\Command;
use Fw\Domain\UserId;

/**
 * Command to delete a user.
 */
final readonly class DeleteUser implements Command
{
    public function __construct(
        public UserId $id,
    ) {}
}
