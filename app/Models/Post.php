<?php

declare(strict_types=1);

namespace App\Models;

use Fw\Model\Model;
use Fw\Model\ModelQueryBuilder;
use Fw\Model\BelongsTo;

/**
 * Post model representing blog posts.
 *
 * Demonstrates:
 * - BelongsTo relationship (author)
 * - Query scopes (published, draft, byAuthor)
 * - DateTime casting
 * - Computed properties (isPublished, excerpt)
 */
class Post extends Model
{
    protected static ?string $table = 'posts';

    protected static array $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'published_at',
    ];

    protected static array $casts = [
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the post's author.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // =========================================================================
    // Query Scopes
    // =========================================================================

    /**
     * Scope: Only published posts.
     */
    public static function published(): ModelQueryBuilder
    {
        return static::whereNotNull('published_at')
            ->where('published_at', '<=', date('Y-m-d H:i:s'));
    }

    /**
     * Scope: Only draft (unpublished) posts.
     */
    public static function drafts(): ModelQueryBuilder
    {
        return static::whereNull('published_at');
    }

    /**
     * Scope: Posts by a specific author.
     */
    public static function byAuthor(int $userId): ModelQueryBuilder
    {
        return static::where('user_id', '=', $userId);
    }

    /**
     * Scope: Recent posts (last N days).
     */
    public static function recent(int $days = 30): ModelQueryBuilder
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return static::published()
            ->where('published_at', '>=', $since)
            ->orderBy('published_at', 'desc');
    }

    // =========================================================================
    // Computed Properties
    // =========================================================================

    /**
     * Check if the post is published.
     */
    public function isPublished(): bool
    {
        $publishedAt = $this->getAttribute('published_at');

        if ($publishedAt === null) {
            return false;
        }

        return $publishedAt <= new \DateTimeImmutable();
    }

    /**
     * Check if the post is a draft.
     */
    public function isDraft(): bool
    {
        return !$this->isPublished();
    }

    /**
     * Get an excerpt of the post content.
     */
    public function excerpt(int $length = 150): string
    {
        $content = strip_tags($this->getAttribute('content') ?? '');
        if (mb_strlen($content) <= $length) {
            return $content;
        }
        return mb_substr($content, 0, $length) . '...';
    }

    // =========================================================================
    // Actions
    // =========================================================================

    /**
     * Publish the post.
     */
    public function publish(): static
    {
        $this->setAttribute('published_at', date('Y-m-d H:i:s'));
        return $this;
    }

    /**
     * Unpublish (draft) the post.
     */
    public function unpublish(): static
    {
        $this->setAttribute('published_at', null);
        return $this;
    }

    /**
     * Check if the given user can edit this post.
     */
    public function canBeEditedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        return $this->getAttribute('user_id') === $user->getKey();
    }
}
