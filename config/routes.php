<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\UserController;
use App\Controllers\PostController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\RegisterController;
use Fw\Core\Router;

/**
 * Application Routes
 *
 * Demonstrates:
 * - Route groups with middleware
 * - Named routes for URL generation
 * - RESTful resource routing
 * - Authentication and authorization
 * - Rate limiting on sensitive endpoints
 *
 * Available middleware aliases (from config/middleware.php):
 *   - 'auth'     : Require authenticated session
 *   - 'guest'    : Only allow unauthenticated users
 *   - 'csrf'     : Validate CSRF token
 *   - 'throttle' : Rate limiting (60 req/min default)
 *   - 'can:X,Y'  : Authorization check with parameters
 *
 * Available middleware groups:
 *   - 'web'           : ['csrf']
 *   - 'api'           : ['throttle']
 *   - 'authenticated' : ['auth', 'csrf']
 */

return function (Router $router): void {
    // =========================================================================
    // Public Routes (No Authentication Required)
    // =========================================================================

    $router->get('/', [HomeController::class, 'index'], 'home');
    $router->get('/about', [HomeController::class, 'about'], 'about');
    $router->get('/health', [HomeController::class, 'health'], 'health');

    // Dashboard redirect (GuestMiddleware hardcodes this path)
    $router->get('/dashboard', [HomeController::class, 'dashboard'], 'dashboard');

    // Public post viewing (read-only)
    $router->get('/posts', [PostController::class, 'index'], 'posts.index');

    // =========================================================================
    // Authentication Routes
    // =========================================================================

    // Guest-only routes (redirect if already logged in)
    $router->group('', function (Router $router) {
        $router->get('/login', [LoginController::class, 'show'], 'login');
        $router->get('/register', [RegisterController::class, 'show'], 'register');

        // POST routes with CSRF + rate limiting to prevent brute force
        $router->post('/login', [LoginController::class, 'login'], 'login.submit')
            ->middleware(['csrf', 'throttle']);
        $router->post('/register', [RegisterController::class, 'register'], 'register.submit')
            ->middleware(['csrf', 'throttle']);
    }, ['guest']);

    // Logout requires authentication
    $router->post('/logout', [LoginController::class, 'logout'], 'logout')
        ->middleware(['auth', 'csrf']);

    // =========================================================================
    // Protected Post Routes (Authentication Required)
    // =========================================================================

    $router->get('/posts/create', [PostController::class, 'create'], 'posts.create')
        ->middleware(['auth', 'csrf']);
    $router->post('/posts', [PostController::class, 'store'], 'posts.store')
        ->middleware(['auth', 'csrf']);
    $router->get('/posts/{id}', [PostController::class, 'show'], 'posts.show')
        ->middleware(['auth', 'csrf']);
    $router->get('/posts/{id}/edit', [PostController::class, 'edit'], 'posts.edit')
        ->middleware(['auth', 'csrf']);
    $router->put('/posts/{id}', [PostController::class, 'update'], 'posts.update')
        ->middleware(['auth', 'csrf']);
    $router->delete('/posts/{id}', [PostController::class, 'destroy'], 'posts.destroy')
        ->middleware(['auth', 'csrf']);

    // =========================================================================
    // User Management Routes (Admin Only - Protected)
    // =========================================================================

    $router->get('/users', [UserController::class, 'index'], 'users.index')
        ->middleware(['auth', 'csrf']);
    $router->get('/users/create', [UserController::class, 'create'], 'users.create')
        ->middleware(['auth', 'csrf']);
    $router->post('/users', [UserController::class, 'store'], 'users.store')
        ->middleware(['auth', 'csrf']);
    $router->get('/users/{id}', [UserController::class, 'show'], 'users.show')
        ->middleware(['auth', 'csrf']);
    $router->get('/users/{id}/edit', [UserController::class, 'edit'], 'users.edit')
        ->middleware(['auth', 'csrf']);
    $router->put('/users/{id}', [UserController::class, 'update'], 'users.update')
        ->middleware(['auth', 'csrf']);
    $router->delete('/users/{id}', [UserController::class, 'destroy'], 'users.destroy')
        ->middleware(['auth', 'csrf']);

    // =========================================================================
    // API Routes (Example - Token Authentication)
    // =========================================================================

    $router->get('/api/posts', [PostController::class, 'apiIndex'], 'api.posts.index')
        ->middleware(['api']);
    $router->get('/api/posts/{id}', [PostController::class, 'apiShow'], 'api.posts.show')
        ->middleware(['api']);
};
