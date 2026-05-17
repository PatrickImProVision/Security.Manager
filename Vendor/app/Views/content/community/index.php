<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? 'Community'),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card community-forum-index">
    <div class="role-list-head forum-index-toolbar">
        <h2>Community Forum</h2>
        <div class="role-list-head-actions">
            <?php if (! empty($canManageCategories)) : ?>
                <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Categories/Index')) ?>">Manage Forums</a>
            <?php endif ?>
            <?php if (! empty($canCreate)) : ?>
                <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Create')) ?>">New Topic</a>
            <?php endif ?>
        </div>
    </div>

    <?php
    $sections = is_array($sections ?? null) ? $sections : [];
    $hasContent = $sections !== [] || ! empty($uncategorizedBoard);
    ?>

    <?php if (! $hasContent) : ?>
        <div class="forum-empty">
            <p>No forums or topics yet.</p>
            <?php if (! empty($canCreate)) : ?>
                <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Create')) ?>">Start the first topic</a>
            <?php endif ?>
            <?php if (! empty($canManageCategories)) : ?>
                <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Categories/Index')) ?>">Create forum categories</a>
            <?php endif ?>
        </div>
    <?php else : ?>
        <div class="forum-index">
            <?php foreach ($sections as $section) : ?>
                <section class="forum-index-block">
                    <?php if (! empty($section['title'])) : ?>
                        <h3 class="forum-category-head"><?= esc((string) $section['title']) ?></h3>
                        <?php if (trim((string) ($section['description'] ?? '')) !== '') : ?>
                            <p class="forum-board-desc"><?= esc((string) $section['description']) ?></p>
                        <?php endif ?>
                    <?php endif ?>
                    <div class="forum-table-wrap">
                        <table class="forum-index-table forum-board-stats-table">
                            <thead>
                                <tr>
                                    <th class="forum-col-topic">Forum</th>
                                    <th class="forum-col-stat">Topics</th>
                                    <th class="forum-col-stat">Posts</th>
                                    <th class="forum-col-last">Last post</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($section['boards'] ?? []) as $board) : ?>
                                    <?php $depth = (int) ($board['depth'] ?? 0); ?>
                                    <tr class="forum-row forum-board-row<?= $depth > 0 ? ' forum-row-sub' : '' ?>">
                                        <td class="forum-col-topic" style="padding-left: <?= esc((string) (0.75 + $depth * 1.25), 'attr') ?>rem">
                                            <div class="forum-row-main">
                                                <span class="forum-icon<?= ! empty($board['child_count']) ? ' forum-icon-folder' : '' ?>" aria-hidden="true"></span>
                                                <div class="forum-row-copy">
                                                    <a class="forum-topic-title" href="<?= esc((string) ($board['forum_url'] ?? \App\Libraries\CommunityCategoryService::forumUrl($board))) ?>">
                                                        <?= esc((string) ($board['name'] ?? 'Forum')) ?>
                                                    </a>
                                                    <?php if (trim((string) ($board['description'] ?? '')) !== '' && $depth > 0) : ?>
                                                        <p class="forum-topic-excerpt"><?= esc((string) $board['description']) ?></p>
                                                    <?php endif ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="forum-col-stat"><?= esc(number_format((int) ($board['topic_count'] ?? 0))) ?></td>
                                        <td class="forum-col-stat"><?= esc(number_format((int) ($board['post_count'] ?? 0))) ?></td>
                                        <td class="forum-col-last">
                                            <?php if ((int) ($board['last_topic_id'] ?? 0) > 0) : ?>
                                                <div class="forum-last-post">
                                                    <a class="forum-last-title" href="<?= esc((string) ($board['last_topic_url'] ?? \App\Libraries\CommunityContentUrls::topicUrl(['id' => (int) ($board['last_topic_id'] ?? 0)]))) ?>">
                                                        <?= esc((string) ($board['last_title'] ?? 'Topic')) ?>
                                                    </a>
                                                    <span class="forum-last-meta">
                                                        by <?= esc((string) ($board['last_author_name'] ?? 'Unknown')) ?>
                                                        · <?= esc((string) ($board['last_at'] ?? '')) ?>
                                                    </span>
                                                </div>
                                            <?php else : ?>
                                                <span class="forum-last-meta">—</span>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach ?>

            <?php if (! empty($uncategorizedBoard)) : ?>
                <?php $board = $uncategorizedBoard; ?>
                <section class="forum-index-block forum-index-block-uncat" aria-label="Uncategorized topics">
                    <div class="forum-table-wrap">
                        <table class="forum-index-table forum-board-stats-table">
                            <thead>
                                <tr>
                                    <th class="forum-col-topic">Forum</th>
                                    <th class="forum-col-stat">Topics</th>
                                    <th class="forum-col-stat">Posts</th>
                                    <th class="forum-col-last">Last post</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="forum-row forum-board-row">
                                    <td class="forum-col-topic">
                                        <div class="forum-row-main">
                                            <span class="forum-icon forum-icon-draft" aria-hidden="true"></span>
                                            <div class="forum-row-copy">
                                                <a class="forum-topic-title" href="<?= esc((string) ($board['forum_url'] ?? \App\Libraries\CommunityCategoryService::forumUrl($board))) ?>">
                                                    <?= esc((string) ($board['name'] ?? 'Uncategorized')) ?>
                                                </a>
                                                <p class="forum-topic-excerpt"><?= esc((string) ($board['description'] ?? '')) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="forum-col-stat"><?= esc(number_format((int) ($board['topic_count'] ?? 0))) ?></td>
                                    <td class="forum-col-stat"><?= esc(number_format((int) ($board['post_count'] ?? 0))) ?></td>
                                    <td class="forum-col-last">
                                        <?php if ((int) ($board['last_topic_id'] ?? 0) > 0) : ?>
                                            <div class="forum-last-post">
                                                <a class="forum-last-title" href="<?= esc((string) ($board['last_topic_url'] ?? \App\Libraries\CommunityContentUrls::topicUrl(['id' => (int) ($board['last_topic_id'] ?? 0)]))) ?>">
                                                    <?= esc((string) ($board['last_title'] ?? 'Topic')) ?>
                                                </a>
                                                <span class="forum-last-meta">
                                                    by <?= esc((string) ($board['last_author_name'] ?? 'Unknown')) ?>
                                                    · <?= esc((string) ($board['last_at'] ?? '')) ?>
                                                </span>
                                            </div>
                                        <?php else : ?>
                                            <span class="forum-last-meta">—</span>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
