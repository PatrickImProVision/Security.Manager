<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php $forumRows = is_array($forumRows ?? null) ? $forumRows : []; ?>

<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card phpbb-acp-forums">
    <div class="phpbb-acp-head">
        <div>
            <h2>Forum administration</h2>
            <p class="hint">Manage categories, forums, and sub-forums. Categories group forums; each forum can contain topics and child forums.</p>
        </div>
        <div class="phpbb-acp-head-actions">
            <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Index')) ?>">Forum index</a>
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Categories/Create?type=category')) ?>">Create category</a>
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Categories/Create?type=forum')) ?>">Create forum</a>
        </div>
    </div>

    <?php if ($forumRows === []) : ?>
        <div class="forum-empty">
            <p>No forums have been created yet.</p>
            <p class="hint">Start with a <strong>category</strong> (board group), then add <strong>forums</strong> and <strong>sub-forums</strong> beneath it.</p>
            <div class="actions">
                <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Categories/Create?type=category')) ?>">Create first category</a>
            </div>
        </div>
    <?php else : ?>
        <div class="phpbb-acp-table-wrap">
            <table class="phpbb-acp-table">
                <thead>
                    <tr>
                        <th class="phpbb-acp-col-name">Forum</th>
                        <th class="phpbb-acp-col-type">Type</th>
                        <th class="phpbb-acp-col-stat">Topics</th>
                        <th class="phpbb-acp-col-stat">Posts</th>
                        <th class="phpbb-acp-col-status">Status</th>
                        <th class="phpbb-acp-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forumRows as $row) : ?>
                        <?php
                        $rowId = (int) ($row['id'] ?? 0);
                        $depth = (int) ($row['depth'] ?? 0);
                        $nodeType = (string) ($row['node_type'] ?? 'forum');
                        $isSystem = ! empty($row['is_system']);
                        $isActive = ! empty($row['is_active']);
                        ?>
                        <tr class="phpbb-acp-row phpbb-acp-row-<?= esc($nodeType, 'attr') ?><?= ! $isActive ? ' phpbb-acp-row-inactive' : '' ?>">
                            <td class="phpbb-acp-col-name" style="padding-left: <?= esc((string) (0.65 + $depth * 1.35), 'attr') ?>rem">
                                <div class="phpbb-acp-forum-name">
                                    <span class="phpbb-acp-icon phpbb-acp-icon-<?= esc($nodeType, 'attr') ?>" aria-hidden="true"></span>
                                    <div class="phpbb-acp-forum-copy">
                                        <a class="phpbb-acp-title" href="<?= esc((string) ($row['forum_url'] ?? '#')) ?>"><?= esc((string) ($row['name'] ?? '')) ?></a>
                                        <?php if (trim((string) ($row['description'] ?? '')) !== '') : ?>
                                            <span class="phpbb-acp-desc"><?= esc((string) $row['description']) ?></span>
                                        <?php endif ?>
                                        <span class="phpbb-acp-meta"><code><?= esc((string) ($row['slug'] ?? '')) ?></code><?= (int) ($row['child_count'] ?? 0) > 0 ? ' · ' . (int) $row['child_count'] . ' sub-forum(s)' : '' ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="phpbb-acp-col-type">
                                <span class="phpbb-acp-type-pill phpbb-acp-type-<?= esc($nodeType, 'attr') ?>"><?= esc((string) ($row['type_label'] ?? 'Forum')) ?></span>
                            </td>
                            <td class="phpbb-acp-col-stat"><?= esc(number_format((int) ($row['topic_count'] ?? 0))) ?></td>
                            <td class="phpbb-acp-col-stat"><?= esc(number_format((int) ($row['post_count'] ?? 0))) ?></td>
                            <td class="phpbb-acp-col-status">
                                <?php if ($isActive) : ?>
                                    <span class="status-pill status-active">Active</span>
                                <?php else : ?>
                                    <span class="status-pill status-inactive">Hidden</span>
                                <?php endif ?>
                            </td>
                            <td class="phpbb-acp-col-actions">
                                <div class="phpbb-acp-actions">
                                    <a class="btn btn-secondary btn-sm" href="<?= esc(site_url('Content/Community/Categories/Edit/' . $rowId)) ?>">Edit</a>
                                    <?php if ($nodeType !== 'category') : ?>
                                        <a class="btn btn-secondary btn-sm" href="<?= esc(site_url('Content/Community/Categories/Create?type=forum&parent=' . $rowId)) ?>">Sub</a>
                                    <?php else : ?>
                                        <a class="btn btn-secondary btn-sm" href="<?= esc(site_url('Content/Community/Categories/Create?type=forum&parent=' . $rowId)) ?>">+ Forum</a>
                                    <?php endif ?>
                                    <?php if (! $isSystem) : ?>
                                        <form method="post" action="<?= esc(site_url('Content/Community/Categories/Delete/' . $rowId)) ?>" class="phpbb-acp-inline-form" onsubmit="return confirm('Delete this forum? Topics will move to Unknown.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <div class="phpbb-acp-foot">
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Categories/Create?type=category')) ?>">Create category</a>
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Categories/Create?type=forum')) ?>">Create forum</a>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
