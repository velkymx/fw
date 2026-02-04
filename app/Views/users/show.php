<?php
/**
 * User Profile View
 *
 * Shows user details and their published posts.
 *
 * @var \App\Models\User $user
 * @var array $posts User's recent published posts
 */

$title = $e($user->getAttribute('name') ?? 'User Profile');
$userId = (string) $user->getKey();
$currentUser = \Fw\Auth\Auth::user();
$isOwnProfile = $currentUser && $currentUser->getKey() === $user->getKey();
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/users">Users</a></li>
        <li class="breadcrumb-item active"><?= $e($user->getAttribute('name') ?? '') ?></li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                         style="width: 80px; height: 80px; font-size: 2rem;">
                        <?= $e(strtoupper(substr($user->getAttribute('name') ?? 'U', 0, 1))) ?>
                    </div>
                </div>
                <h1 class="h4 mb-1"><?= $e($user->getAttribute('name') ?? '') ?></h1>
                <p class="text-muted mb-3"><?= $e((string) ($user->getAttribute('email') ?? '')) ?></p>

                <dl class="text-start">
                    <dt class="text-muted small">Member Since</dt>
                    <dd><?= $formatDate($user->getAttribute('created_at')) ?></dd>
                </dl>

                <?php if ($isOwnProfile): ?>
                    <div class="d-grid gap-2">
                        <a href="/users/<?= $e($userId) ?>/edit" class="btn btn-primary">Edit Profile</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent">
                <h2 class="h5 mb-0">
                    <?= $isOwnProfile ? 'Your Posts' : 'Published Posts' ?>
                </h2>
            </div>
            <div class="card-body">
                <?php if (!empty($posts)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($posts as $post): ?>
                            <a href="/posts/<?= $e((string) $post->getKey()) ?>"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0"><?= $e($post->getAttribute('title') ?? '') ?></h6>
                                    <small class="text-muted"><?= $formatDate($post->getAttribute('published_at')) ?></small>
                                </div>
                                <?php if ($post->isDraft()): ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">
                        <?= $isOwnProfile ? "You haven't published any posts yet." : "This user hasn't published any posts yet." ?>
                    </p>
                    <?php if ($isOwnProfile): ?>
                        <a href="/posts/create" class="btn btn-primary mt-3">Create Your First Post</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isOwnProfile): ?>
            <div class="card mt-4 border-danger">
                <div class="card-header bg-danger text-white">
                    Danger Zone
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Permanently delete your account and all associated data. This action cannot be undone.
                    </p>
                    <form method="POST" action="/users/<?= $e($userId) ?>">
                        <?= $csrf() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit"
                                class="btn btn-outline-danger"
                                onclick="return confirm('Are you sure you want to delete your account? All your posts will be deleted. This action cannot be undone.')">
                            Delete My Account
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
