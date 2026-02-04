<?php
/**
 * Post Detail View
 *
 * Displays a single post with author info and actions.
 * All output is properly escaped to prevent XSS.
 *
 * @var \App\Models\Post $post
 */

$title = $e($post->getAttribute('title') ?? 'Post');
$currentUser = \Fw\Auth\Auth::user();
$isAuthenticated = $currentUser !== null;
$canEdit = $post->canBeEditedBy($currentUser);
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/posts">Posts</a></li>
        <li class="breadcrumb-item active"><?= $e($post->getAttribute('title') ?? '') ?></li>
    </ol>
</nav>

<article class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h1 class="h3 mb-0"><?= $e($post->getAttribute('title') ?? '') ?></h1>
            <?php if ($post->isDraft()): ?>
                <span class="badge bg-secondary">Draft</span>
            <?php else: ?>
                <span class="badge bg-success">Published</span>
            <?php endif; ?>
        </div>

        <?php
        $author = $post->author()->get()->unwrapOr(null);
        $authorName = $author?->getAttribute('name') ?? 'Unknown';
        $authorId = $author?->getKey();
        ?>
        <p class="text-muted mb-4">
            By
            <?php if ($authorId): ?>
                <a href="/users/<?= $e((string) $authorId) ?>"><?= $e($authorName) ?></a>
            <?php else: ?>
                <?= $e($authorName) ?>
            <?php endif; ?>
            &bull;
            <?php if ($post->isPublished()): ?>
                Published <?= $formatDate($post->getAttribute('published_at')) ?>
            <?php else: ?>
                Created <?= $formatDate($post->getAttribute('created_at')) ?>
            <?php endif; ?>
            <?php if ($post->getAttribute('created_at')): ?>
                <small class="text-muted">(<?= $timeAgo($post->getAttribute('created_at')) ?>)</small>
            <?php endif; ?>
        </p>

        <div class="mb-4 post-content">
            <?= $post->getAttribute('content') ?? '' ?>
        </div>

        <?php if ($canEdit): ?>
            <hr>
            <div class="d-flex gap-2">
                <a href="/posts/<?= $e((string) $post->getKey()) ?>/edit" class="btn btn-primary">
                    Edit Post
                </a>
                <form method="POST" action="/posts/<?= $e((string) $post->getKey()) ?>" class="d-inline">
                    <?= $csrf() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-outline-danger"
                            onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                        Delete
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</article>

<?php if (!$isAuthenticated): ?>
    <div class="card mt-4 bg-light">
        <div class="card-body text-center">
            <p class="mb-2">Want to create your own posts?</p>
            <a href="/register" class="btn btn-primary">Create an Account</a>
            <span class="mx-2">or</span>
            <a href="/login" class="btn btn-outline-primary">Sign In</a>
        </div>
    </div>
<?php endif; ?>
