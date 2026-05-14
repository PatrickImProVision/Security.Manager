<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Delete Community Post</h1>
<p class="lead">Confirm deletion of this community post.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <h2><?= esc((string) $post['title']) ?></h2>
    <p>Post ID: <code><?= esc((string) $post['id']) ?></code></p>
    <p>Status: <code><?= esc((string) $post['status']) ?></code></p>

    <form method="post" action="<?= esc(site_url('Content/Community/Delete/' . (int) $post['id'])) ?>">
        <?= csrf_field() ?>
        <div class="actions">
            <button type="submit" class="btn btn-danger">Delete post</button>
            <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/View/' . (int) $post['id'])) ?>">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
