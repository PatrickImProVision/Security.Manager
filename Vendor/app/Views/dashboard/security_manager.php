<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<div class="dashboard-shell">
    <?= $this->include('dashboard/_sidebar') ?>

    <section class="dashboard-main">
        <?= $this->include('member/user/_flash') ?>

        <div class="card prose">
            <h2>CANG Generator</h2>
            <p>Manage application identifier profiles for user URLs, password IDs, content URLs, and future product keys.</p>
            <div class="actions">
                <a class="btn btn-primary" href="<?= esc(site_url('DashBoard/SecurityManager/CANG/Index')) ?>">Manage CANG Profiles</a>
            </div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>
