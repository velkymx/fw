<?php
/**
 * Posts List View
 *
 * Shows paginated list of published posts.
 *
 * @var array $posts Array of Post models
 * @var array $pagination Pagination metadata
 */

$title = 'Posts';
$currentUser = \Fw\Auth\Auth::user();
$isAuthenticated = $currentUser !== null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Posts</h1>
    <?php if ($isAuthenticated): ?>
        <a href="/posts/create" class="btn btn-primary">New Post</a>
    <?php endif; ?>
</div>

<?php if (empty($posts)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <p class="text-muted mb-3">No posts yet.</p>
            <?php if ($isAuthenticated): ?>
                <a href="/posts/create" class="btn btn-primary">Create the First Post</a>
            <?php else: ?>
                <a href="/login" class="btn btn-outline-primary">Sign in to create a post</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row row-cols-1 gap-3">
        <?php foreach ($posts as $post): ?>
            <?php
            $author = $post->author()->get()->unwrapOr(null);
            $authorName = $author?->getAttribute('name') ?? 'Unknown';
            ?>
            <div class="col">
                <article class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h2 class="h5 card-title mb-1">
                                <a href="/posts/<?= $e((string) $post->getKey()) ?>" class="text-decoration-none">
                                    <?= $e($post->getAttribute('title') ?? '') ?>
                                </a>
                            </h2>
                            <?php if ($post->isDraft()): ?>
                                <span class="badge bg-secondary">Draft</span>
                            <?php endif; ?>
                        </div>
                        <p class="card-text text-muted small mb-2">
                            By <?= $e($authorName) ?>
                            &bull; <?= $formatDate($post->getAttribute('published_at') ?? $post->getAttribute('created_at')) ?>
                        </p>
                        <p class="card-text"><?= $e($post->excerpt(200)) ?></p>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="/posts/<?= $e((string) $post->getKey()) ?>" class="btn btn-sm btn-outline-primary">
                            Read More
                        </a>
                        <?php if ($post->canBeEditedBy($currentUser)): ?>
                            <a href="/posts/<?= $e((string) $post->getKey()) ?>/edit" class="btn btn-sm btn-outline-secondary">
                                Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (isset($pagination) && $pagination['last_page'] > 1): ?>
        <nav aria-label="Posts pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($pagination['current_page'] > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $pagination['current_page'] - 1 ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                    <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $pagination['current_page'] + 1 ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
            <p class="text-center text-muted small">
                Showing <?= count($posts) ?> of <?= $pagination['total'] ?> posts
            </p>
        </nav>
    <?php endif; ?>
<?php endif; ?>
