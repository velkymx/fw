<?php

declare(strict_types=1);

namespace Examples\User\Queries;

use Fw\Bus\Query;
use Fw\Domain\UserId;

/**
 * Query to get a user by ID.
 */
final readonly class GetUserById implements Query
{
    public function __construct(
        public UserId $id,
    ) {}
}
