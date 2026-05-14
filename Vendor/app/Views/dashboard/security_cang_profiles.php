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
            <div class="role-list-head">
                <div>
                    <h2>Managed Generator Profiles</h2>
                    <p>Select the CANG language, code length, and random/sequential mode for each application target.</p>
                </div>
            </div>

            <div class="cang-profile-table-wrap">
                <div class="cang-profile-table-head" aria-hidden="true">
                    <span>Profile</span>
                    <span>Key</span>
                    <span>Language</span>
                    <span>Type</span>
                    <span>Len</span>
                    <span>Mode</span>
                    <span>Status</span>
                    <span>Action</span>
                </div>
                <?php foreach (($profiles ?? []) as $profile) : ?>
                    <article class="cang-profile-row">
                        <div class="cang-profile-cell cang-profile-name">
                            <strong><?= esc((string) ($profile['label'] ?? 'CANG Profile')) ?></strong>
                        </div>
                        <div class="cang-profile-cell"><code><?= esc((string) ($profile['target_key'] ?? '')) ?></code></div>
                        <div class="cang-profile-cell"><strong><?= esc((string) ($profile['language_name'] ?? '')) ?></strong></div>
                        <div class="cang-profile-cell"><code><?= esc((string) ($profile['language_type'] ?? '')) ?></code></div>
                        <div class="cang-profile-cell cang-profile-center"><?= esc((string) ($profile['code_length'] ?? 0)) ?></div>
                        <div class="cang-profile-cell">
                            <span class="status-pill status-system"><?= esc(ucfirst((string) ($profile['generation_mode'] ?? 'random'))) ?></span>
                        </div>
                        <div class="cang-profile-cell">
                            <span class="status-pill <?= ! empty($profile['is_active']) ? 'status-active' : 'status-inactive' ?>">
                                <?= ! empty($profile['is_active']) ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="cang-profile-cell">
                            <a class="btn btn-primary" href="<?= esc(site_url('DashBoard/SecurityManager/CANG/Edit/' . (int) ($profile['id'] ?? 0))) ?>">Edit</a>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>
