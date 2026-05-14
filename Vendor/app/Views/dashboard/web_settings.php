<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$settings = is_array($webSettings ?? null) ? $webSettings : [];
$webName = (string) old('web_name', $settings['web_name'] ?? 'Change Name');
$webDescription = (string) old('web_description', $settings['web_description'] ?? 'Change Description');
?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<div class="dashboard-shell">
    <?= $this->include('dashboard/_sidebar') ?>

    <section class="dashboard-main">
        <?= $this->include('member/user/_flash') ?>

        <form class="card prose" method="post" action="<?= esc(site_url('DashBoard/WebSettings')) ?>">
            <?= csrf_field() ?>

            <label for="web_name">Web name</label>
            <input
                type="text"
                name="web_name"
                id="web_name"
                value="<?= esc($webName, 'attr') ?>"
                maxlength="120"
                required
            >

            <label for="web_description">Web description</label>
            <textarea
                name="web_description"
                id="web_description"
                rows="4"
                maxlength="500"
                required
            ><?= esc($webDescription) ?></textarea>
            <p class="hint">This description appears in the home page header under the web name.</p>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save Web Settings</button>
                <a class="btn btn-secondary" href="<?= esc(site_url('/')) ?>">View Home Page</a>
            </div>
        </form>
    </section>
</div>
<?= $this->endSection() ?>
