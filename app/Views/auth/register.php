<?php
/**
 * Registration View
 *
 * New user registration form with password confirmation.
 */

$title = 'Create Account';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-4 text-center">Create Account</h1>

                <form method="POST" action="/register">
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
                               autocomplete="name"
                               autofocus
                               value="<?= $e($old('name') ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               required
                               autocomplete="email"
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

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </div>
                </form>

                <hr class="my-4">

                <p class="text-center mb-0">
                    Already have an account? <a href="/login">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>
