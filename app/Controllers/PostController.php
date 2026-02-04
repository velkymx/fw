<?php

declare(strict_types=1);

namespace App\Controllers;

use Fw\Auth\Auth;
use Fw\Core\Controller;
use Fw\Core\Request;
use Fw\Core\Response;
use Fw\Support\Str;
use App\Models\Post;
use App\Models\User;

/**
 * Post controller demonstrating CRUD operations with authorization.
 *
 * Shows:
 * - Resource controller pattern (index, show, create, store, edit, update, destroy)
 * - Owner-based authorization
 * - Result/Option monad handling
 * - Flash messages for user feedback
 * - API endpoints alongside web endpoints
 * - Pagination
 */
class PostController extends Controller
{
    // =========================================================================
    // Web Endpoints
    // =========================================================================

    /**
     * List all published posts (public).
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int) $this->input($request, 'page', 1));
        $perPage = 10;

        // Get published posts with author relationship
        $pagination = Post::with('author')
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, $page);

        return $this->view('posts.index', [
            'posts' => $pagination['items'],
            'pagination' => $pagination,
        ]);
    }

    /**
     * Show a single post (public for published, auth required for drafts).
     */
    public function show(Request $request, string $id): Response
    {
        $post = Post::with('author')->find((int) $id);

        if ($post->isNone()) {
            return $this->notFound('Post not found.');
        }

        $post = $post->unwrap();
        $user = Auth::user();

        // Drafts can only be viewed by the author
        if ($post->isDraft() && !$post->canBeEditedBy($user)) {
            return $this->notFound('Post not found.');
        }

        return $this->view('posts.show', compact('post'));
    }

    /**
     * Show form to create a new post (auth required via middleware).
     */
    public function create(Request $request): Response
    {
        return $this->view('posts.create');
    }

    /**
     * Store a new post (auth required via middleware).
     */
    public function store(Request $request): Response
    {
        $validation = $this->validate($request, [
            'title' => 'required|min:3|max:200',
            'content' => 'required|min:10',
        ]);

        if ($validation->isErr()) {
            $_SESSION['_flash']['errors'] = $validation->getError();
            return $this->back();
        }

        $data = $validation->getValue();
        $user = Auth::user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        // Generate unique slug
        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (Post::where('slug', '=', $slug)->first()->isSome()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $post = new Post();
        $post->setAttribute('user_id', $user->getKey());
        $post->setAttribute('title', trim($data['title']));
        $post->setAttribute('slug', $slug);
        $post->setAttribute('content', $data['content']);

        // Handle publish checkbox
        if ($this->input($request, 'publish')) {
            $post->publish();
        }

        if (!$post->save()) {
            $_SESSION['_flash']['errors'] = ['title' => 'Failed to create post. Please try again.'];
            return $this->back();
        }

        $_SESSION['_flash']['success'] = $post->isPublished()
            ? 'Post published successfully!'
            : 'Post saved as draft.';

        return $this->redirect("/posts/{$post->getKey()}");
    }

    /**
     * Show form to edit a post (auth + ownership required).
     */
    public function edit(Request $request, string $id): Response
    {
        $post = Post::find((int) $id);

        if ($post->isNone()) {
            return $this->notFound('Post not found.');
        }

        $post = $post->unwrap();

        // Authorization: only the author can edit
        if (!$post->canBeEditedBy(Auth::user())) {
            return $this->forbidden('You are not authorized to edit this post.');
        }

        return $this->view('posts.edit', compact('post'));
    }

    /**
     * Update a post (auth + ownership required).
     */
    public function update(Request $request, string $id): Response
    {
        $post = Post::find((int) $id);

        if ($post->isNone()) {
            return $this->notFound('Post not found.');
        }

        $post = $post->unwrap();

        // Authorization: only the author can update
        if (!$post->canBeEditedBy(Auth::user())) {
            return $this->forbidden('You are not authorized to edit this post.');
        }

        $validation = $this->validate($request, [
            'title' => 'required|min:3|max:200',
            'content' => 'required|min:10',
        ]);

        if ($validation->isErr()) {
            $_SESSION['_flash']['errors'] = $validation->getError();
            return $this->back();
        }

        $data = $validation->getValue();
        $post->setAttribute('title', trim($data['title']));
        $post->setAttribute('content', $data['content']);

        // Handle publish/unpublish
        if ($this->input($request, 'publish') && $post->isDraft()) {
            $post->publish();
        } elseif (!$this->input($request, 'publish') && $post->isPublished()) {
            $post->unpublish();
        }

        if (!$post->save()) {
            $_SESSION['_flash']['errors'] = ['title' => 'Failed to update post. Please try again.'];
            return $this->back();
        }

        $_SESSION['_flash']['success'] = 'Post updated successfully!';
        return $this->redirect("/posts/{$post->getKey()}");
    }

    /**
     * Delete a post (auth + ownership required).
     */
    public function destroy(Request $request, string $id): Response
    {
        $post = Post::find((int) $id);

        if ($post->isNone()) {
            $_SESSION['_flash']['error'] = 'Post not found.';
            return $this->redirect('/posts');
        }

        $post = $post->unwrap();

        // Authorization: only the author can delete
        if (!$post->canBeEditedBy(Auth::user())) {
            return $this->forbidden('You are not authorized to delete this post.');
        }

        $post->delete();

        $_SESSION['_flash']['success'] = 'Post deleted successfully.';
        return $this->redirect('/posts');
    }

    // =========================================================================
    // API Endpoints
    // =========================================================================

    /**
     * API: List published posts.
     *
     * @return array<string, mixed>
     */
    public function apiIndex(Request $request): array
    {
        $page = max(1, (int) $this->input($request, 'page', 1));
        $perPage = min(50, max(1, (int) $this->input($request, 'per_page', 15)));

        $pagination = Post::published()
            ->with('author')
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, $page);

        return [
            'data' => array_map(fn($post) => $this->transformPost($post), $pagination['items']),
            'meta' => [
                'current_page' => $pagination['current_page'],
                'last_page' => $pagination['last_page'],
                'per_page' => $pagination['per_page'],
                'total' => $pagination['total'],
            ],
        ];
    }

    /**
     * API: Show a single published post.
     *
     * @return array<string, mixed>
     */
    public function apiShow(Request $request, string $id): array|Response
    {
        $post = Post::with('author')->find((int) $id);

        if ($post->isNone()) {
            return $this->json(['error' => 'Post not found'], 404);
        }

        $post = $post->unwrap();

        // Only show published posts via API
        if ($post->isDraft()) {
            return $this->json(['error' => 'Post not found'], 404);
        }

        return [
            'data' => $this->transformPost($post),
        ];
    }

    /**
     * Transform a post for API response.
     *
     * @return array<string, mixed>
     */
    private function transformPost(Post $post): array
    {
        $author = $post->author()->get()->unwrapOr(null);

        return [
            'id' => $post->getKey(),
            'title' => $post->getAttribute('title'),
            'content' => $post->getAttribute('content'),
            'excerpt' => $post->excerpt(200),
            'published_at' => $post->getAttribute('published_at'),
            'created_at' => $post->getAttribute('created_at'),
            'author' => $author ? [
                'id' => $author->getKey(),
                'name' => $author->getAttribute('name'),
            ] : null,
        ];
    }
}
