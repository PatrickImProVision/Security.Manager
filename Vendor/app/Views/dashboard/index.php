<?= $this->extend('layouts/site') ?>

<?= $this->section('main') ?>

<?php

$memberCanManageRoles = (bool) session()->get('member_can_manage_roles');

$analyticsEnabled = (bool) ($analyticsEnabled ?? false);

$analytics = is_array($analytics ?? null) ? $analytics : null;

?>

<?= view('layouts/_site_header', [

    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',

]) ?>



<div class="dashboard-shell">

    <?= $this->include('dashboard/_sidebar') ?>



    <section class="dashboard-main">

        <?= $this->include('member/user/_flash') ?>



        <?php if ($memberCanManageRoles && $analytics !== null) : ?>

            <div class="card analytics-card">

                <div class="analytics-head">

                    <div>

                        <h2>Web Usage Analytics</h2>

                        <p class="hint">Whole-site traffic over the last <?= esc((string) ($analytics['days'] ?? 14)) ?> days. Chart and top pages exclude bots.</p>

                    </div>

                </div>



                <div class="analytics-stats">

                    <div class="analytics-stat">

                        <span>Human Views</span>

                        <strong><?= esc(number_format((int) ($analytics['humanViews'] ?? 0))) ?></strong>

                    </div>

                    <div class="analytics-stat">

                        <span>Members</span>

                        <strong><?= esc(number_format((int) ($analytics['memberViews'] ?? 0))) ?></strong>

                        <small><?= esc(number_format((int) ($analytics['uniqueMembers'] ?? 0))) ?> unique</small>

                    </div>

                    <div class="analytics-stat">

                        <span>Guests</span>

                        <strong><?= esc(number_format((int) ($analytics['guestViews'] ?? 0))) ?></strong>

                        <small><?= esc(number_format((int) ($analytics['uniqueGuests'] ?? 0))) ?> unique</small>

                    </div>

                    <div class="analytics-stat">

                        <span>Bots</span>

                        <strong><?= esc(number_format((int) ($analytics['botViews'] ?? 0))) ?></strong>

                        <small><?= esc(number_format((int) ($analytics['uniqueBots'] ?? 0))) ?> unique</small>

                    </div>

                    <div class="analytics-stat">

                        <span>All Hits</span>

                        <strong><?= esc(number_format((int) ($analytics['totalViews'] ?? 0))) ?></strong>

                    </div>

                    <div class="analytics-stat">

                        <span>Unique Humans</span>

                        <strong><?= esc(number_format((int) ($analytics['uniqueVisitors'] ?? 0))) ?></strong>

                    </div>

                </div>



                <div class="analytics-chart" aria-label="Daily web usage chart">

                    <?php foreach (($analytics['daily'] ?? []) as $day) : ?>

                        <?php

                        $views = (int) ($day['views'] ?? 0);

                        $maxViews = max(1, (int) ($analytics['maxViews'] ?? 1));

                        $height = $views > 0 ? max(8, (int) round(($views / $maxViews) * 100)) : 3;

                        ?>

                        <div class="analytics-bar-item">

                            <div

                                class="analytics-bar"

                                style="--bar-height: <?= esc((string) $height) ?>%;"

                                title="<?= esc((string) ($day['label'] ?? '')) ?>: <?= esc((string) $views) ?> views"

                            >

                                <span><?= esc((string) $views) ?></span>

                            </div>

                            <small><?= esc((string) ($day['label'] ?? '')) ?></small>

                        </div>

                    <?php endforeach ?>

                </div>



                <div class="analytics-pages">

                    <h3>Top Pages</h3>

                    <?php if (empty($analytics['topPages'])) : ?>

                        <p class="hint">No usage has been recorded yet.</p>

                    <?php else : ?>

                        <?php foreach ($analytics['topPages'] as $page) : ?>

                            <div class="analytics-page-row">

                                <code><?= esc((string) ($page['path'] ?? '/')) ?></code>

                                <span><?= esc(number_format((int) ($page['views'] ?? 0))) ?> views</span>

                            </div>

                        <?php endforeach ?>

                    <?php endif ?>

                </div>

            </div>

        <?php endif ?>



        <?php if ($memberCanManageRoles && ! $analyticsEnabled) : ?>

            <div class="card prose">

                <h2>Web Usage Analytics</h2>

                <p>Analytics tracking and the usage graph are disabled in Module Manager.</p>

                <p><a class="btn btn-secondary" href="<?= esc(site_url('DashBoard/ModuleManager/Index')) ?>">Open Module Manager</a></p>

            </div>

        <?php endif ?>



        <?php if (! $memberCanManageRoles) : ?>

            <div class="card prose">

                <h2>User Access</h2>

                <p>You can access your profile, member list, personal messages, and community content from the user dashboard.</p>

            </div>

        <?php endif ?>

    </section>

</div>

<?= $this->endSection() ?>


