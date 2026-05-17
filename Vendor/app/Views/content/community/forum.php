<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$forum = is_array($forum ?? null) ? $forum : [];
$forumId = (int) ($forum['id'] ?? 0);
$forumRef = \App\Libraries\CommunityForumUrls::forumRefForUrl($forum);
$subforums = is_array($subforums ?? null) ? $subforums : [];
?>
<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card community-forum-index">

    <div class="role-list-head forum-index-toolbar">
        <div>
            <h2><?= esc((string) ($forum['name'] ?? 'Forum')) ?></h2>
            <?php if (trim((string) ($forum['description'] ?? '')) !== '') : ?>
                <p class="hint"><?= esc((string) $forum['description']) ?></p>
            <?php endif ?>
        </div>
        <div class="role-list-head-actions">
            <?php if (! empty($canCreate)) : ?>
                <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Create?forum=' . rawurlencode($forumRef))) ?>">New Topic</a>
            <?php endif ?>
        </div>
    </div>

    <?php if ($subforums !== []) : ?>
        <section class="forum-subforums" aria-label="Sub-forums">
            <h3 class="forum-subforums-title">Sub-forums</h3>
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
                        <?php foreach ($subforums as $sub) : ?>
                            <tr class="forum-row forum-board-row forum-row-sub">
                                <td class="forum-col-topic">
                                    <a class="forum-topic-title" href="<?= esc((string) ($sub['forum_url'] ?? \App\Libraries\CommunityCategoryService::forumUrl($sub))) ?>">
                                        <?= esc((string) ($sub['name'] ?? 'Forum')) ?>
                                    </a>
                                    <?php if (trim((string) ($sub['description'] ?? '')) !== '') : ?>
                                        <p class="forum-topic-excerpt"><?= esc((string) $sub['description']) ?></p>
                                    <?php endif ?>
                                </td>
                                <td class="forum-col-stat"><?= esc(number_format((int) ($sub['topic_count'] ?? 0))) ?></td>
                                <td class="forum-col-stat"><?= esc(number_format((int) ($sub['post_count'] ?? 0))) ?></td>
                                <td class="forum-col-last">
                                    <?php if ((int) ($sub['last_topic_id'] ?? 0) > 0) : ?>
                                        <a class="forum-last-title" href="<?= esc((string) ($sub['last_topic_url'] ?? \App\Libraries\CommunityContentUrls::topicUrl(['id' => (int) ($sub['last_topic_id'] ?? 0)]))) ?>">
                                            <?= esc((string) ($sub['last_title'] ?? 'Topic')) ?>
                                        </a>
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
    <?php endif ?>

    <?php if (empty($topics)) : ?>
        <div class="forum-empty">
            <p>No topics in this forum yet.</p>
            <?php if (! empty($canCreate)) : ?>
                <a class="btn btn-primary" href="<?= esc(site_url('Content/Community/Create?forum=' . rawurlencode($forumRef))) ?>">Post the first topic</a>
            <?php endif ?>
        </div>
    <?php else : ?>
        <section class="forum-index-block">
            <div class="forum-table-wrap">
                <table class="forum-index-table">
                    <thead>
                        <tr>
                            <th class="forum-col-topic">Topic</th>
                            <th class="forum-col-stat">Replies</th>
                            <th class="forum-col-stat">Views</th>
                            <th class="forum-col-last">Last post</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topics as $topic) : ?>
                            <?php
                            $topicId = (int) ($topic['id'] ?? 0);
                            $isPublished = (string) ($topic['status'] ?? '') === 'published';
                            ?>
                            <tr class="forum-row<?= ! empty($topic['is_sticky']) ? ' forum-row-sticky' : '' ?>">
                                <td class="forum-col-topic">
                                    <div class="forum-row-main">
                                        <span class="forum-icon<?= $isPublished ? '' : ' forum-icon-draft' ?><?= ! empty($topic['is_locked']) ? ' forum-icon-locked' : '' ?>" aria-hidden="true"></span>
                                        <div class="forum-row-copy">
                                            <a class="forum-topic-title" href="<?= esc((string) ($topic['topic_url'] ?? \App\Libraries\CommunityContentUrls::topicUrl($topic))) ?>">
                                                <?php if (! empty($topic['is_sticky'])) : ?><span class="forum-flag">Sticky</span><?php endif ?>
                                                <?php if (! empty($topic['is_locked'])) : ?><span class="forum-flag forum-flag-lock">Locked</span><?php endif ?>
                                                <?= esc((string) ($topic['title'] ?? 'Topic')) ?>
                                            </a>
                                            <?php if (! $isPublished) : ?>
                                                <span class="status-pill status-inactive"><?= esc(ucfirst((string) ($topic['status'] ?? 'draft'))) ?></span>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="forum-col-stat"><?= esc(number_format((int) ($topic['reply_count'] ?? 0))) ?></td>
                                <td class="forum-col-stat"><?= esc(number_format((int) ($topic['view_count'] ?? 0))) ?></td>
                                <td class="forum-col-last">
                                    <div class="forum-last-post">
                                        <span class="forum-last-meta"><?= esc((string) ($topic['last_activity_at'] ?? '')) ?></span>
                                        <span class="forum-last-meta">by <?= esc((string) ($topic['author_name'] ?? 'Unknown')) ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php
        $pagination = is_array($pagination ?? null) ? $pagination : [];
        $page = (int) ($pagination['page'] ?? 1);
        $totalPages = (int) ($pagination['totalPages'] ?? 1);
        $total = (int) ($pagination['total'] ?? 0);
        $perPage = (int) ($pagination['perPage'] ?? count($topics));
        $start = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $end = $total > 0 ? min($total, $page * $perPage) : 0;
        $pageUrl = static fn (int $p): string => \App\Libraries\CommunityForumUrls::forumUrl($forum) . '?page=' . $p;
        ?>
        <div class="content-pagination forum-pagination">
            <span>Topics <?= esc((string) $start) ?>–<?= esc((string) $end) ?> of <?= esc((string) $total) ?></span>
            <?php if ($totalPages > 1) : ?>
                <nav class="pagination-links" aria-label="Forum topics pagination">
                    <a class="btn btn-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= esc($page <= 1 ? '#' : $pageUrl($page - 1)) ?>">Previous</a>
                    <span class="forum-page-current">Page <?= esc((string) $page) ?> / <?= esc((string) $totalPages) ?></span>
                    <a class="btn btn-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= esc($page >= $totalPages ? '#' : $pageUrl($page + 1)) ?>">Next</a>
                </nav>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
