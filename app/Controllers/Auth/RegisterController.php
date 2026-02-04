<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use Fw\Auth\Auth;
use Fw\Core\Controller;
use Fw\Core\Request;
use Fw\Core\Response;
use App\Models\User;

/**
 * Handles user registration.
 *
 * Demonstrates:
 * - Input validation with confirmation
 * - Unique email checking
 * - Proper Auth integration
 * - Flash messages
 */
class RegisterController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('auth.register');
    }

    public function register(Request $request): Response
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

        // Check if email already exists (case-insensitive)
        $existing = User::where('email', strtolower($data['email']))->first();
        if ($existing->isSome()) {
            return $this->back()->withErrors(['email' => 'This email is already registered.']);
        }

        // Create the user
        $user = new User();
        $user->setAttribute('email', strtolower($data['email']));
        $user->setAttribute('name', trim($data['name']));
        $user->setPassword($data['password']);

        $result = $user->save();

        if ($result->isErr()) {
            return $this->back()->withErrors(['email' => 'Registration failed. Please try again.']);
        }

        // Log the user in using the secure Auth class
        Auth::login($user);

        return $this->redirect('/')->with('success', 'Welcome! Your account has been created.');
    }
}
