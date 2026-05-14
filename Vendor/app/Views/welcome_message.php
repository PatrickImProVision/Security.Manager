<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php if ($msg = session()->getFlashdata('message')): ?>
    <div class="ok"><?= esc($msg) ?></div>
<?php endif ?>
<?php
$memberLoggedIn = ! empty($memberLoggedIn);
$memberUsername = trim((string) ($memberUsername ?? ''));
$webName = trim((string) ($webName ?? 'Change Name')) ?: 'Change Name';
$webDescription = trim((string) ($webDescription ?? 'Change Description')) ?: 'Change Description';
$publicContentEnabled = ! empty($publicContentEnabled);
$communityEnabled = ! empty($communityEnabled);
$personalEnabled = ! empty($personalEnabled);
$featuredPages = is_array($featuredPages ?? null) ? $featuredPages : [];
$latestCommunityPosts = is_array($latestCommunityPosts ?? null) ? $latestCommunityPosts : [];
$siteStats = is_array($siteStats ?? null) ? $siteStats : [];
$onlineSummary = is_array($onlineSummary ?? null) ? $onlineSummary : null;
?>

<?= view('layouts/_site_header', [
    'siteHeaderTitle'       => $webName,
    'siteHeaderDescription' => $webDescription,
    'siteHeaderImage'       => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<section class="home-grid">
    <article class="card home-card">
        <div class="home-card-head">
            <h2>Featured Public Pages</h2>
            <?php if ($publicContentEnabled) : ?>
                <a href="<?= esc(site_url('Content/Public/Index')) ?>">View All</a>
            <?php endif ?>
        </div>
        <?php if (! $publicContentEnabled) : ?>
            <p class="hint">Public content is disabled in Module Manager.</p>
        <?php elseif (empty($featuredPages)) : ?>
            <p class="hint">No featured public pages are available yet.</p>
        <?php else : ?>
            <div class="home-link-list">
                <?php foreach ($featuredPages as $page) : ?>
                    <a href="<?= esc((string) ($page['url'] ?? '#')) ?>">
                        <strong><?= esc((string) ($page['label'] ?? $page['title'] ?? 'Public Page')) ?></strong>
                        <?php if (! empty($page['summary'])) : ?>
                            <span><?= esc((string) $page['summary']) ?></span>
                        <?php endif ?>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </article>

    <article class="card home-card">
        <div class="home-card-head">
            <h2>Latest Community Posts</h2>
            <?php if ($communityEnabled) : ?>
                <a href="<?= esc(site_url('Content/Community/Index')) ?>">View All</a>
            <?php endif ?>
        </div>
        <?php if (! $communityEnabled) : ?>
            <p class="hint">Community content is disabled in Module Manager.</p>
        <?php elseif (empty($latestCommunityPosts)) : ?>
            <p class="hint">No community posts are available yet.</p>
        <?php else : ?>
            <div class="home-link-list">
                <?php foreach ($latestCommunityPosts as $post) : ?>
                    <a href="<?= esc((string) ($post['url'] ?? '#')) ?>">
                        <strong><?= esc((string) ($post['title'] ?? 'Community Post')) ?></strong>
                        <span>
                            <?= esc((string) ($post['category'] ?? 'Unknown')) ?>
                            by <?= esc((string) ($post['author_name'] ?? 'Unknown')) ?>
                        </span>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </article>
</section>

<section class="home-stat-grid" aria-label="Site statistics">
    <div class="home-stat card"><span>Public Pages</span><strong><?= esc(number_format((int) ($siteStats['publicPages'] ?? 0))) ?></strong></div>
    <div class="home-stat card"><span>Community Posts</span><strong><?= esc(number_format((int) ($siteStats['communityPosts'] ?? 0))) ?></strong></div>
    <div class="home-stat card"><span>Active Members</span><strong><?= esc(number_format((int) ($siteStats['activeMembers'] ?? 0))) ?></strong></div>
</section>

<section class="home-grid">
    <article class="card home-card">
        <h2>Who Is Online</h2>
        <?php if ($onlineSummary !== null) : ?>
            <div class="home-stat-row">
                <div class="home-stat"><span>Guests</span><strong><?= esc(number_format((int) ($onlineSummary['guests'] ?? 0))) ?></strong></div>
                <div class="home-stat"><span>Members</span><strong><?= esc(number_format((int) ($onlineSummary['members'] ?? 0))) ?></strong></div>
            </div>
            <p class="hint">Active in the last <?= esc((string) ($onlineSummary['windowMinutes'] ?? 10)) ?> minutes.</p>
            <?php if (! empty($onlineSummary['memberList'])) : ?>
                <div class="home-online-members">
                    <?php foreach ($onlineSummary['memberList'] as $member) : ?>
                        <span class="home-online-member-name"><?= esc((string) ($member['username'] ?? 'Member')) ?></span>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        <?php else : ?>
            <p>Online tracking is disabled in Module Manager.</p>
        <?php endif ?>
    </article>
</section>

<?= $this->endSection() ?>
