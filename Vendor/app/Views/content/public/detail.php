<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<article class="card wp-blog-single">
    <header class="wp-blog-single-header">
        <h1 class="wp-blog-single-title"><?= esc((string) ($content['title'] ?? 'Post')) ?></h1>
        <p class="wp-blog-post-meta wp-blog-single-meta">
            <?php if (! empty($content['date_label'])) : ?>
                <time datetime="<?= esc((string) ($content['published_at'] ?? $content['created_at'] ?? ''), 'attr') ?>"><?= esc((string) $content['date_label']) ?></time>
            <?php endif ?>
            <?php if (! empty($content['author_name'])) : ?>
                <span class="wp-blog-meta-sep">·</span>
                <span class="wp-blog-post-author">by <?= esc((string) $content['author_name']) ?></span>
            <?php endif ?>
            <?php if (! empty($canManage) && empty($content['is_published'])) : ?>
                <span class="wp-blog-status-pill">Draft</span>
            <?php endif ?>
        </p>
    </header>

    <div class="wp-blog-single-body content-body">
        <?= $bodyHtml ?? '' ?>
    </div>

    <footer class="wp-blog-single-footer actions">
        <?php if (! empty($canManage)) : ?>
            <a class="btn btn-primary" href="<?= esc(\App\Libraries\PublicContentUrls::editUrl($content)) ?>">Edit post</a>
            <a class="btn btn-danger" href="<?= esc(\App\Libraries\PublicContentUrls::deleteUrl($content)) ?>">Delete</a>
        <?php endif ?>
    </footer>
</article>
<?= $this->endSection() ?>
