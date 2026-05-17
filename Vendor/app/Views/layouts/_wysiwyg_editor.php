<?php
$wysiwygName = trim((string) ($wysiwygName ?? 'body'));
$wysiwygLabel = trim((string) ($wysiwygLabel ?? 'Body'));
$wysiwygValue = (string) ($wysiwygValue ?? '');
$wysiwygHint = trim((string) ($wysiwygHint ?? 'Visual and HTML modes stay in sync. Use Clean up before saving pasted content.'));
$wysiwygToolbarLabel = trim((string) ($wysiwygToolbarLabel ?? 'Rich text editor toolbar'));
$wysiwygEditorId = trim((string) ($wysiwygEditorId ?? $wysiwygName . '-editor'));
$wysiwygFormatId = trim((string) ($wysiwygFormatId ?? $wysiwygName . '-format'));
$wysiwygPreviewButton = trim((string) ($wysiwygPreviewButton ?? 'btn-preview'));
$wysiwygPreviewPanel = trim((string) ($wysiwygPreviewPanel ?? 'content-preview'));
$wysiwygPreviewTitleField = trim((string) ($wysiwygPreviewTitleField ?? 'title'));
$wysiwygPreviewMetaField = trim((string) ($wysiwygPreviewMetaField ?? ''));
$wysiwygPreviewMetaSelector = trim((string) ($wysiwygPreviewMetaSelector ?? ''));
$wysiwygPreviewMetaPrefix = trim((string) ($wysiwygPreviewMetaPrefix ?? ''));
$wysiwygPreviewMetaId = trim((string) ($wysiwygPreviewMetaId ?? 'preview-meta'));
$wysiwygPreviewEmptyTitle = trim((string) ($wysiwygPreviewEmptyTitle ?? 'Untitled preview'));
$wysiwygPreviewEmptyBody = trim((string) ($wysiwygPreviewEmptyBody ?? '<p>No content yet.</p>'));
$wysiwygLoadAssets = ! defined('PRODUCT_STORE_WYSIWYG_ASSETS');
if ($wysiwygLoadAssets) {
    define('PRODUCT_STORE_WYSIWYG_ASSETS', true);
}

$wysiwygIcon = static fn (string $name): string => (string) view('layouts/_wysiwyg_icon', ['icon' => $name]);
$wysiwygFontFamilies = \App\Libraries\WysiwygFonts::families();
$wysiwygFontStacksJson = json_encode(\App\Libraries\WysiwygFonts::normalizedStacks(), JSON_THROW_ON_ERROR);
?>
<?php if ($wysiwygLabel !== '') : ?>
    <label for="<?= esc($wysiwygEditorId, 'attr') ?>"><?= esc($wysiwygLabel) ?></label>
<?php endif ?>
<div
    class="wysiwyg"
    data-wysiwyg
    data-wysiwyg-source="<?= esc($wysiwygName, 'attr') ?>"
    data-wysiwyg-editor="<?= esc($wysiwygEditorId, 'attr') ?>"
    data-wysiwyg-format="<?= esc($wysiwygFormatId, 'attr') ?>"
    data-wysiwyg-preview-button="<?= esc($wysiwygPreviewButton, 'attr') ?>"
    data-wysiwyg-preview-panel="<?= esc($wysiwygPreviewPanel, 'attr') ?>"
    data-wysiwyg-preview-title-field="<?= esc($wysiwygPreviewTitleField, 'attr') ?>"
    data-wysiwyg-preview-meta-field="<?= esc($wysiwygPreviewMetaField, 'attr') ?>"
    data-wysiwyg-preview-meta-selector="<?= esc($wysiwygPreviewMetaSelector, 'attr') ?>"
    data-wysiwyg-preview-meta-prefix="<?= esc($wysiwygPreviewMetaPrefix, 'attr') ?>"
    data-wysiwyg-preview-meta="<?= esc($wysiwygPreviewMetaId, 'attr') ?>"
    data-wysiwyg-preview-empty-title="<?= esc($wysiwygPreviewEmptyTitle, 'attr') ?>"
    data-wysiwyg-preview-empty-body="<?= esc($wysiwygPreviewEmptyBody, 'attr') ?>"
    data-wysiwyg-font-stacks="<?= esc($wysiwygFontStacksJson, 'attr') ?>"
    data-wysiwyg-upload-url="<?= esc(site_url('Content/Wysiwyg/UploadImage'), 'attr') ?>"
    data-wysiwyg-csrf-name="<?= esc(csrf_token(), 'attr') ?>"
    data-wysiwyg-csrf-hash="<?= esc(csrf_hash(), 'attr') ?>"
