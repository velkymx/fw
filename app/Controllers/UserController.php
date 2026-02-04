<?php

declare(strict_types=1);

namespace App\Controllers;

use Fw\Auth\Auth;
use Fw\Core\Controller;
use Fw\Core\Request;
use Fw\Core\Response;
use App\Models\User;

/**
 * User management controller.
 *
 * Demonstrates:
 * - Admin-style resource management
 * - Self-service profile editing
 * - Unique validation
 * - Pagination
 * - Flash messages
 */
class UserController extends Controller
{
    /**
     * List all users (admin view).
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int) $this->input($request, 'page', 1));
        $perPage = 15;

        $pagination = User::orderBy('created_at', 'desc')
            ->paginate($perPage, $page);

        return $this->view('users.index', [
            'users' => $pagination['items'],
            'pagination' => $pagination,
        ]);
    }

    /**
     * Show a single user profile.
     */
    public function show(Request $request, string $id): Response
    {
        $user = User::with('posts')->find((int) $id);

        if ($user->isNone()) {
            return $this->notFound('User not found.');
        }

        $user = $user->unwrap();

        // Get user's published posts
        $posts = $user->posts()
            ->getQuery()
            ->whereNotNull('published_at')
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

        return $this->view('users.show', compact('user', 'posts'));
    }

    /**
     * Show form to create a new user.
     */
    public function create(Request $request): Response
    {
        return $this->view('users.create');
    }

    /**
     * Store a new user.
     */
    public function store(Request $request): Response
    {
        $validation = $this->validate($request, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validation->isErr()) {
            return $this->back()->withErrors($validation->getError());
        }

        $data = $validation->getValue();

        // Check for duplicate email
        $existing = User::findByEmail($data['email']);
        if ($existing->isSome()) {
            return $this->back()->withErrors(['email' => 'This email is already registered.']);
        }

        $user = new User();
        $user->setAttribute('email', strtolower($data['email']));
        $user->setAttribute('name', trim($data['name']));
        $user->setPassword($data['password']);

        $result = $user->save();

        if ($result->isErr()) {
            return $this->back()->withErrors(['name' => 'Failed to create user. Please try again.']);
        }

        return $this->redirect("/users/{$user->getKey()}")
            ->with('success', 'User created successfully!');
    }

    /**
     * Show form to edit a user.
     *
     * Users can only edit their own profile unless they're an admin.
     */
    public function edit(Request $request, string $id): Response
    {
        $user = User::find((int) $id);

        if ($user->isNone()) {
            return $this->notFound('User not found.');
        }

        $user = $user->unwrap();
        $currentUser = Auth::user();

        // Authorization: users can only edit themselves
        // In a real app, you'd also check for admin role
        if ($currentUser->getKey() !== $user->getKey()) {
            return $this->forbidden('You can only edit your own profile.');
        }

        return $this->view('users.edit', compact('user'));
    }

    /**
     * Update a user.
     */
    public function update(Request $request, string $id): Response
    {
        $user = User::find((int) $id);

        if ($user->isNone()) {
            return $this->notFound('User not found.');
        }

        $user = $user->unwrap();
        $currentUser = Auth::user();

        // Authorization: users can only edit themselves
        if ($currentUser->getKey() !== $user->getKey()) {
            return $this->forbidden('You can only edit your own profile.');
        }

        $validation = $this->validate($request, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
        ]);

        if ($validation->isErr()) {
            return $this->back()->withErrors($validation->getError());
        }

        $data = $validation->getValue();
        $newEmail = strtolower($data['email']);

        // Check for duplicate email (only if changed)
        if ($newEmail !== strtolower((string) $user->getAttribute('email'))) {
            $existing = User::findByEmail($newEmail);
            if ($existing->isSome()) {
                return $this->back()->withErrors(['email' => 'This email is already in use.']);
            }
        }

        $user->setAttribute('email', $newEmail);
        $user->setAttribute('name', trim($data['name']));

        // Handle optional password change
        $newPassword = $this->input($request, 'password');
        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return $this->back()->withErrors(['password' => 'Password must be at least 8 characters.']);
            }
            $user->setPassword($newPassword);
        }

        $result = $user->save();

        if ($result->isErr()) {
            return $this->back()->withErrors(['name' => 'Failed to update profile. Please try again.']);
        }

        return $this->redirect("/users/{$user->getKey()}")
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Delete a user.
     *
     * Users can delete their own account. Admins can delete anyone.
     */
    public function destroy(Request $request, string $id): Response
    {
        $user = User::find((int) $id);

        if ($user->isNone()) {
            return $this->redirect('/users')
                ->with('error', 'User not found.');
        }

        $user = $user->unwrap();
        $currentUser = Auth::user();

        // Authorization: users can only delete themselves
        if ($currentUser->getKey() !== $user->getKey()) {
            return $this->forbidden('You can only delete your own account.');
        }

        // Logout before deleting if deleting self
        if ($currentUser->getKey() === $user->getKey()) {
            Auth::logout();
        }

        $user->delete();

        return $this->redirect('/')
            ->with('success', 'Account deleted successfully.');
    }
}
