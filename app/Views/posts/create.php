<?php
/**
 * Create Post View
 *
 * Form for creating a new post.
 */

$title = 'Create Post';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/posts">Posts</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h3 mb-4">Create New Post</h1>

                <form method="POST" action="/posts" id="post-form">
                    <?= $csrf() ?>

                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="title"
                               name="title"
                               required
                               minlength="3"
                               maxlength="200"
                               placeholder="Enter a descriptive title"
                               value="<?= $e($old('title') ?? '') ?>">
                        <div class="form-text">At least 3 characters.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <div id="editor" style="height: 300px;"><?= $old('content') ?? '' ?></div>
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
                                   <?= $old('publish') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="publish">
                                Publish immediately
                            </label>
                        </div>
                        <div class="form-text">Leave unchecked to save as draft.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Post</button>
                        <a href="/posts" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h3 class="h6 card-title">Tips</h3>
                <ul class="small text-muted mb-0">
                    <li class="mb-2">Use a clear, descriptive title that summarizes your post.</li>
                    <li class="mb-2">Break up long content with paragraphs for better readability.</li>
                    <li class="mb-2">Save as draft first to review before publishing.</li>
                    <li>You can edit or delete your post anytime after creation.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
    const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Write your post content here...',
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

    document.getElementById('post-form').addEventListener('submit', function(e) {
        var content = quill.root.innerHTML;
        // Don't submit if Quill only has empty paragraph
        if (content === '<p><br></p>' || content.trim() === '') {
            e.preventDefault();
            alert('Please enter some content');
            return false;
        }
        document.getElementById('content').value = content;
    });
</script>
