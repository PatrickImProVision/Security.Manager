<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$topic = is_array($topic ?? null) ? $topic : [];
$topicId = (int) ($topic['id'] ?? 0);
$topicRef = (string) ($topicRef ?? \App\Libraries\CommunityContentUrls::topicRefForUrl($topic));
$replies = is_array($replies ?? null) ? $replies : [];
$replyBodies = is_array($replyBodies ?? null) ? $replyBodies : [];
?>
<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card community-forum-topic">

    <div class="topic-head">
        <h2>
            <?php if (! empty($topic['is_sticky'])) : ?><span class="forum-flag">Sticky</span><?php endif ?>
            <?php if (! empty($topic['is_locked'])) : ?><span class="forum-flag forum-flag-lock">Locked</span><?php endif ?>
            <?= esc((string) ($topic['title'] ?? 'Topic')) ?>
        </h2>
        <p class="hint">
            <?= esc((string) ($topic['reply_count'] ?? count($replies))) ?> replies
            · <?= esc(number_format((int) ($topic['view_count'] ?? 0))) ?> views
            · started by <?= esc((string) ($topic['author_name'] ?? 'Unknown')) ?>
        </p>
        <div class="actions topic-actions">
            <?php if (! empty($canManage)) : ?>
                <a class="btn btn-secondary" href="<?= esc(\App\Libraries\CommunityContentUrls::editUrl($topic)) ?>">Edit topic</a>
                <a class="btn btn-danger" href="<?= esc(\App\Libraries\CommunityContentUrls::deleteUrl($topic)) ?>">Delete topic</a>
            <?php endif ?>
            <?php if (! empty($canModerate)) : ?>
                <form method="post" action="<?= esc(site_url('Content/Community/Topic/' . rawurlencode($topicRef) . '/Lock')) ?>" class="topic-inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><?= ! empty($topic['is_locked']) ? 'Unlock' : 'Lock' ?> topic</button>
                </form>
                <form method="post" action="<?= esc(site_url('Content/Community/Topic/' . rawurlencode($topicRef) . '/Sticky')) ?>" class="topic-inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary"><?= ! empty($topic['is_sticky']) ? 'Unstick' : 'Sticky' ?> topic</button>
                </form>
            <?php endif ?>
        </div>
    </div>

    <article class="forum-post forum-post-op" id="post-<?= esc((string) $topicId) ?>">
        <header class="forum-post-head">
            <div class="forum-post-user">
                <span class="forum-post-author"><?= esc((string) ($topic['author_name'] ?? 'Unknown')) ?></span>
                <span class="forum-post-role">Topic starter</span>
            </div>
            <time class="forum-post-time"><?= esc((string) ($topic['created_at'] ?? '')) ?></time>
        </header>
        <div class="forum-post-body content-body prose">
            <?= $topicHtml ?? '' ?>
        </div>
    </article>

    <?php foreach ($replies as $index => $reply) : ?>
        <?php
        $replyId = (int) ($reply['id'] ?? 0);
        $canEditReply = (bool) session()->get('member_can_manage_roles')
            || (int) ($reply['author_id'] ?? 0) === (int) session()->get('member_user_id');
        ?>
        <article class="forum-post" id="post-<?= esc((string) $replyId) ?>">
            <header class="forum-post-head">
                <div class="forum-post-user">
                    <span class="forum-post-author"><?= esc((string) ($reply['author_name'] ?? 'Unknown')) ?></span>
                    <span class="forum-post-role">#<?= esc((string) ($index + 2)) ?></span>
                </div>
                <time class="forum-post-time"><?= esc((string) ($reply['created_at'] ?? '')) ?></time>
            </header>
            <div class="forum-post-body content-body prose">
                <?= $replyBodies[$replyId] ?? '' ?>
            </div>
            <?php if ($canEditReply) : ?>
                <footer class="forum-post-foot">
                    <a href="<?= esc(\App\Libraries\CommunityContentUrls::editUrl($reply)) ?>">Edit</a>
                    <a class="forum-last-danger" href="<?= esc(\App\Libraries\CommunityContentUrls::deleteUrl($reply)) ?>">Delete</a>
                </footer>
            <?php endif ?>
        </article>
    <?php endforeach ?>

    <?php if (! empty($canReply)) : ?>
        <section class="forum-reply-form card">
            <h3>Post a reply</h3>
            <form method="post" action="<?= esc(site_url('Content/Community/Topic/' . rawurlencode($topicRef) . '/Reply')) ?>">
                <?= csrf_field() ?>
                <label for="reply_body">Your message</label>
                <textarea name="body" id="reply_body" rows="8" required minlength="3"></textarea>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Submit reply</button>
                </div>
            </form>
        </section>
    <?php elseif (empty($canReply) && empty(session()->get('member_user_id'))) : ?>
        <p class="hint">Log in to reply to this topic.</p>
    <?php elseif (! empty($topic['is_locked'])) : ?>
        <p class="hint">This topic is locked. No new replies can be posted.</p>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
