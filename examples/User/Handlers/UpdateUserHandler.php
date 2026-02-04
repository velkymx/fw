<?php

declare(strict_types=1);

namespace Examples\User\Handlers;

use Examples\User\Commands\UpdateUser;
use Examples\User\Events\UserUpdated;
use Examples\User\User;
use Examples\User\UserRepository;
use Fw\Bus\Handler;
use Fw\Domain\Email;
use Fw\Events\EventDispatcher;
use Fw\Repository\EntityNotFoundException;

/**
 * Handler for UpdateUser command.
 */
final class UpdateUserHandler implements Handler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EventDispatcher $events,
    ) {}

    public function handle(UpdateUser $command): User
    {
        // Find existing user
        $user = $this->users->findOrFail($command->id);

        // Apply updates (immutable)
        if ($command->name !== null) {
            $user = $user->withName($command->name);
        }

        if ($command->email !== null) {
            $newEmail = Email::from($command->email);

            // Check if email is taken by someone else
            $existing = $this->users->findByEmail($newEmail);
            if ($existing->isSome() && !$existing->unwrap()->id->equals($command->id)) {
                throw new \DomainException('Email is already registered');
            }

            $user = $user->withEmail($newEmail);
        }

        // Persist
        $this->users->save($user);

        // Emit event
        $this->events->dispatch(new UserUpdated($user));

        return $user;
    }
}
