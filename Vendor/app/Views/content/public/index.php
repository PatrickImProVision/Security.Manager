<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <div class="role-list-head">
        <h2>Contents</h2>
        <?php if (! empty($canManage)) : ?>
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Public/Create')) ?>">Create Content</a>
        <?php endif ?>
    </div>

    <?php if (empty($contents)) : ?>
        <p>No public content has been created yet.</p>
    <?php else : ?>
        <div class="content-list-table-wrap">
            <table class="content-list-table">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Status</th>
                        <th>Favorite</th>
                        <th>Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $currentGroup = ''; ?>
                    <?php foreach ($contents as $content) : ?>
                        <?php
                        $group = ! empty($content['show_in_nav']) ? 'Favorite' : 'Normal';
                        if ($group !== $currentGroup) :
                            $currentGroup = $group;
                        ?>
                            <tr class="content-list-group-row">
                                <td colspan="5">
                                    <span><?= esc($group) ?></span>
                                </td>
                            </tr>
                        <?php endif ?>
                        <tr>
                            <td class="content-list-main">
                                <strong><?= esc((string) $content['title']) ?></strong>
                                <p><code><?= esc((string) $content['slug']) ?></code></p>
                                <?php if (trim((string) ($content['summary'] ?? '')) !== '') : ?>
                                    <p>SEO Description: <?= esc((string) $content['summary']) ?></p>
                                <?php endif ?>
                            </td>
                            <td class="content-list-status">
                                <span class="status-pill <?= (string) ($content['status'] ?? '') === 'published' ? 'status-active' : 'status-inactive' ?>">
                                    <?= esc(ucfirst((string) ($content['status'] ?? 'draft'))) ?>
                                </span>
                            </td>
                            <td class="content-list-status">
                                <span class="status-pill <?= ! empty($content['show_in_nav']) ? 'status-active' : 'status-inactive' ?>">
                                    <?= ! empty($content['show_in_nav']) ? 'True' : 'False' ?>
                                </span>
                            </td>
                            <td class="content-list-nav">
                                <code><?= esc(! empty($content['show_in_nav']) ? (string) (($content['nav_label'] ?? '') ?: $content['title']) : '-') ?></code>
                            </td>
                            <td>
                                <div class="content-list-actions">
                                    <?php $viewPath = ! empty($content['show_in_nav'])
                                        ? 'Content/Public/View/' . (string) $content['slug']
                                        : 'Content/Public/View/' . (int) $content['id']; ?>
                                    <a class="btn btn-secondary" href="<?= esc(site_url($viewPath)) ?>">View</a>
                                    <?php if (! empty($canManage)) : ?>
                                        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Public/Edit/' . (int) $content['id'])) ?>">Edit</a>
                                        <a class="btn btn-danger" href="<?= esc(site_url('Content/Public/Delete/' . (int) $content['id'])) ?>">Delete</a>
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
        $total = (int) ($pagination['total'] ?? count($contents));
        $perPage = (int) ($pagination['perPage'] ?? count($contents));
        $start = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $end = $total > 0 ? min($total, $page * $perPage) : 0;
        $pageUrl = static fn (int $p): string => site_url('Content/Public/Index') . '?page=' . $p;
        ?>
        <div class="content-pagination">
            <span>Showing <?= esc((string) $start) ?>-<?= esc((string) $end) ?> of <?= esc((string) $total) ?></span>
            <?php if ($totalPages > 1) : ?>
                <nav class="pagination-links" aria-label="Content pagination">
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
