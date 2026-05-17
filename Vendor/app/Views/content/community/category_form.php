<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$nodeType = (string) ($nodeType ?? 'forum');
$category = is_array($category ?? null) ? $category : [];
$categoryId = (int) ($category['id'] ?? 0);
$isSystem = ! empty($category['is_system']);
$parentOptions = is_array($parentOptions ?? null) ? $parentOptions : [];
$isCategory = $nodeType === 'category';
$typeLabel = match ($nodeType) {
    'category' => 'Category',
    'subforum' => 'Sub-forum',
    default    => 'Forum',
};
?>

<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card phpbb-acp-form">
    <h2><?= $isEdit ? 'Edit' : 'Create' ?> <?= esc($typeLabel) ?></h2>
    <p class="hint phpbb-acp-form-lead">
        <?php if ($isCategory) : ?>
            A <strong>category</strong> is a top-level board group (like phpBB). It can contain forums, sub-forums, and topics.
        <?php elseif ($nodeType === 'subforum') : ?>
            A <strong>sub-forum</strong> lives under another forum or category and can hold topics and further sub-forums.
        <?php else : ?>
            A <strong>forum</strong> holds topics and may contain sub-forums beneath it.
        <?php endif ?>
    </p>

    <form method="post" action="<?= esc(site_url('Content/Community/Categories/Save')) ?>" class="phpbb-acp-form-body">
        <?= csrf_field() ?>
        <?php if ($isEdit) : ?>
            <input type="hidden" name="id" value="<?= esc((string) $categoryId, 'attr') ?>">
        <?php endif ?>
        <input type="hidden" name="node_type" value="<?= esc($nodeType, 'attr') ?>">

        <div class="phpbb-acp-form-grid">
            <div class="phpbb-acp-field phpbb-acp-field-wide">
                <label for="name"><?= esc($typeLabel) ?> name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="<?= esc(old('name', (string) ($category['name'] ?? '')), 'attr') ?>"
                    required
                    maxlength="100"
                    <?= $isSystem ? 'readonly' : '' ?>
                >
            </div>

            <?php if (! $isCategory && ! $isSystem) : ?>
                <div class="phpbb-acp-field phpbb-acp-field-wide">
                    <label for="parent_id">Parent forum</label>
                    <select name="parent_id" id="parent_id">
                        <option value="0">— Top level (no parent) —</option>
                        <?php
                        $selectedParent = (int) old('parent_id', (int) ($category['parent_id'] ?? 0));
                        foreach ($parentOptions as $pid => $label) :
                            ?>
                            <option value="<?= esc((string) $pid, 'attr') ?>" <?= $selectedParent === (int) $pid ? 'selected' : '' ?>>
                                <?= esc((string) $label) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <p class="hint">Choose the category or forum this belongs under.</p>
                </div>
            <?php else : ?>
                <input type="hidden" name="parent_id" value="0">
            <?php endif ?>

            <div class="phpbb-acp-field phpbb-acp-field-wide">
                <label for="description">Description</label>
                <input
                    type="text"
                    name="description"
                    id="description"
                    value="<?= esc(old('description', (string) ($category['description'] ?? '')), 'attr') ?>"
                    maxlength="255"
                    placeholder="Optional — shown on the forum index"
                >
            </div>

            <div class="phpbb-acp-field">
                <label for="sort_order">Position</label>
                <input type="number" name="sort_order" id="sort_order" value="<?= esc(old('sort_order', (string) ($category['sort_order'] ?? 0)), 'attr') ?>">
                <p class="hint">Lower numbers appear first.</p>
            </div>

            <div class="phpbb-acp-field">
                <label class="field-check">
                    <input type="checkbox" name="is_active" value="1" <?= old('is_active', ! empty($category['is_active']) ? '1' : '') !== '' ? 'checked' : '' ?> <?= $isSystem ? 'disabled' : '' ?>>
                    <?php if ($isSystem) : ?>
                        <input type="hidden" name="is_active" value="1">
                    <?php endif ?>
                    <span class="field-check-text">Forum visible on index</span>
                </label>
            </div>
        </div>

        <?php if ($isEdit && trim((string) ($category['slug'] ?? '')) !== '') : ?>
            <p class="hint">URL slug: <code><?= esc((string) $category['slug']) ?></code></p>
        <?php endif ?>

        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create ' . strtolower($typeLabel) ?></button>
            <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Categories/Index')) ?>">Cancel</a>
        </div>
    </form>

    <?php if ($isEdit && ! $isSystem) : ?>
        <form method="post" action="<?= esc(site_url('Content/Community/Categories/Delete/' . $categoryId)) ?>" class="phpbb-acp-delete-form" onsubmit="return confirm('Delete this forum? Topics will move to Unknown.');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete <?= esc(strtolower($typeLabel)) ?></button>
        </form>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
