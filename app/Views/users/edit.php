<?php
/**
 * Edit User Profile View
 *
 * Form for editing user profile information.
 *
 * @var \App\Models\User $user
 */

$title = 'Account Settings';
$userId = (string) $user->getKey();
$userName = $user->getAttribute('name') ?? '';
$userEmail = (string) ($user->getAttribute('email') ?? '');
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/users">Users</a></li>
        <li class="breadcrumb-item"><a href="/users/<?= $e($userId) ?>"><?= $e($userName) ?></a></li>
        <li class="breadcrumb-item active">Settings</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-transparent">
                <h1 class="h5 mb-0">Profile Information</h1>
            </div>
            <div class="card-body">
                <form method="POST" action="/users/<?= $e($userId) ?>">
                    <?= $csrf() ?>
                    <input type="hidden" name="_method" value="PUT">

                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="name"
                               name="name"
                               required
                               minlength="2"
                               maxlength="100"
                               value="<?= $e($old('name') ?? $userName) ?>">
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               required
                               value="<?= $e($old('email') ?? $userEmail) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-transparent">
                <h2 class="h5 mb-0">Change Password</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="/users/<?= $e($userId) ?>">
                    <?= $csrf() ?>
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="name" value="<?= $e($userName) ?>">
                    <input type="hidden" name="email" value="<?= $e($userEmail) ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               minlength="8"
                               autocomplete="new-password">
                        <div class="form-text">Leave blank to keep your current password. Minimum 8 characters.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h3 class="h6 card-title">Account Info</h3>
                <dl class="small mb-0">
                    <dt>Member Since</dt>
                    <dd><?= $formatDate($user->getAttribute('created_at')) ?></dd>
                    <?php if ($user->getAttribute('updated_at')): ?>
                        <dt>Last Updated</dt>
                        <dd><?= $formatDate($user->getAttribute('updated_at')) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <a href="/users/<?= $e($userId) ?>" class="btn btn-outline-secondary w-100">
                    View Profile
                </a>
            </div>
        </div>
    </div>
</div>
