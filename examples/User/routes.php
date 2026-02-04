<?php

declare(strict_types=1);

/**
 * Example routes for User domain.
 *
 * Shows both Controller-based MVC routes and Component-based routes.
 *
 * Include this file in your config/routes.php:
 *
 * return function (Router $router) {
 *     require __DIR__ . '/../examples/User/routes.php';
 * };
 */

use Examples\User\UserController;
use Examples\User\UserProfileComponent;
use Fw\Core\Router;

return function (Router $router) {

    // ========================================
    // WEB ROUTES (MVC Controller)
    // ========================================

    $router->group('/users', function (Router $router) {

        // List users
        $router->get('/', [UserController::class, 'index'], 'users.index');

        // Create user form
        $router->get('/create', [UserController::class, 'create'], 'users.create');

        // Store new user
        $router->post('/', [UserController::class, 'store'], 'users.store');

        // Show user profile (Component-based for async data)
        $router->get('/{id}', UserProfileComponent::class, 'users.show');

        // Edit user form
        $router->get('/{id}/edit', [UserController::class, 'edit'], 'users.edit');

        // Update user
        $router->put('/{id}', [UserController::class, 'update'], 'users.update');

        // Delete user
        $router->delete('/{id}', [UserController::class, 'destroy'], 'users.destroy');

    }, middleware: ['auth', 'csrf']);

    // ========================================
    // API ROUTES (JSON responses)
    // ========================================

    $router->group('/api/users', function (Router $router) {

        // List users
        $router->get('/', [UserController::class, 'apiIndex']);

        // Get user
        $router->get('/{id}', [UserController::class, 'apiShow']);

        // Create user
        $router->post('/', [UserController::class, 'apiStore']);

    }, middleware: ['api', 'throttle:60,1']);

};
