<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <h2>Delete Public Content</h2>
    <p><strong><?= esc((string) $content['title']) ?></strong></p>
    <p>Slug: <code><?= esc((string) $content['slug']) ?></code></p>
    <p>Status: <code><?= esc((string) $content['status']) ?></code></p>
    <?php if (trim((string) ($content['summary'] ?? '')) !== '') : ?>
        <p>SEO Description: <?= esc((string) $content['summary']) ?></p>
    <?php endif ?>

    <form method="post" action="<?= esc(\App\Libraries\PublicContentUrls::deleteUrl($content)) ?>">
        <?= csrf_field() ?>
        <div class="actions">
            <button type="submit" class="btn btn-danger">Delete content</button>
            <a class="btn btn-secondary" href="<?= esc(\App\Libraries\PublicContentUrls::postUrl($content)) ?>">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
