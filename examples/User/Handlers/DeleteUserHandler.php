<?php

declare(strict_types=1);

namespace Examples\User\Handlers;

use Examples\User\Commands\DeleteUser;
use Examples\User\Events\UserDeleted;
use Examples\User\UserRepository;
use Fw\Bus\Handler;
use Fw\Events\EventDispatcher;

/**
 * Handler for DeleteUser command.
 */
final class DeleteUserHandler implements Handler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EventDispatcher $events,
    ) {}

    public function handle(DeleteUser $command): void
    {
        // Get user first to include in event
        $user = $this->users->findOrFail($command->id);

        // Delete
        $this->users->delete($user);

        // Emit event
        $this->events->dispatch(new UserDeleted($user->id, $user->email));
    }
}
