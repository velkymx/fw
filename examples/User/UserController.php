<?php

declare(strict_types=1);

namespace Examples\User;

use Examples\User\Commands\CreateUser;
use Examples\User\Commands\DeleteUser;
use Examples\User\Commands\UpdateUser;
use Examples\User\Queries\GetAllUsers;
use Examples\User\Queries\GetUserById;
use Fw\Core\Controller;
use Fw\Core\Request;
use Fw\Core\Response;
use Fw\Domain\UserId;

/**
 * Traditional MVC Controller for User operations.
 *
 * Demonstrates:
 * - Using the base Controller class
 * - Command/Query bus integration
 * - Result type handling
 * - View rendering
 * - JSON responses
 * - Validation
 *
 * This is the familiar MVC pattern that developers know,
 * but with modern Result types and CQRS under the hood.
 */
final class UserController extends Controller
{
    /**
     * GET /users
     * List all users.
     */
    public function index(Request $request): Response
    {
        $page = (int) $this->input($request, 'page', 1);

        $result = $this->query(new GetAllUsers(page: $page));

        return $result->match(
            fn($users) => $this->view('users.index', ['users' => $users, 'page' => $page]),
            fn($error) => $this->serverError($error->getMessage())
        );
    }

    /**
     * GET /users/{id}
     * Show a single user.
     */
    public function show(Request $request, string $id): Response
    {
        $result = $this->query(new GetUserById(UserId::from($id)));

        return $result->match(
            fn($option) => $option->match(
                fn($user) => $this->view('users.show', ['user' => $user]),
                fn() => $this->notFound('User not found')
            ),
            fn($error) => $this->serverError($error->getMessage())
        );
    }

    /**
     * GET /users/create
     * Show user creation form.
     */
    public function create(Request $request): Response
    {
        return $this->view('users.create');
    }

    /**
     * POST /users
     * Store a new user.
     */
    public function store(Request $request): Response
    {
        // Validate input using Result
        $validation = $this->validate($request, [
            'email' => 'required|email',
            'name' => 'required|min:2|max:100',
        ]);

        if ($validation->isErr()) {
            return $this->back()->withErrors($validation->getError());
        }

        $data = $validation->getValue();

        // Dispatch command
        $result = $this->dispatch(new CreateUser(
            email: $data['email'],
            name: $data['name'],
        ));

        return $result->match(
            fn($user) => $this->redirect("/users/{$user->id}"),
            fn($error) => $this->back()->withErrors(['error' => $error->getMessage()])
        );
    }

    /**
     * GET /users/{id}/edit
     * Show user edit form.
     */
    public function edit(Request $request, string $id): Response
    {
        $result = $this->query(new GetUserById(UserId::from($id)));

        return $result->match(
            fn($option) => $option->match(
                fn($user) => $this->view('users.edit', ['user' => $user]),
                fn() => $this->notFound('User not found')
            ),
            fn($error) => $this->serverError($error->getMessage())
        );
    }

    /**
     * PUT /users/{id}
     * Update a user.
     */
    public function update(Request $request, string $id): Response
    {
        $validation = $this->validate($request, [
            'email' => 'email',
            'name' => 'min:2|max:100',
        ]);

        if ($validation->isErr()) {
            return $this->back()->withErrors($validation->getError());
        }

        $data = $validation->getValue();

        $result = $this->dispatch(new UpdateUser(
            id: UserId::from($id),
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
        ));

        return $result->match(
            fn($user) => $this->redirect("/users/{$user->id}"),
            fn($error) => $this->back()->withErrors(['error' => $error->getMessage()])
        );
    }

    /**
     * DELETE /users/{id}
     * Delete a user.
     */
    public function destroy(Request $request, string $id): Response
    {
        $result = $this->dispatch(new DeleteUser(
            id: UserId::from($id),
        ));

        return $result->match(
            fn() => $this->redirect('/users'),
            fn($error) => $this->back()->withErrors(['error' => $error->getMessage()])
        );
    }

    // ========================================
    // API ENDPOINTS (JSON)
    // ========================================

    /**
     * GET /api/users
     * API: List all users.
     */
    public function apiIndex(Request $request): Response
    {
        $page = (int) $this->input($request, 'page', 1);

        return $this->query(new GetAllUsers(page: $page))->match(
            fn($users) => $this->json(['data' => $users, 'page' => $page]),
            fn($error) => $this->json(['error' => $error->getMessage()], 500)
        );
    }

    /**
     * GET /api/users/{id}
     * API: Get single user.
     */
    public function apiShow(Request $request, string $id): Response
    {
        return $this->query(new GetUserById(UserId::from($id)))->match(
            fn($option) => $option->match(
                fn($user) => $this->json(['data' => $user]),
                fn() => $this->json(['error' => 'User not found'], 404)
            ),
            fn($error) => $this->json(['error' => $error->getMessage()], 500)
        );
    }

    /**
     * POST /api/users
     * API: Create user.
     */
    public function apiStore(Request $request): Response
    {
        $validation = $this->validate($request, [
            'email' => 'required|email',
            'name' => 'required|min:2|max:100',
        ]);

        if ($validation->isErr()) {
            return $this->json(['errors' => $validation->getError()], 422);
        }

        $data = $validation->getValue();

        return $this->dispatch(new CreateUser(
            email: $data['email'],
            name: $data['name'],
        ))->match(
            fn($user) => $this->json(['data' => $user], 201),
            fn($error) => $this->json(['error' => $error->getMessage()], 400)
        );
    }
}
