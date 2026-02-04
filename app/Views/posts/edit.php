<?php
/**
 * Edit Post View
 *
 * Form for editing an existing post.
 *
 * @var \App\Models\Post $post
 */

$title = 'Edit Post';
$postId = (string) $post->getKey();
$postTitle = $post->getAttribute('title') ?? '';
$postContent = $post->getAttribute('content') ?? '';
$isPublished = $post->isPublished();
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/posts">Posts</a></li>
        <li class="breadcrumb-item"><a href="/posts/<?= $e($postId) ?>"><?= $e($postTitle) ?></a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Edit Post</h1>
                    <?php if ($isPublished): ?>
                        <span class="badge bg-success">Published</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Draft</span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="/posts/<?= $e($postId) ?>" id="post-form">
                    <?= $csrf() ?>
                    <input type="hidden" name="_method" value="PUT">

                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="title"
                               name="title"
                               required
                               minlength="3"
                               maxlength="200"
                               value="<?= $e($old('title') ?? $postTitle) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <div id="editor" style="height: 300px;"><?= $old('content') ?? $postContent ?></div>
                        <input type="hidden" name="content" id="content">
                        <div class="form-text">At least 10 characters.</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="publish"
                                   value="1"
                                   id="publish"
                                   <?= ($old('publish') !== null ? $old('publish') : $isPublished) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="publish">
                                Published
                            </label>
                        </div>
                        <div class="form-text">Uncheck to revert to draft status.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Post</button>
                        <a href="/posts/<?= $e($postId) ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                Danger Zone
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Permanently delete this post. This action cannot be undone.</p>
                <form method="POST" action="/posts/<?= $e($postId) ?>">
                    <?= $csrf() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit"
                            class="btn btn-outline-danger w-100"
                            onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                        Delete Post
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3 bg-light border-0">
            <div class="card-body">
                <h3 class="h6 card-title">Post Info</h3>
                <dl class="small mb-0">
                    <dt>Created</dt>
                    <dd><?= $formatDate($post->getAttribute('created_at')) ?></dd>
                    <?php if ($post->getAttribute('updated_at')): ?>
                        <dt>Last Updated</dt>
                        <dd><?= $formatDate($post->getAttribute('updated_at')) ?></dd>
                    <?php endif; ?>
                    <?php if ($isPublished): ?>
                        <dt>Published</dt>
                        <dd><?= $formatDate($post->getAttribute('published_at')) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
    const quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['blockquote', 'code-block'],
                ['link'],
                ['clean']
            ]
        }
    });

    document.getElementById('post-form').addEventListener('submit', function() {
        document.getElementById('content').value = quill.root.innerHTML;
    });
</script>
