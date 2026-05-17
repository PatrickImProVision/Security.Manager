<?php
$wysiwygPreviewPanel = trim((string) ($wysiwygPreviewPanel ?? 'content-preview'));
$wysiwygPreviewMetaId = trim((string) ($wysiwygPreviewMetaId ?? 'preview-meta'));
$wysiwygShowMeta = ! empty($wysiwygShowMeta);
?>
<div class="card prose content-preview" id="<?= esc($wysiwygPreviewPanel) ?>" aria-live="polite">
    <h2 id="preview-title">Preview</h2>
    <?php if ($wysiwygShowMeta) : ?>
        <p id="<?= esc($wysiwygPreviewMetaId) ?>"></p>
    <?php endif ?>
    <div class="content-body" id="preview-body"></div>
</div>
