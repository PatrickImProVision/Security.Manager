<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$isEdit = ($mode ?? '') === 'edit';
$isEditReply = ($mode ?? '') === 'edit-reply';
$postId = (int) ($post['id'] ?? 0);
$topicId = (int) ($topicId ?? 0);
$action = $isEdit || $isEditReply
    ? \App\Libraries\CommunityContentUrls::editUrl($post)
    : site_url('Content/Community/Create');
$titleValue = (string) ($post['title'] ?? '');
$categoryIdValue = (int) ($post['category_id'] ?? 0);
$bodyValue = (string) ($post['body'] ?? '');
$statusValue = (string) ($post['status'] ?? 'published');
?>

<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc($action) ?>" class="card">
    <?= csrf_field() ?>

    <?php if (! $isEditReply) : ?>
        <label for="title">Title</label>
        <input type="text" name="title" id="title" value="<?= old('title', $titleValue, 'attr') ?>" required maxlength="180">

        <label for="category_id">Forum</label>
        <?php $selectedCategoryId = (int) old('category_id', $categoryIdValue); ?>
        <select name="category_id" id="category_id" required>
            <?php foreach (($categories ?? []) as $value => $label) : ?>
                <option value="<?= esc((string) $value, 'attr') ?>" <?= $selectedCategoryId === (int) $value ? 'selected' : '' ?>>
                    <?= esc((string) $label) ?>
                </option>
            <?php endforeach ?>
        </select>
    <?php endif ?>
    <?= view('layouts/_wysiwyg_editor', [
    'wysiwygValue' => old('body', $bodyValue),
    'wysiwygLabel' => 'Body',
    'wysiwygToolbarLabel' => 'Community post editor toolbar',
    'wysiwygHint' => 'Use the toolbar for rich community posts. HTML source mode is available for fine adjustments.',
    'wysiwygPreviewTitleField' => 'title',
    'wysiwygPreviewMetaField' => 'category',
    'wysiwygPreviewMetaId' => 'preview-category',
    'wysiwygPreviewMetaPrefix' => 'Category: ',
    'wysiwygPreviewEmptyBody' => '<p>No body content yet.</p>',
]) ?>
<?php if (! $isEditReply) : ?>
        <label for="status">Status</label>
        <?php $selectedStatus = old('status', $statusValue); ?>
        <select name="status" id="status" required>
            <option value="draft" <?= $selectedStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $selectedStatus === 'published' ? 'selected' : '' ?>>Published</option>
        </select>
    <?php endif ?>

    <div class="actions">
        <button type="submit" class="btn btn-primary">
            <?= $isEditReply ? 'Save reply' : ($isEdit ? 'Save topic' : 'Create topic') ?>
        </button>
        <?php if (! $isEditReply) : ?>
            <button type="button" class="btn btn-secondary" id="btn-preview">Preview</button>
        <?php endif ?>
        <?php if ($isEditReply && $topicId > 0) : ?>
            <a class="btn btn-secondary" href="<?= esc((string) ($topicUrl ?? \App\Libraries\CommunityContentUrls::topicUrl(['id' => $topicId]))) ?>">Back to topic</a>
        <?php else : ?>
            <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Index')) ?>">Back to forum</a>
        <?php endif ?>
        <?php if ($isEdit || $isEditReply) : ?>
            <a class="btn btn-danger" href="<?= esc(\App\Libraries\CommunityContentUrls::deleteUrl($post)) ?>">Delete</a>
        <?php endif ?>
    </div>
    <?= view('layouts/_wysiwyg_preview', ['wysiwygShowMeta' => true, 'wysiwygPreviewMetaId' => 'preview-category']) ?>
</form>
<?= $this->endSection() ?>
