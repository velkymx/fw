<?php

declare(strict_types=1);

namespace Examples\User\Handlers;

use Examples\User\Commands\CreateUser;
use Examples\User\Events\UserCreated;
use Examples\User\User;
use Examples\User\UserRepository;
use Fw\Bus\Handler;
use Fw\Domain\Email;
use Fw\Domain\UserId;
use Fw\Events\EventDispatcher;

/**
 * Handler for CreateUser command.
 *
 * Demonstrates:
 * - Constructor injection of dependencies
 * - Value Object creation
 * - Entity persistence
 * - Domain event emission
 */
final class CreateUserHandler implements Handler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EventDispatcher $events,
    ) {}

    public function handle(CreateUser $command): User
    {
        // Validate email is not taken
        $email = Email::from($command->email);

        if ($this->users->emailExists($email)) {
            throw new \DomainException('Email is already registered');
        }

        // Create user entity
        $user = new User(
            id: UserId::generate(),
            email: $email,
            name: $command->name,
            createdAt: date('Y-m-d H:i:s'),
        );

        // Persist
        $this->users->save($user);

        // Emit domain event
        $this->events->dispatch(new UserCreated($user));

        return $user;
    }
}
