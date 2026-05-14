<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1><?= esc((string) $content['title']) ?></h1>
<p class="lead">Public content detail.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <p>Slug: <code><?= esc((string) $content['slug']) ?></code></p>
    <p>Status: <code><?= esc((string) $content['status']) ?></code></p>
    <?php if (! empty($content['published_at'])) : ?>
        <p>Published: <code><?= esc((string) $content['published_at']) ?></code></p>
    <?php endif ?>
    <?php if (! empty($content['updated_at'])) : ?>
        <p>Updated: <code><?= esc((string) $content['updated_at']) ?></code></p>
    <?php endif ?>

    <?php if (trim((string) ($content['summary'] ?? '')) !== '') : ?>
        <h2>SEO Description</h2>
        <p><?= esc((string) $content['summary']) ?></p>
    <?php endif ?>

    <h2>Content</h2>
    <div class="content-body">
        <?= $bodyHtml ?? '' ?>
    </div>

    <div class="actions">
        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Public/Index')) ?>">Back to contents</a>
        <?php if (! empty($canManage)) : ?>
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Public/Edit/' . (int) $content['id'])) ?>">Edit content</a>
            <a class="btn btn-danger" href="<?= esc(site_url('Content/Public/Delete/' . (int) $content['id'])) ?>">Delete content</a>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
