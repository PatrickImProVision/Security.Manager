<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<div class="dashboard-shell">
    <?= $this->include('dashboard/_sidebar') ?>

    <section class="dashboard-main">
        <?= $this->include('member/user/_flash') ?>

        <form class="card prose" method="post" action="<?= esc(site_url('DashBoard/ContentModules')) ?>">
            <?= csrf_field() ?>
            <div class="role-list-head">
                <div>
                    <h2>Application Modules</h2>
                    <p>Enable or disable application areas and dashboard services from the Module Manager.</p>
                </div>
                <button type="submit" class="btn btn-primary">Save Module Settings</button>
            </div>

            <div class="module-toggle-list">
                <?php foreach (($contentModules ?? []) as $module) : ?>
                    <?php $moduleKey = (string) ($module['module_key'] ?? ''); ?>
                    <label class="module-toggle">
                        <input
                            type="checkbox"
                            name="modules[]"
                            value="<?= esc($moduleKey) ?>"
                            <?= ! empty($module['is_enabled']) ? 'checked' : '' ?>
                        >
                        <span class="module-toggle-body">
                            <span class="module-toggle-title">
                                <?= esc((string) ($module['label'] ?? $moduleKey)) ?>
                                <span class="status-pill <?= ! empty($module['is_enabled']) ? 'status-active' : 'status-inactive' ?>">
                                    <?= ! empty($module['is_enabled']) ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </span>
                            <span class="module-toggle-description"><?= esc((string) ($module['description'] ?? '')) ?></span>
                        </span>
                    </label>
                <?php endforeach ?>
            </div>
        </form>
    </section>
</div>
<?= $this->endSection() ?>
