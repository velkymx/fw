<?php
/**
 * Create User View
 *
 * Form for creating a new user account.
 */

$title = 'Add User';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/users">Users</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h3 mb-4">Add New User</h1>

                <form method="POST" action="/users">
                    <?= $csrf() ?>

                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="name"
                               name="name"
                               required
                               minlength="2"
                               maxlength="100"
                               value="<?= $e($old('name') ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               required
                               value="<?= $e($old('email') ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               required
                               minlength="8"
                               autocomplete="new-password">
                        <div class="form-text">At least 8 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password"
                               class="form-control"
                               id="password_confirmation"
                               name="password_confirmation"
                               required
                               minlength="8"
                               autocomplete="new-password">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create User</button>
                        <a href="/users" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
