<?php
/**
 * Login View
 *
 * User authentication form.
 */

$title = 'Sign In';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-4 text-center">Sign In</h1>

                <form method="POST" action="/login">
                    <?= $csrf() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               required
                               autocomplete="email"
                               autofocus
                               value="<?= $e($old('email') ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               required
                               minlength="8"
                               autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="remember"
                                   value="1"
                                   id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Sign In</button>
                    </div>
                </form>

                <hr class="my-4">

                <p class="text-center mb-0">
                    Don't have an account? <a href="/register">Create one</a>
                </p>
            </div>
        </div>
    </div>
</div>
