<?php

declare(strict_types=1);

namespace Fw\Auth;

use App\Models\User;

/**
 * Base Policy Class.
 *
 * EVERY policy MUST extend this class.
 * EVERY policy method MUST return bool.
 * EVERY policy method receives the authenticated User as the first argument.
 *
 * Naming convention:
 *   - Policy class: {ModelName}Policy (e.g., PostPolicy for Post model)
 *   - Policy location: App\Policies\{ModelName}Policy
 *   - Method names: Match the action (view, create, edit, delete, etc.)
 *
 * Example:
 *
 *   class PostPolicy extends Policy
 *   {
 *       public function view(User $user, Post $post): bool
 *       {
 *           return true; // Anyone can view
 *       }
 *
 *       public function create(User $user): bool
 *       {
 *           return true; // Any authenticated user can create
 *       }
 *
 *       public function edit(User $user, Post $post): bool
 *       {
 *           return $user->id === $post->user_id;
 *       }
 *
 *       public function delete(User $user, Post $post): bool
 *       {
 *           return $user->id === $post->user_id;
 *       }
 *   }
 */
abstract class Policy
{
    /**
     * Override this to bypass ALL policy checks for certain users.
     * If this returns true, all actions are allowed without checking methods.
     * If this returns false, normal policy checks apply.
     * If this returns null, normal policy checks apply.
     *
     * Use this for admin bypass:
     *
     *   protected function before(User $user, string $action): ?bool
     *   {
     *       if ($user->role === 'admin') {
     *           return true;
     *       }
     *       return null;
     *   }
     */
    public function before(User $user, string $action): ?bool
    {
        return null;
    }
}
