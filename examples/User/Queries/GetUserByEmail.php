<?php

declare(strict_types=1);

namespace Examples\User\Queries;

use Fw\Bus\Query;

/**
 * Query to get a user by email.
 */
final readonly class GetUserByEmail implements Query
{
    public function __construct(
        public string $email,
    ) {}
}
