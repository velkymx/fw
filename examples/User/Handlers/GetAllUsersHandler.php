<?php

declare(strict_types=1);

namespace Examples\User\Handlers;

use Examples\User\Queries\GetAllUsers;
use Examples\User\User;
use Examples\User\UserRepository;
use Fw\Bus\Handler;

/**
 * Handler for GetAllUsers query.
 */
final class GetAllUsersHandler implements Handler
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * @return array<User>
     */
    public function handle(GetAllUsers $query): array
    {
        return $this->users->paginate($query->page, $query->perPage);
    }
}