>
    <div class="wysiwyg-toolbar" aria-label="<?= esc($wysiwygToolbarLabel) ?>">
        <div class="wysiwyg-group">
            <span class="wysiwyg-group-label">Format</span>
            <span class="wysiwyg-select-icon" title="Text format"><?= $wysiwygIcon('format') ?></span>
            <select id="<?= esc($wysiwygFormatId) ?>" aria-label="Text format">
                <option value="h1">Heading 1</option>
                <option value="h2">Heading 2</option>
                <option value="h3">Heading 3</option>
                <option value="h4">Heading 4</option>
                <option value="h5">Heading 5</option>
                <option value="h6">Heading 6</option>
                <option value="pre">Code block</option>
            </select>
            <button type="button" data-block="blockquote" title="Quote" aria-label="Quote"><?= $wysiwygIcon('quote') ?><span class="wysiwyg-btn-label">Quote</span></button>
        </div>
        <div class="wysiwyg-group">
            <span class="wysiwyg-group-label">Text</span>
            <span class="wysiwyg-select-icon" title="Font"><?= $wysiwygIcon('type') ?></span>
            <select class="wysiwyg-font-select" data-wysiwyg-font-family aria-label="Font family">
                <option value="">Font</option>
                <?php foreach ($wysiwygFontFamilies as $fontFamily) : ?>
                    <option value="<?= esc((string) $fontFamily['stack'], 'attr') ?>" style="font-family: <?= esc((string) $fontFamily['stack'], 'attr') ?>"><?= esc((string) $fontFamily['label']) ?></option>
                <?php endforeach ?>
            </select>
            <select class="wysiwyg-font-select wysiwyg-font-size-select" data-wysiwyg-font-size aria-label="Font size">
                <option value="">Size</option>
                <option value="12px">12</option>
                <option value="14px">14</option>
                <option value="16px">16</option>
                <option value="18px">18</option>
                <option value="20px">20</option>
                <option value="24px">24</option>
                <option value="28px">28</option>
                <option value="32px">32</option>
            </select>
            <button type="button" data-command="bold" title="Bold" aria-label="Bold"><?= $wysiwygIcon('bold') ?><span class="wysiwyg-btn-label">Bold</span></button>
            <button type="button" data-command="italic" title="Italic" aria-label="Italic"><?= $wysiwygIcon('italic') ?><span class="wysiwyg-btn-label">Italic</span></button>
            <button type="button" data-command="underline" title="Underline" aria-label="Underline"><?= $wysiwygIcon('underline') ?><span class="wysiwyg-btn-label">Underline</span></button>
            <button type="button" data-command="strikeThrough" title="Strikethrough" aria-label="Strikethrough"><?= $wysiwygIcon('strikethrough') ?><span class="wysiwyg-btn-label">Strike</span></button>
            <label class="wysiwyg-color" title="Text color">
                <span class="sr-only">Text color</span>
                <span class="wysiwyg-color-swatch" aria-hidden="true"><?= $wysiwygIcon('palette') ?></span>
                <input type="color" value="#e8eaed" data-wysiwyg-color aria-label="Text color">
            </label>
            <button type="button" data-command="removeFormat" title="Clear formatting" aria-label="Clear formatting"><?= $wysiwygIcon('clear') ?><span class="wysiwyg-btn-label">Clear</span></button>
        </div>
        <div class="wysiwyg-group">
            <span class="wysiwyg-group-label">Lists</span>
            <button type="button" data-command="insertUnorderedList" title="Bullet list" aria-label="Bullet list"><?= $wysiwygIcon('bullets') ?><span class="wysiwyg-btn-label">Bullets</span></button>
            <button type="button" data-command="insertOrderedList" title="Numbered list" aria-label="Numbered list"><?= $wysiwygIcon('numbers') ?><span class="wysiwyg-btn-label">Numbers</span></button>
            <button type="button" data-command="outdent" title="Outdent" aria-label="Outdent"><?= $wysiwygIcon('outdent') ?><span class="wysiwyg-btn-label">Outdent</span></button>
            <button type="button" data-command="indent" title="Indent" aria-label="Indent"><?= $wysiwygIcon('indent') ?><span class="wysiwyg-btn-label">Indent</span></button>
        </div>
        <div class="wysiwyg-group wysiwyg-group-icons">
            <span class="wysiwyg-group-label">Alignment</span>
            <button type="button" data-command="justifyLeft" title="Align left" aria-label="Align left"><?= $wysiwygIcon('align-left') ?></button>
            <button type="button" data-command="justifyCenter" title="Align center" aria-label="Align center"><?= $wysiwygIcon('align-center') ?></button>
            <button type="button" data-command="justifyRight" title="Align right" aria-label="Align right"><?= $wysiwygIcon('align-right') ?></button>
            <button type="button" data-command="justifyFull" title="Justify" aria-label="Justify"><?= $wysiwygIcon('align-justify') ?></button>
        </div>
        <div class="wysiwyg-group">
            <span class="wysiwyg-group-label">Insert</span>
            <button type="button" data-action="toggle-link" title="Insert link" aria-label="Insert link"><?= $wysiwygIcon('link') ?><span class="wysiwyg-btn-label">Link</span></button>
            <button type="button" data-action="unlink" title="Remove link" aria-label="Remove link"><?= $wysiwygIcon('unlink') ?><span class="wysiwyg-btn-label">Unlink</span></button>
            <button type="button" data-action="toggle-image" title="Insert image" aria-label="Insert image"><?= $wysiwygIcon('image') ?><span class="wysiwyg-btn-label">Image</span></button>
            <button type="button" data-command="insertHorizontalRule" title="Horizontal line" aria-label="Horizontal line"><?= $wysiwygIcon('line') ?><span class="wysiwyg-btn-label">Line</span></button>
        </div>
        <div class="wysiwyg-group">
            <span class="wysiwyg-group-label">Tools</span>
            <button type="button" data-action="clean" title="Remove junk markup from pasted HTML" aria-label="Clean up HTML"><?= $wysiwygIcon('clean') ?><span class="wysiwyg-btn-label">Clean</span></button>
            <button type="button" data-action="toggle-find" title="Find and replace" aria-label="Find and replace"><?= $wysiwygIcon('find') ?><span class="wysiwyg-btn-label">Find</span></button>
            <select data-wysiwyg-placeholder-count aria-label="Draft text paragraphs">
                <option value="1">1 paragraph</option>
                <option value="2">2 paragraphs</option>
                <option value="3">3 paragraphs</option>
                <option value="4">4 paragraphs</option>
                <option value="5">5 paragraphs</option>
            </select>
            <button type="button" data-action="placeholder" title="Insert placeholder draft paragraphs" aria-label="Insert draft text"><?= $wysiwygIcon('draft') ?><span class="wysiwyg-btn-label">Draft</span></button>
        </div>
        <div class="wysiwyg-group">
            <span class="wysiwyg-group-label">View</span>
            <button type="button" data-command="undo" title="Undo" aria-label="Undo"><?= $wysiwygIcon('undo') ?><span class="wysiwyg-btn-label">Undo</span></button>
            <button type="button" data-command="redo" title="Redo" aria-label="Redo"><?= $wysiwygIcon('redo') ?><span class="wysiwyg-btn-label">Redo</span></button>
            <button type="button" data-action="source" title="HTML source" aria-label="HTML source"><?= $wysiwygIcon('code') ?><span class="wysiwyg-source-label">HTML</span></button>
        </div>
    </div>
    <div class="wysiwyg-panel wysiwyg-insert-panel" data-wysiwyg-link-panel hidden>
        <p class="wysiwyg-panel-title">Insert link</p>
        <label class="wysiwyg-panel-field">
            <span>URL</span>
            <input type="url" data-wysiwyg-link-url placeholder="https://example.com" autocomplete="off" spellcheck="false">
        </label>
        <p class="wysiwyg-panel-message" data-wysiwyg-panel-message hidden></p>
        <div class="wysiwyg-panel-actions">
            <button type="button" class="btn btn-primary" data-action="insert-link">Insert link</button>
            <button type="button" class="btn btn-secondary" data-action="close-insert-panels">Cancel</button>
        </div>
    </div>
    <div class="wysiwyg-panel wysiwyg-insert-panel" data-wysiwyg-image-panel hidden>
        <p class="wysiwyg-panel-title">Insert image</p>
        <label class="wysiwyg-panel-field">
            <span>Image URL</span>
            <input type="url" data-wysiwyg-image-url placeholder="https://example.com/photo.jpg" autocomplete="off" spellcheck="false">
        </label>
        <button type="button" class="btn btn-secondary" data-action="insert-image-url">Insert from URL</button>
        <label class="wysiwyg-panel-field wysiwyg-panel-field-file">
            <span>Upload image</span>
            <input type="file" data-wysiwyg-image-file accept="image/jpeg,image/png,image/gif,image/webp">
        </label>
        <button type="button" class="btn btn-primary" data-action="upload-image">Upload and insert</button>
        <p class="wysiwyg-panel-message" data-wysiwyg-panel-message hidden></p>
        <button type="button" class="btn btn-secondary" data-action="close-insert-panels">Cancel</button>
    </div>
    <div class="wysiwyg-panel wysiwyg-find-panel" data-wysiwyg-find-panel hidden>
        <label class="wysiwyg-panel-field">
            <span>Find</span>
            <input type="text" data-wysiwyg-find autocomplete="off" spellcheck="false">
        </label>
        <label class="wysiwyg-panel-field">
            <span>Replace with</span>
            <input type="text" data-wysiwyg-replace autocomplete="off" spellcheck="false">
        </label>
        <button type="button" class="btn btn-secondary wysiwyg-btn-icon" data-action="find-replace" title="Replace all" aria-label="Replace all"><?= $wysiwygIcon('replace') ?><span class="wysiwyg-btn-label">Replace all</span></button>
    </div>
    <div id="<?= esc($wysiwygEditorId) ?>" class="wysiwyg-editor" contenteditable="true"></div>
    <textarea name="<?= esc($wysiwygName) ?>" id="<?= esc($wysiwygName) ?>" class="wysiwyg-source" rows="12" spellcheck="false"><?= esc($wysiwygValue) ?></textarea>
    <div class="wysiwyg-status" aria-live="polite">
        <span data-wysiwyg-status-mode>Visual</span>
        <span data-wysiwyg-status-count>0 characters</span>
    </div>
</div>
<?php if ($wysiwygHint !== '') : ?>
    <p class="hint"><?= esc($wysiwygHint) ?></p>
<?php endif ?>
<?php if ($wysiwygLoadAssets) : ?>
    <script src="<?= esc(base_url('Vendor/public/assets/wysiwyg-editor.js?v=11')) ?>" defer></script>
<?php endif ?>
