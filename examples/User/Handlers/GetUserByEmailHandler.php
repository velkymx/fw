<?php

declare(strict_types=1);

namespace Examples\User\Handlers;

use Examples\User\Queries\GetUserByEmail;
use Examples\User\User;
use Examples\User\UserRepository;
use Fw\Bus\Handler;
use Fw\Support\Option;

/**
 * Handler for GetUserByEmail query.
 */
final class GetUserByEmailHandler implements Handler
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * @return Option<User>
     */
    public function handle(GetUserByEmail $query): Option
    {
        return $this->users->findByEmailString($query->email);
    }
}
