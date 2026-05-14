<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$isEdit = ($mode ?? '') === 'edit';
$postId = (int) ($post['id'] ?? 0);
$action = $isEdit ? site_url('Content/Community/Edit/' . $postId) : site_url('Content/Community/Create');
$titleValue = (string) ($post['title'] ?? '');
$categoryValue = (string) ($post['category'] ?? '');
$bodyValue = (string) ($post['body'] ?? '');
$statusValue = (string) ($post['status'] ?? 'published');
?>

<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc($action) ?>" class="card">
    <?= csrf_field() ?>

    <label for="title">Title</label>
    <input type="text" name="title" id="title" value="<?= old('title', $titleValue, 'attr') ?>" required maxlength="180">

    <label for="category">Category</label>
    <?php $selectedCategory = old('category', $categoryValue !== '' ? $categoryValue : 'Unknown'); ?>
    <select name="category" id="category" required>
        <?php foreach (($categories ?? ['Unknown' => 'Unknown']) as $value => $label) : ?>
            <option value="<?= esc((string) $value) ?>" <?= $selectedCategory === (string) $value ? 'selected' : '' ?>>
                <?= esc((string) $label) ?>
            </option>
        <?php endforeach ?>
    </select>
    <p class="hint">Use <code>Unknown</code> when the post does not belong to a managed category.</p>

    <style>
        .wysiwyg { border: 1px solid rgba(255,255,255,.12); border-radius: 8px; background: #0c1016; overflow: hidden; }
        .wysiwyg-toolbar { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: stretch; padding: 0.45rem; border-bottom: 1px solid rgba(255,255,255,.08); background: rgba(255,255,255,.03); }
        .wysiwyg-group { display: inline-flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; padding: 0.3rem; border: 1px solid rgba(255,255,255,.08); border-radius: 7px; background: rgba(255,255,255,.025); }
        .wysiwyg-group-label { flex: 0 0 100%; color: var(--muted); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
        .wysiwyg-toolbar select { width: auto; min-width: 7.5rem; padding: 0.32rem 0.45rem; font-size: 0.78rem; }
        .wysiwyg-toolbar button { width: auto; background: rgba(255,255,255,.08); color: var(--text); border: 1px solid rgba(255,255,255,.1); padding: 0.32rem 0.45rem; font-size: 0.78rem; min-width: 2rem; min-height: 2rem; }
        .wysiwyg-toolbar button:hover { background: rgba(255,255,255,.14); }
        .wysiwyg-editor { min-height: 20rem; padding: 0.85rem 1rem; outline: none; color: var(--text); }
        .wysiwyg-editor:focus { box-shadow: inset 0 0 0 2px rgba(61,139,253,.45); }
        .wysiwyg-editor p { margin: 0 0 0.8rem; }
        .wysiwyg-editor h2, .wysiwyg-editor h3, .wysiwyg-editor h4 { margin: 1rem 0 0.5rem; }
        .wysiwyg-editor blockquote { margin: 0.8rem 0; padding-left: 1rem; border-left: 3px solid var(--accent); color: var(--muted); }
        .wysiwyg-editor pre { white-space: pre-wrap; }
        .wysiwyg-editor img { max-width: 100%; height: auto; border-radius: 8px; }
        .wysiwyg-source { display: none; min-height: 20rem; border: 0; border-radius: 0; font-family: Consolas, Monaco, monospace; }
        .wysiwyg-source.is-visible { display: block; }
        .wysiwyg-editor.is-hidden { display: none; }
        .content-preview { display: none; margin-top: 1rem; }
        .content-preview.is-visible { display: block; }
        .content-preview h2 { margin-bottom: 0.35rem; }
        .content-preview .content-body { margin-top: 1rem; }
    </style>

    <label for="body">Body</label>
    <div class="wysiwyg" data-wysiwyg>
        <div class="wysiwyg-toolbar" aria-label="Community post editor toolbar">
            <div class="wysiwyg-group">
                <span class="wysiwyg-group-label">Format</span>
                <select id="body-format" aria-label="Text format">
                    <option value="p">Paragraph</option>
                    <option value="h2">Heading 2</option>
                    <option value="h3">Heading 3</option>
                    <option value="h4">Heading 4</option>
                    <option value="pre">Code block</option>
                </select>
                <button type="button" data-block="blockquote">Quote</button>
            </div>
            <div class="wysiwyg-group">
                <span class="wysiwyg-group-label">Text</span>
                <button type="button" data-command="bold"><strong>B</strong></button>
                <button type="button" data-command="italic"><em>I</em></button>
                <button type="button" data-command="underline"><u>U</u></button>
                <button type="button" data-command="strikeThrough"><s>S</s></button>
                <button type="button" data-command="removeFormat">Clear</button>
            </div>
            <div class="wysiwyg-group">
                <span class="wysiwyg-group-label">Lists</span>
                <button type="button" data-command="insertUnorderedList">Bullets</button>
                <button type="button" data-command="insertOrderedList">Numbers</button>
                <button type="button" data-command="outdent">Outdent</button>
                <button type="button" data-command="indent">Indent</button>
            </div>
            <div class="wysiwyg-group">
                <span class="wysiwyg-group-label">Alignment</span>
                <button type="button" data-command="justifyLeft">Left</button>
                <button type="button" data-command="justifyCenter">Center</button>
                <button type="button" data-command="justifyRight">Right</button>
                <button type="button" data-command="justifyFull">Justify</button>
            </div>
            <div class="wysiwyg-group">
                <span class="wysiwyg-group-label">Insert</span>
                <button type="button" data-action="link">Link</button>
                <button type="button" data-action="image">Image</button>
                <button type="button" data-command="insertHorizontalRule">Line</button>
            </div>
            <div class="wysiwyg-group">
                <span class="wysiwyg-group-label">History</span>
                <button type="button" data-command="undo">Undo</button>
                <button type="button" data-command="redo">Redo</button>
                <button type="button" data-action="source">HTML</button>
            </div>
        </div>
        <div id="body-editor" class="wysiwyg-editor" contenteditable="true"></div>
        <textarea name="body" id="body" class="wysiwyg-source" rows="12"><?= esc(old('body', $bodyValue)) ?></textarea>
    </div>
    <p class="hint">Use the toolbar for rich community posts. HTML source mode is available for fine adjustments.</p>

    <label for="status">Status</label>
    <?php $selectedStatus = old('status', $statusValue); ?>
    <select name="status" id="status" required>
        <option value="draft" <?= $selectedStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= $selectedStatus === 'published' ? 'selected' : '' ?>>Published</option>
    </select>

    <div class="actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save post' : 'Create post' ?></button>
        <button type="button" class="btn btn-secondary" id="btn-preview">Preview</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Community/Index')) ?>">Back to posts</a>
        <?php if ($isEdit) : ?>
            <a class="btn btn-danger" href="<?= esc(site_url('Content/Community/Delete/' . $postId)) ?>">Delete post</a>
        <?php endif ?>
    </div>

    <div class="card prose content-preview" id="content-preview" aria-live="polite">
        <h2 id="preview-title">Preview</h2>
        <p id="preview-category"></p>
        <div class="content-body" id="preview-body"></div>
    </div>
</form>
<script>
(function () {
    const source = document.getElementById('body');
    const editor = document.getElementById('body-editor');
    const form = source ? source.closest('form') : null;
    const format = document.getElementById('body-format');
    const toolbar = document.querySelector('[data-wysiwyg] .wysiwyg-toolbar');
    const previewButton = document.getElementById('btn-preview');
    const preview = document.getElementById('content-preview');
    const previewTitle = document.getElementById('preview-title');
    const previewCategory = document.getElementById('preview-category');
    const previewBody = document.getElementById('preview-body');
    let sourceMode = false;

    if (! source || ! editor || ! form || ! toolbar) {
        return;
    }

    editor.innerHTML = source.value.trim() || '<p><br></p>';

    function syncSource() {
        if (! sourceMode) {
            source.value = editor.innerHTML.trim();
        }
    }

    function syncEditor() {
        editor.innerHTML = source.value.trim() || '<p><br></p>';
    }

    function focusEditor() {
        editor.focus();
    }

    function normalizeCreatedLinks() {
        editor.querySelectorAll('a[href]').forEach(function (link) {
            if (/^https?:\/\//i.test(link.getAttribute('href') || '')) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            }
        });
    }

    function showPreview() {
        if (! preview || ! previewTitle || ! previewCategory || ! previewBody) {
            return;
        }

        const title = (document.getElementById('title')?.value || '').trim() || 'Untitled preview';
        const category = (document.getElementById('category')?.value || '').trim() || 'Unknown';
        const body = sourceMode ? source.value.trim() : editor.innerHTML.trim();

        previewTitle.textContent = title;
        previewCategory.textContent = 'Category: ' + category;
        previewBody.innerHTML = body || '<p>No body content yet.</p>';
        preview.classList.add('is-visible');
        preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (format) {
        format.addEventListener('change', function () {
            focusEditor();
            document.execCommand('formatBlock', false, format.value);
            syncSource();
        });
    }

    toolbar.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (! button) {
            return;
        }

        if (sourceMode && button.dataset.action !== 'source') {
            return;
        }

        const command = button.dataset.command;
        const block = button.dataset.block;
        const action = button.dataset.action;

        if (command) {
            focusEditor();
            document.execCommand(command, false, null);
            syncSource();
            return;
        }

        if (block) {
            focusEditor();
            document.execCommand('formatBlock', false, block);
            syncSource();
            return;
        }

        if (action === 'link') {
            const url = window.prompt('Link URL');
            if (url) {
                focusEditor();
                document.execCommand('createLink', false, url);
                normalizeCreatedLinks();
                syncSource();
            }
            return;
        }

        if (action === 'image') {
            const url = window.prompt('Image URL');
            if (url) {
                focusEditor();
                document.execCommand('insertImage', false, url);
                syncSource();
            }
            return;
        }

        if (action === 'source') {
            if (sourceMode) {
                sourceMode = false;
                syncEditor();
                source.classList.remove('is-visible');
                editor.classList.remove('is-hidden');
                button.textContent = 'HTML';
                focusEditor();
            } else {
                syncSource();
                sourceMode = true;
                source.classList.add('is-visible');
                editor.classList.add('is-hidden');
                button.textContent = 'Visual';
                source.focus();
            }
        }
    });

    editor.addEventListener('input', syncSource);
    if (previewButton) {
        previewButton.addEventListener('click', function () {
            if (! sourceMode) {
                syncSource();
            }
            showPreview();
        });
    }
    form.addEventListener('submit', function () {
        if (sourceMode) {
            syncEditor();
        }
        syncSource();
    });
})();
</script>
<?= $this->endSection() ?>
