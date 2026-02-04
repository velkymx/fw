<?php

declare(strict_types=1);

namespace App\Controllers;

use Fw\Auth\Auth;
use Fw\Core\Controller;
use Fw\Core\Request;
use Fw\Core\Response;
use App\Models\Post;
use App\Models\User;

/**
 * Home controller - landing page and utility endpoints.
 *
 * Demonstrates:
 * - View rendering with data
 * - Health check endpoint for monitoring
 * - Using Auth for user info
 */
class HomeController extends Controller
{
    /**
     * Homepage showing recent posts.
     */
    public function index(Request $request): Response
    {
        // Get recent published posts
        $posts = Post::published()
            ->with('author')
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

        // Get current user if logged in
        $user = Auth::user();

        // Get user's draft count if logged in
        $draftCount = 0;
        if ($user !== null) {
            $draftCount = Post::drafts()
                ->where('user_id', '=', $user->getKey())
                ->count();
        }

        return $this->view('home.index', [
            'posts' => $posts,
            'user' => $user,
            'draftCount' => $draftCount,
        ]);
    }

    /**
     * About page showcasing the framework.
     */
    public function about(Request $request): Response
    {
        return $this->view('home.about');
    }

    /**
     * Health check endpoint for load balancers/monitoring.
     *
     * Returns a simple 200 OK response with minimal overhead.
     */
    public function health(Request $request): Response
    {
        return $this->json([
            'status' => 'healthy',
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Dashboard redirect - sends users to posts list.
     */
    public function dashboard(Request $request): Response
    {
        return $this->redirect('/posts');
    }
}
