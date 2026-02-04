<?php
/**
 * Users List View
 *
 * Shows paginated list of all users.
 *
 * @var array $users Array of User models
 * @var array $pagination Pagination metadata
 */

$title = 'Users';
$currentUser = \Fw\Auth\Auth::user();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Users</h1>
    <a href="/users/create" class="btn btn-primary">Add User</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <?php $isCurrentUser = $currentUser && $currentUser->getKey() === $user->getKey(); ?>
                        <tr>
                            <td>
                                <a href="/users/<?= $e((string) $user->getKey()) ?>" class="text-decoration-none">
                                    <?= $e($user->getAttribute('name') ?? '') ?>
                                </a>
                                <?php if ($isCurrentUser): ?>
                                    <span class="badge bg-primary ms-1">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $e((string) ($user->getAttribute('email') ?? '')) ?></td>
                            <td><?= $formatDate($user->getAttribute('created_at')) ?></td>
                            <td class="text-end">
                                <a href="/users/<?= $e((string) $user->getKey()) ?>" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                                <?php if ($isCurrentUser): ?>
                                    <a href="/users/<?= $e((string) $user->getKey()) ?>/edit" class="btn btn-sm btn-outline-secondary">
                                        Edit
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($pagination) && $pagination['last_page'] > 1): ?>
    <nav aria-label="Users pagination" class="mt-4">
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
            Showing <?= count($users) ?> of <?= $pagination['total'] ?> users
        </p>
    </nav>
<?php endif; ?>
