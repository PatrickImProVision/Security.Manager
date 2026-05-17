<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$isEdit = ($mode ?? '') === 'edit';
$contentId = (int) ($content['id'] ?? 0);
$action = $isEdit
    ? \App\Libraries\PublicContentUrls::editUrl($content)
    : site_url('Content/Public/Create');
$titleValue = (string) ($content['title'] ?? '');
$slugValue = (string) ($content['slug'] ?? '');
$summaryValue = (string) ($content['summary'] ?? '');
$bodyValue = (string) ($content['body'] ?? '');
$statusValue = (string) ($content['status'] ?? 'draft');
$showInNavValue = ! empty($content['show_in_nav']);
$navLabelValue = (string) ($content['nav_label'] ?? '');
$navOrderValue = (string) ($content['nav_order'] ?? '0');
$publishedAtValue = '';
if (! empty($content['published_at']) && strtotime((string) $content['published_at']) !== false) {
    $publishedAtValue = date('Y-m-d\TH:i', strtotime((string) $content['published_at']));
}
?>

<?= view('layouts/_site_header', [
    'pageHeading'     => (string) ($pageHeading ?? ''),
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc($action) ?>" class="card">
    <?= csrf_field() ?>

    <h2><?= $isEdit ? 'Edit Public Content' : 'Create Public Content' ?></h2>

    <label for="title">Title</label>
    <input type="text" name="title" id="title" value="<?= old('title', $titleValue, 'attr') ?>" required maxlength="180">

    <label for="slug">Slug</label>
    <input type="text" name="slug" id="slug" value="<?= old('slug', $slugValue, 'attr') ?>" maxlength="191" placeholder="about-us">
    <p class="hint">Leave blank to generate it from the title. Slugs use lowercase letters, numbers, and dashes.</p>

    <div class="card" style="margin-top:1rem;">
        <h2 style="font-size:1rem;margin:0 0 0.5rem;">Navigation</h2>
        <label class="field-check">
            <input type="checkbox" name="show_in_nav" value="1" <?= old('show_in_nav', $showInNavValue ? '1' : '') ? 'checked' : '' ?>>
            <span class="field-check-text">Show this page in the navigation bar</span>
        </label>
        <p class="hint">Only published pages are shown in the navigation bar.</p>

        <div class="row row-user-pass">
            <div>
                <label for="nav_label">Navigation label</label>
                <input type="text" name="nav_label" id="nav_label" value="<?= old('nav_label', $navLabelValue, 'attr') ?>" maxlength="100" placeholder="About Us">
                <p class="hint">Leave blank to use the page title.</p>
            </div>
            <div>
                <label for="nav_order">Navigation order</label>
                <input type="number" name="nav_order" id="nav_order" value="<?= old('nav_order', $navOrderValue, 'attr') ?>">
                <p class="hint">Lower numbers appear first.</p>
            </div>
        </div>
    </div>

    <label for="summary">SEO Description</label>
    <textarea name="summary" id="summary" rows="3" maxlength="500"><?= esc(old('summary', $summaryValue)) ?></textarea>
    <p class="hint">Short description for previews, search results, and future SEO meta description.</p>

    <?= view('layouts/_wysiwyg_editor', [
        'wysiwygValue'            => old('body', $bodyValue),
        'wysiwygLabel'            => 'Body',
        'wysiwygToolbarLabel'     => 'Content editor toolbar',
        'wysiwygPreviewMetaField' => 'summary',
        'wysiwygPreviewMetaId'    => 'preview-summary',
        'wysiwygPreviewEmptyBody' => '<p>No body content yet.</p>',
    ]) ?>
<div class="row row-user-pass">
        <div>
            <label for="status">Status</label>
            <?php $selectedStatus = old('status', $statusValue); ?>
            <select name="status" id="status" required>
                <option value="draft" <?= $selectedStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $selectedStatus === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>
        <div>
            <label for="published_at">Publish date</label>
            <input type="datetime-local" name="published_at" id="published_at" value="<?= old('published_at', $publishedAtValue, 'attr') ?>">
        </div>
    </div>
    <p class="hint">If status is Published and no date is set, the current time is used.</p>

    <div class="actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save content' : 'Create content' ?></button>
        <button type="button" class="btn btn-secondary" id="btn-preview">Preview</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Public/Index')) ?>">Back to contents</a>
        <?php if ($isEdit) : ?>
            <a class="btn btn-danger" href="<?= esc(\App\Libraries\PublicContentUrls::deleteUrl($content)) ?>">Delete content</a>
        <?php endif ?>
    </div>

    <?= view('layouts/_wysiwyg_preview', ['wysiwygShowMeta' => true, 'wysiwygPreviewMetaId' => 'preview-summary']) ?>
</form>
<?= $this->endSection() ?>
