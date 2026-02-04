<?php

declare(strict_types=1);

namespace Examples\User\Handlers;

use Examples\User\Queries\GetUserById;
use Examples\User\User;
use Examples\User\UserRepository;
use Fw\Bus\Handler;
use Fw\Support\Option;

/**
 * Handler for GetUserById query.
 *
 * Returns Option<User> to handle not-found case cleanly.
 */
final class GetUserByIdHandler implements Handler
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * @return Option<User>
     */
    public function handle(GetUserById $query): Option
    {
        return $this->users->find($query->id);
    }
}
