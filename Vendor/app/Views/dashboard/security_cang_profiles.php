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
                    <p>Each row shows language, length, split formatting, and mode for each generator profile. Open Edit for the target key and full language details. <strong>Product Key.Id</strong> is configured here but not connected to any feature yet.</p>
                </div>
            </div>

            <div class="cang-profile-table-wrap">
                <div class="cang-profile-table-head" aria-hidden="true">
                    <span>Profile</span>
                    <span>Language</span>
                    <span>Length</span>
                    <span>Split By / Length</span>
                    <span>Mode</span>
                    <span>Status</span>
                    <span>Action</span>
                </div>
                <?php foreach (($profiles ?? []) as $profile) : ?>
                    <article class="cang-profile-row">
                        <div class="cang-profile-cell cang-profile-name">
                            <strong class="cang-profile-label" title="<?= esc((string) ($profile['label'] ?? 'CANG Profile'), 'attr') ?>"><?= esc((string) ($profile['label'] ?? 'CANG Profile')) ?></strong>
                            <?php if (! empty($profile['integration_note'])) : ?>
                                <span class="cang-profile-integration-note" title="<?= esc((string) $profile['integration_note'], 'attr') ?>">Not connected</span>
                            <?php endif ?>
                        </div>
                        <div class="cang-profile-cell cang-profile-language">
                            <span class="cang-profile-value" title="<?= esc((string) ($profile['language_name'] ?? ''), 'attr') ?>"><?= esc((string) ($profile['language_name'] ?? '')) ?></span>
                        </div>
                        <div class="cang-profile-cell cang-profile-center"><span class="cang-profile-num"><?= esc((string) ($profile['code_length'] ?? 0)) ?></span></div>
                        <?php
                        $sb = trim((string) ($profile['split_by'] ?? ''));
                        $sl = (int) ($profile['split_length'] ?? 0);
                        ?>
                        <div class="cang-profile-cell cang-profile-center cang-profile-split" title="Split by / split length (separator / characters per group)">
                            <?php if ($sb !== '' && $sl > 0) : ?>
                                <span class="cang-profile-split-pair"><code class="cang-profile-code"><?= esc($sb) ?></code><span class="cang-profile-split-sep" aria-hidden="true">/</span><span class="cang-profile-num"><?= esc((string) $sl) ?></span></span>
                            <?php else : ?>
                                <span class="cang-profile-muted">—</span>
                            <?php endif ?>
                        </div>
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
