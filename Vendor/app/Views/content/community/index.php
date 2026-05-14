<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <div class="role-list-head">
        <h2>Posts</h2>
        <div class="role-list-head-actions">
            <?php if (! empty($canManageCategories)) : ?>
                <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Categories/Index')) ?>">Manage Categories</a>
            <?php endif ?>
            <?php if (! empty($canCreate)) : ?>
                <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Create')) ?>">Create Post</a>
            <?php endif ?>
        </div>
    </div>

    <?php if (empty($posts)) : ?>
        <p>No community posts have been created yet.</p>
    <?php else : ?>
        <div class="content-list-table-wrap">
            <table class="content-list-table">
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Status</th>
                        <th>Author</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $currentCategory = null; ?>
                    <?php foreach ($posts as $post) : ?>
                        <?php
                        $category = (string) (($post['category'] ?? '') ?: 'Unknown');
                        $canManagePost = is_array($current ?? null)
                            && ((bool) session()->get('member_can_manage_roles') || (int) ($post['author_id'] ?? 0) === (int) ($current['id'] ?? 0));
                        ?>
                        <?php if ($category !== $currentCategory) : ?>
                            <?php $currentCategory = $category; ?>
                            <tr class="content-list-group-row">
                                <td colspan="5"><span><?= esc($category) ?></span></td>
                            </tr>
                        <?php endif ?>
                        <tr>
                            <td class="content-list-main">
                                <strong><?= esc((string) $post['title']) ?></strong>
                                <p><code>#<?= esc((string) $post['id']) ?></code></p>
                            </td>
                            <td class="content-list-status">
                                <span class="status-pill <?= (string) ($post['status'] ?? '') === 'published' ? 'status-active' : 'status-inactive' ?>">
                                    <?= esc(ucfirst((string) ($post['status'] ?? 'draft'))) ?>
                                </span>
                            </td>
                            <td class="content-list-nav">
                                <code><?= esc((string) ($post['author_name'] ?? 'Unknown')) ?></code>
                            </td>
                            <td class="content-list-nav">
                                <code><?= esc((string) ($post['created_at'] ?? '-')) ?></code>
                            </td>
                            <td>
                                <div class="content-list-actions">
                                    <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/View/' . (int) $post['id'])) ?>">View</a>
                                    <?php if ($canManagePost) : ?>
                                        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Edit/' . (int) $post['id'])) ?>">Edit</a>
                                        <a class="btn btn-danger" href="<?= esc(site_url('Content/Community/Delete/' . (int) $post['id'])) ?>">Delete</a>
                                    <?php endif ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php
        $pagination = is_array($pagination ?? null) ? $pagination : [];
        $page = (int) ($pagination['page'] ?? 1);
        $totalPages = (int) ($pagination['totalPages'] ?? 1);
        $total = (int) ($pagination['total'] ?? count($posts));
        $perPage = (int) ($pagination['perPage'] ?? count($posts));
        $start = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $end = $total > 0 ? min($total, $page * $perPage) : 0;
        $pageUrl = static fn (int $p): string => site_url('Content/Community/Index') . '?page=' . $p;
        ?>
        <div class="content-pagination">
            <span>Showing <?= esc((string) $start) ?>-<?= esc((string) $end) ?> of <?= esc((string) $total) ?></span>
            <?php if ($totalPages > 1) : ?>
                <nav class="pagination-links" aria-label="Community content pagination">
                    <a class="btn btn-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= esc($page <= 1 ? '#' : $pageUrl($page - 1)) ?>">Previous</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                        <a class="btn btn-secondary <?= $i === $page ? 'active' : '' ?>" href="<?= esc($pageUrl($i)) ?>"><?= esc((string) $i) ?></a>
                    <?php endfor ?>
                    <a class="btn btn-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= esc($page >= $totalPages ? '#' : $pageUrl($page + 1)) ?>">Next</a>
                </nav>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
