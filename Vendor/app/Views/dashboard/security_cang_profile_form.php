<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$profile = is_array($profile ?? null) ? $profile : [];
$languages = is_array($languages ?? null) ? $languages : [];
$selectedLanguage = (int) old('language_id', $profile['language_id'] ?? 7);
$selectedMode = (string) old('generation_mode', $profile['generation_mode'] ?? 'random');
$codeLength = (int) old('code_length', $profile['code_length'] ?? 12);
$isActive = old('is_active', ! empty($profile['is_active']) ? '1' : '');
?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<div class="dashboard-shell">
    <?= $this->include('dashboard/_sidebar') ?>

    <section class="dashboard-main">
        <?= $this->include('member/user/_flash') ?>

        <form class="card prose" method="post" action="<?= esc(site_url('DashBoard/SecurityManager/CANG/Edit/' . (int) ($profile['id'] ?? 0))) ?>">
            <?= csrf_field() ?>

            <h2><?= esc((string) ($profile['label'] ?? 'CANG Profile')) ?></h2>
            <p><?= esc((string) ($profile['description'] ?? '')) ?></p>
            <p><strong>Profile:</strong> <code><?= esc((string) ($profile['target_key'] ?? '')) ?></code></p>

            <label for="language_id">CANG language</label>
            <select name="language_id" id="language_id" required>
                <?php foreach ($languages as $id => $language) : ?>
                    <option value="<?= esc((string) $id) ?>" <?= (int) $id === $selectedLanguage ? 'selected' : '' ?>>
                        <?= esc((string) ($language['name'] ?? 'Language')) ?> <?= esc((string) ($language['type'] ?? '')) ?>
                    </option>
                <?php endforeach ?>
            </select>

            <label for="code_length">Length of code string</label>
            <input type="number" name="code_length" id="code_length" min="1" max="128" value="<?= esc((string) $codeLength, 'attr') ?>" required>
            <p class="hint">Only the generated code string length. Prefixes, separators, or grouping can be added later.</p>

            <label for="generation_mode">Generation mode</label>
            <select name="generation_mode" id="generation_mode" required>
                <option value="random" <?= $selectedMode === 'random' ? 'selected' : '' ?>>Random</option>
                <option value="sequential" <?= $selectedMode === 'sequential' ? 'selected' : '' ?>>Sequential</option>
            </select>
            <p class="hint">Random is recommended for security-sensitive profiles like Password.Id.</p>

            <label class="field-check">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span class="field-check-text">Profile active</span>
            </label>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save Profile</button>
                <a class="btn btn-secondary" href="<?= esc(site_url('DashBoard/SecurityManager/CANG/Index')) ?>">Back To Profiles</a>
            </div>
        </form>
    </section>
</div>
<?= $this->endSection() ?>
