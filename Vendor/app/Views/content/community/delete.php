<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$post = is_array($post ?? null) ? $post : [];
$postId = (int) ($post['id'] ?? 0);
$isTopic = (bool) ($isTopic ?? true);
$replyCount = (int) ($replyCount ?? 0);
$topicId = (int) ($topicId ?? 0);
$cancelUrl = $isTopic
    ? \App\Libraries\CommunityContentUrls::topicUrl($post)
    : ($topicId > 0
        ? \App\Libraries\CommunityContentUrls::topicUrl(['id' => $topicId]) . '#post-' . $postId
        : site_url('Content/Community/Index'));
?>

<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card community-forum-delete">
    <h2><?= $isTopic ? 'Delete topic' : 'Delete reply' ?></h2>
    <p class="lead">
        <?php if ($isTopic) : ?>
            This will permanently remove the topic and all <?= esc(number_format($replyCount)) ?> repl<?= $replyCount === 1 ? 'y' : 'ies' ?>.
        <?php else : ?>
            This will permanently remove this reply from the thread.
        <?php endif ?>
    </p>

    <div class="prose">
        <?php if ($isTopic) : ?>
            <p><strong><?= esc((string) ($post['title'] ?? 'Topic')) ?></strong></p>
        <?php endif ?>
        <p class="hint">Forum: <?= esc((string) ($post['category'] ?? 'Unknown')) ?> · ID <?= esc((string) $postId) ?></p>
    </div>

    <form method="post" action="<?= esc(\App\Libraries\CommunityContentUrls::deleteUrl($post)) ?>">
        <?= csrf_field() ?>
        <div class="actions">
            <button type="submit" class="btn btn-danger"><?= $isTopic ? 'Delete topic' : 'Delete reply' ?></button>
            <a class="btn btn-secondary" href="<?= esc($cancelUrl) ?>">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
