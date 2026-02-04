<?php

declare(strict_types=1);

namespace Examples\User;

use Fw\Async\AsyncDatabase;
use Fw\Lifecycle\Component;

/**
 * Component-based user profile page with async data fetching.
 *
 * Demonstrates:
 * - Full lifecycle hooks
 * - Async data fetching with Fibers
 * - Multiple concurrent database queries
 * - Error handling
 *
 * This is an alternative to traditional controllers when you need:
 * - Fine-grained lifecycle control
 * - Async data fetching
 * - Component-based architecture
 */
final class UserProfileComponent extends Component
{
    private ?array $user = null;
    private array $posts = [];
    private array $stats = [];

    /**
     * Called after all services are ready.
     */
    public function booted(): void
    {
        $this->set('title', 'User Profile');
    }

    /**
     * Validate request before processing.
     */
    public function beforeRequest(): void
    {
        $userId = $this->param('id');

        if (!$userId) {
            $this->abort(400, 'User ID is required');
        }
    }

    /**
     * Fetch user data asynchronously.
     *
     * This is where the Fiber magic happens - multiple queries
     * are initiated and the Fiber suspends while waiting for results.
     */
    public function fetch(): void
    {
        $userId = $this->param('id');
        $db = new AsyncDatabase($this->app->db);

        // Start all queries - they run concurrently!
        $userDeferred = $db->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$userId]
        );

        $postsDeferred = $db->fetchAll(
            'SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
            [$userId]
        );

        $statsDeferred = $db->fetchOne(
            'SELECT COUNT(*) as post_count, MAX(created_at) as last_post FROM posts WHERE user_id = ?',
            [$userId]
        );

        // Await all results - Fiber suspends until data is ready
        $this->user = $this->await($userDeferred);
        $this->posts = $this->await($postsDeferred);
        $this->stats = $this->await($statsDeferred) ?? [];

        // Check if user exists
        if ($this->user === null) {
            $this->abort(404, 'User not found');
        }
    }

    /**
     * Process data after fetching.
     */
    public function afterFetch(): void
    {
        // Transform data for the view
        $this->set('user', $this->user);
        $this->set('posts', $this->posts);
        $this->set('stats', [
            'post_count' => $this->stats['post_count'] ?? 0,
            'last_post' => $this->stats['last_post'] ?? null,
        ]);

        // Log data fetch timing
        $this->app->log->info('Profile data loaded', [
            'user_id' => $this->user['id'] ?? null,
            'post_count' => count($this->posts),
        ]);
    }

    /**
     * Render the profile page.
     */
    public function render(): string
    {
        return $this->view('users/profile', [
            'title' => $this->get('title'),
            'user' => $this->get('user'),
            'posts' => $this->get('posts'),
            'stats' => $this->get('stats'),
        ]);
    }

    /**
     * Cleanup after response is sent.
     */
    public function afterResponse(): void
    {
        // Clear data to free memory
        $this->user = null;
        $this->posts = [];
        $this->stats = [];
        $this->clear();
    }

    /**
     * Handle errors during lifecycle.
     */
    public function error(\Throwable $e): void
    {
        parent::error($e);

        $this->app->log->error('Profile error', [
            'error' => $e->getMessage(),
            'user_id' => $this->param('id'),
        ]);
    }
}
