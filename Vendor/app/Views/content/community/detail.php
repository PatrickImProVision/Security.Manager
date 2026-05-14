<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1><?= esc((string) $post['title']) ?></h1>
<p class="lead">Community post detail.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <p>Post ID: <code><?= esc((string) $post['id']) ?></code></p>
    <p>Category: <code><?= esc((string) ($post['category'] ?? 'Unknown')) ?></code></p>
    <p>Status: <code><?= esc((string) $post['status']) ?></code></p>
    <p>Created by: <code><?= esc((string) ($post['author_name'] ?? 'Unknown')) ?></code></p>
    <?php if (! empty($post['created_at'])) : ?>
        <p>Created: <code><?= esc((string) $post['created_at']) ?></code></p>
    <?php endif ?>
    <?php if (! empty($post['updated_at'])) : ?>
        <p>Updated: <code><?= esc((string) $post['updated_at']) ?></code></p>
    <?php endif ?>

    <h2>Post</h2>
    <div class="content-body">
        <?= $bodyHtml ?? '' ?>
    </div>

    <div class="actions">
        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Index')) ?>">Back to posts</a>
        <?php if (! empty($canManage)) : ?>
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Edit/' . (int) $post['id'])) ?>">Edit post</a>
            <a class="btn btn-danger" href="<?= esc(site_url('Content/Community/Delete/' . (int) $post['id'])) ?>">Delete post</a>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
