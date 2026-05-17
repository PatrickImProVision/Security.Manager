<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? 'Blog'),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="wp-blog-layout wp-blog-layout-index">
    <main class="card wp-blog-main">
        <?php if (! empty($canManage)) : ?>
            <div class="wp-blog-admin-bar">
                <a class="btn btn-primary" href="<?= esc(site_url('Content/Public/Create')) ?>">Add New Post</a>
            </div>
        <?php endif ?>

        <?php
        $posts = is_array($posts ?? null) ? $posts : [];
        $pagination = is_array($pagination ?? null) ? $pagination : [];
        $page = (int) ($pagination['page'] ?? 1);
        $totalPages = (int) ($pagination['totalPages'] ?? 1);
        $pageUrl = static fn (int $p): string => site_url('Content/Public/Index') . '?page=' . $p;
        ?>

        <?php if ($posts === []) : ?>
            <div class="wp-blog-empty">
                <p>No posts have been published yet.</p>
            </div>
        <?php else : ?>
            <div class="wp-blog-feed">
                <?php foreach ($posts as $post) : ?>
                    <article class="wp-blog-post<?= empty($post['is_published']) ? ' wp-blog-post-draft' : '' ?>">
                        <header class="wp-blog-post-header">
                            <h2 class="wp-blog-post-title">
                                <a href="<?= esc((string) ($post['post_url'] ?? '#')) ?>"><?= esc((string) ($post['title'] ?? 'Untitled')) ?></a>
                            </h2>
                            <p class="wp-blog-post-meta">
                                <?php if (! empty($post['date_label'])) : ?>
                                    <time datetime="<?= esc((string) ($post['published_at'] ?? $post['created_at'] ?? ''), 'attr') ?>"><?= esc((string) $post['date_label']) ?></time>
                                <?php endif ?>
                                <?php if (! empty($post['author_name'])) : ?>
                                    <span class="wp-blog-meta-sep">·</span>
                                    <span class="wp-blog-post-author">by <?= esc((string) $post['author_name']) ?></span>
                                <?php endif ?>
                                <?php if (empty($post['is_published'])) : ?>
                                    <span class="wp-blog-status-pill">Draft</span>
                                <?php endif ?>
                            </p>
                        </header>
                        <?php if (trim((string) ($post['excerpt_html'] ?? '')) !== '') : ?>
                            <div class="wp-blog-post-excerpt content-body">
                                <?= $post['excerpt_html'] ?>
                            </div>
                        <?php endif ?>
                        <footer class="wp-blog-post-footer">
                            <a class="wp-blog-read-more" href="<?= esc((string) ($post['post_url'] ?? '#')) ?>">Continue reading <span aria-hidden="true">→</span></a>
                            <?php if (! empty($canManage)) : ?>
                                <span class="wp-blog-post-admin">
                                    <a class="btn btn-secondary btn-sm" href="<?= esc(\App\Libraries\PublicContentUrls::editUrl($post)) ?>">Edit</a>
                                </span>
                            <?php endif ?>
                        </footer>
                    </article>
                <?php endforeach ?>
            </div>

            <?php if ($totalPages > 1) : ?>
                <nav class="wp-blog-pagination" aria-label="Blog pagination">
                    <?php if ($page < $totalPages) : ?>
                        <a class="wp-blog-nav-older" href="<?= esc($pageUrl($page + 1)) ?>">← Older posts</a>
                    <?php else : ?>
                        <span class="wp-blog-nav-older wp-blog-nav-disabled">← Older posts</span>
                    <?php endif ?>
                    <span class="wp-blog-page-indicator">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
                    <?php if ($page > 1) : ?>
                        <a class="wp-blog-nav-newer" href="<?= esc($pageUrl($page - 1)) ?>">Newer posts →</a>
                    <?php else : ?>
                        <span class="wp-blog-nav-newer wp-blog-nav-disabled">Newer posts →</span>
                    <?php endif ?>
                </nav>
            <?php endif ?>
        <?php endif ?>
    </main>

    <aside class="wp-blog-sidebar">
        <section class="card wp-blog-widget">
            <h2 class="wp-blog-widget-title">Recent Posts</h2>
            <?php
            $recentPosts = is_array($recentPosts ?? null) ? $recentPosts : [];
            ?>
            <?php if ($recentPosts === []) : ?>
                <p class="hint">No posts yet.</p>
            <?php else : ?>
                <ul class="wp-blog-recent-list">
                    <?php foreach ($recentPosts as $recent) : ?>
                        <li>
                            <a href="<?= esc((string) ($recent['post_url'] ?? '#')) ?>"><?= esc((string) ($recent['title'] ?? 'Post')) ?></a>
                            <?php if (! empty($recent['date_label'])) : ?>
                                <span class="wp-blog-recent-date"><?= esc((string) $recent['date_label']) ?></span>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </aside>
</div>
<?= $this->endSection() ?>
