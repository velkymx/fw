<?php

declare(strict_types=1);

namespace Examples\User\Queries;

use Fw\Bus\Query;

/**
 * Query to get all users with pagination.
 */
final readonly class GetAllUsers implements Query
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 15,
    ) {}
}
