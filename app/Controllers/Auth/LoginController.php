<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use Fw\Auth\Auth;
use Fw\Core\Controller;
use Fw\Core\Request;
use Fw\Core\Response;

/**
 * Handles user authentication (login/logout).
 *
 * Uses the framework's Auth class which provides:
 * - Session fixation protection (regenerates session ID)
 * - CSRF token regeneration on login
 * - Timing-safe password verification
 * - Remember me functionality
 */
class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('auth.login');
    }

    public function login(Request $request): Response
    {
        $validation = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($validation->isErr()) {
            return $this->back()->withErrors($validation->getError());
        }

        $data = $validation->getValue();
        $remember = (bool) $this->input($request, 'remember', false);

        // Auth::attempt handles:
        // - User lookup with timing-safe comparison
        // - Session regeneration (prevents session fixation)
        // - CSRF token regeneration (prevents CSRF token fixation)
        // - Remember me token generation
        if (!Auth::attempt($data['email'], $data['password'], $remember)) {
            return $this->back()->withErrors(['email' => 'Invalid email or password.']);
        }

        // Redirect to intended URL or dashboard
        $intended = $_SESSION['_intended_url'] ?? '/';
        unset($_SESSION['_intended_url']);

        return $this->redirect($intended)->with('success', 'Welcome back!');
    }

    public function logout(Request $request): Response
    {
        // Auth::logout handles:
        // - Clearing session data
        // - Invalidating remember token
        // - Regenerating session ID
        Auth::logout();

        return $this->redirect('/login')->with('success', 'You have been logged out.');
    }
}
