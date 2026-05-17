/**
 * Shared Product Store WYSIWYG editor (contenteditable + toolbar).
 * Inspired by professional HTML composer patterns (instant source sync, cleanup, find/replace)
 * without third-party editor dependencies.
 */
(function () {
    'use strict';

    const PLACEHOLDER_SENTENCE =
        'Draft paragraph text — replace this placeholder with your own content before publishing.';

    const DEFAULT_ALLOWED_FONT_FAMILIES = new Set([
        'arial, helvetica, sans-serif',
        'helvetica neue, helvetica, arial, sans-serif',
        'segoe ui, system-ui, sans-serif',
        'tahoma, geneva, sans-serif',
        'verdana, geneva, sans-serif',
        'trebuchet ms, helvetica, sans-serif',
        'calibri, candara, segoe, segoe ui, optima, arial, sans-serif',
        'lucida sans unicode, lucida grande, sans-serif',
        'century gothic, centurygothic, applegothic, sans-serif',
        'franklin gothic medium, arial narrow, arial, sans-serif',
        'arial black, gadget, sans-serif',
        'impact, haettenschweiler, franklin gothic bold, sans-serif',
        'georgia, serif',
        'times new roman, times, serif',
        'garamond, baskerville, times new roman, serif',
        'palatino linotype, palatino, serif',
        'book antiqua, palatino, serif',
        'cambria, georgia, serif',
        'didot, baskerville, segoe ui, serif',
        'courier new, courier, monospace',
        'consolas, monaco, monospace',
        'lucida console, monaco, monospace',
        'comic sans ms, comic sans, cursive',
        'brush script mt, cursive',
    ]);

    function loadAllowedFontFamilies(root) {
        const raw = root ? root.dataset.wysiwygFontStacks : '';
        if (!raw) {
            return DEFAULT_ALLOWED_FONT_FAMILIES;
        }

        try {
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed) || parsed.length === 0) {
                return DEFAULT_ALLOWED_FONT_FAMILIES;
            }

            return new Set(parsed.map(normalizeFontFamily));
        } catch (error) {
            return DEFAULT_ALLOWED_FONT_FAMILIES;
        }
    }

    const ALLOWED_FONT_SIZES = new Set(['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px']);

    const STYLE_ALIGN_TAGS = new Set(['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'SPAN']);

    function normalizeFontFamily(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/["']/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function buildAllowedStyleString(styleValue, tagName, allowedFontFamilies) {
        const families = allowedFontFamilies || DEFAULT_ALLOWED_FONT_FAMILIES;
        const styles = [];
        const align = String(styleValue || '').match(/text-align\s*:\s*(left|right|center|justify)/i);
        const color = String(styleValue || '').match(/color\s*:\s*(#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))/i);
        const family = String(styleValue || '').match(/font-family\s*:\s*([^;]+)/i);
        const size = String(styleValue || '').match(/font-size\s*:\s*(\d{1,2}px)/i);

        if (align && STYLE_ALIGN_TAGS.has(tagName)) {
            styles.push('text-align: ' + align[1].toLowerCase());
        }

        if (color) {
            styles.push('color: ' + color[1].toLowerCase());
        }

        if (family) {
            const normalized = normalizeFontFamily(family[1]);
            if (families.has(normalized)) {
                styles.push('font-family: ' + normalized);
            }
        }

        if (size) {
            const normalized = size[1].toLowerCase();
            if (ALLOWED_FONT_SIZES.has(normalized)) {
                styles.push('font-size: ' + normalized);
            }
        }

        return styles.length > 0 ? styles.join('; ') + ';' : '';
    }

    function convertFontTagsToSpans(root) {
        root.querySelectorAll('font').forEach(function (node) {
            const span = document.createElement('span');
            const face = node.getAttribute('face');
            const size = node.getAttribute('size');

            if (face) {
                span.style.fontFamily = face;
            }

            if (size) {
                const map = { '1': '12px', '2': '14px', '3': '16px', '4': '18px', '5': '20px', '6': '24px', '7': '32px' };
                const px = map[String(size)] || '';
                if (px && ALLOWED_FONT_SIZES.has(px)) {
                    span.style.fontSize = px;
                }
            }

            while (node.firstChild) {
                span.appendChild(node.firstChild);
            }

            node.parentNode.replaceChild(span, node);
        });
    }

    function unwrapEmptySpans(root, allowedFontFamilies) {
        root.querySelectorAll('span').forEach(function (node) {
            const style = buildAllowedStyleString(node.getAttribute('style') || '', 'SPAN', allowedFontFamilies);
            if (style === '') {
                unwrapNode(node);
                return;
            }

            node.setAttribute('style', style);
        });
    }

    function formatBlockTag(tag) {
        const normalized = String(tag || 'p').trim().toLowerCase();
        if (normalized === '') {
            return '<p>';
        }

        if (normalized.charAt(0) === '<') {
            return normalized;
        }

        return '<' + normalized.replace(/[^a-z0-9]/g, '') + '>';
    }

    const BLOCK_TAGS = new Set([
        'P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'HR',
    ]);

    const NESTED_BLOCK_SELECTOR = 'p, div, h1, h2, h3, h4, h5, h6, blockquote, pre, ul, ol';

    function blockText(el) {
        return (el.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function hasNestedBlocks(el) {
        return el.querySelector(NESTED_BLOCK_SELECTOR) !== null;
    }

    function normalizeEditorHtml(html) {
        const trimmed = String(html || '').trim();
        if (trimmed === '') {
            return '<p><br></p>';
        }

        const temp = document.createElement('div');
        temp.innerHTML = trimmed;

        Array.from(temp.childNodes).forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '') {
                const paragraph = document.createElement('p');
                paragraph.textContent = node.textContent;
                temp.replaceChild(paragraph, node);
            }
        });

        return consolidateParagraphs(temp.innerHTML);
    }

    function unwrapNestedParagraphs(root) {
        let changed = true;

        while (changed) {
            changed = false;
            root.querySelectorAll('p p').forEach(function (inner) {
                const outer = inner.parentElement;
                if (!outer || outer.tagName !== 'P') {
                    return;
                }

                while (inner.firstChild) {
                    outer.insertBefore(inner.firstChild, inner);
                }
                inner.remove();
                changed = true;
            });
        }
    }

    function flattenDivParagraphWrappers(root) {
        Array.from(root.children).forEach(function (child) {
            if (child.tagName !== 'DIV') {
                return;
            }

            const elementChildren = Array.from(child.children);
            if (elementChildren.length === 1 && elementChildren[0].tagName === 'P') {
                root.replaceChild(elementChildren[0], child);
            }
        });
    }

    function convertDivBlocksToParagraphs(root) {
        Array.from(root.children).forEach(function (child) {
            if (child.tagName !== 'DIV' || hasNestedBlocks(child)) {
                return;
            }

            const paragraph = document.createElement('p');
            paragraph.innerHTML = child.innerHTML;
            root.replaceChild(paragraph, child);
        });
    }

    function mergeDuplicateAdjacentBlocks(root) {
        let previous = null;

        Array.from(root.children).forEach(function (child) {
            if (!BLOCK_TAGS.has(child.tagName)) {
                previous = null;
                return;
            }

            const text = blockText(child);
            if (
                previous &&
                text !== '' &&
                text === blockText(previous) &&
                child.tagName === previous.tagName
            ) {
                child.remove();
                return;
            }

            previous = child;
        });
    }

    function collapseConsecutiveEmptyBlocks(root) {
        let lastWasEmpty = false;

        Array.from(root.children).forEach(function (child) {
            if (!BLOCK_TAGS.has(child.tagName)) {
                lastWasEmpty = false;
                return;
            }

            if (isEmptyBlock(child)) {
                if (lastWasEmpty) {
                    child.remove();
                } else {
                    lastWasEmpty = true;
                }
                return;
            }

            lastWasEmpty = false;
        });
    }

    function trimLeadingEmptyBlocks(root) {
        while (root.firstElementChild && isEmptyBlock(root.firstElementChild)) {
            root.firstElementChild.remove();
        }
    }

    function consolidateParagraphs(html) {
        const trimmed = String(html || '').trim();
        if (trimmed === '') {
            return '<p><br></p>';
        }

        const temp = document.createElement('div');
        temp.innerHTML = trimmed;

        unwrapNestedParagraphs(temp);
        flattenDivParagraphWrappers(temp);
        convertDivBlocksToParagraphs(temp);
        unwrapNestedParagraphs(temp);
        mergeDuplicateAdjacentBlocks(temp);
        collapseConsecutiveEmptyBlocks(temp);
        trimLeadingEmptyBlocks(temp);
        removeEmptyNodes(temp);

        const output = temp.innerHTML.trim();
        return output === '' ? '<p><br></p>' : output;
    }

    function unwrapNode(node) {
        const parent = node.parentNode;
        if (!parent) {
            return;
        }

        while (node.firstChild) {
            parent.insertBefore(node.firstChild, node);
        }
        parent.removeChild(node);
    }

    function replaceTagName(node, tagName) {
        const replacement = document.createElement(tagName);
        Array.from(node.attributes).forEach(function (attr) {
            replacement.setAttribute(attr.name, attr.value);
        });
        replacement.innerHTML = node.innerHTML;
        node.parentNode.replaceChild(replacement, node);
    }

    function isEmptyBlock(el) {
        const text = (el.textContent || '').replace(/\u00a0/g, ' ').trim();
        if (text !== '') {
            return false;
        }

        return el.querySelector('img, hr, iframe, video, audio') === null;
    }

    function removeEmptyNodes(root) {
        const selectors = 'p, div, span, h1, h2, h3, h4, h5, h6, blockquote, pre, li';
        let removed = true;

        while (removed) {
            removed = false;
            root.querySelectorAll(selectors).forEach(function (el) {
                if (isEmptyBlock(el)) {
                    el.remove();
                    removed = true;
                }
            });
        }
    }

    function cleanEditorHtml(html, allowedFontFamilies) {
        const families = allowedFontFamilies || DEFAULT_ALLOWED_FONT_FAMILIES;
        let value = String(html || '').trim();
        if (value === '') {
            return '<p><br></p>';
        }

        value = value.replace(/<!--[\s\S]*?-->/g, '');

        const temp = document.createElement('div');
        temp.innerHTML = value;

        convertFontTagsToSpans(temp);
        temp.querySelectorAll('b').forEach(function (node) {
            replaceTagName(node, 'strong');
        });
        temp.querySelectorAll('i').forEach(function (node) {
            replaceTagName(node, 'em');
        });

        temp.querySelectorAll('*').forEach(function (el) {
            Array.from(el.attributes).forEach(function (attr) {
                const name = attr.name.toLowerCase();
                if (name === 'class' || name === 'id' || name.indexOf('data-') === 0) {
                    el.removeAttribute(attr.name);
                    return;
                }

                if (name === 'style') {
                    const style = buildAllowedStyleString(attr.value, el.tagName, families);
                    if (style === '') {
                        el.removeAttribute('style');
                    } else {
                        el.setAttribute('style', style);
                    }
                }
            });
        });

        unwrapEmptySpans(temp, families);
        removeEmptyNodes(temp);

        return normalizeEditorHtml(temp.innerHTML);
    }

    function applyFormatBlock(tag) {
        document.execCommand('formatBlock', false, formatBlockTag(tag));
    }

    function getActiveBlock(editor, range) {
        let node = range.commonAncestorContainer;
        if (node.nodeType === Node.TEXT_NODE) {
            node = node.parentNode;
        }

        const blocks = ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'BLOCKQUOTE', 'PRE'];
        while (node && node !== editor) {
            if (node.nodeType === Node.ELEMENT_NODE && blocks.indexOf(node.tagName) !== -1) {
                return node;
            }
            node = node.parentNode;
        }

        return null;
    }

    function normalizeElementStyle(el, allowedFontFamilies) {
        const style = buildAllowedStyleString(el.getAttribute('style') || '', el.tagName, allowedFontFamilies);
        if (style === '') {
            if (el.tagName === 'SPAN') {
                unwrapNode(el);
            } else {
                el.removeAttribute('style');
            }
        } else {
            el.setAttribute('style', style);
        }
    }

    function stripStylePropertyFromRange(editor, range, property, allowedFontFamilies) {
        editor.querySelectorAll('[style]').forEach(function (el) {
            try {
                if (!range.intersectsNode(el)) {
                    return;
                }
            } catch (error) {
                return;
            }

            el.style[property] = '';
            normalizeElementStyle(el, allowedFontFamilies);
        });
    }

    function applyStyleProperty(editor, property, value, syncSourceFn, allowedFontFamilies) {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);
        if (!editor.contains(range.commonAncestorContainer)) {
            return;
        }

        if (!value) {
            if (!range.collapsed) {
                stripStylePropertyFromRange(editor, range, property, allowedFontFamilies);
            } else {
                const block = getActiveBlock(editor, range);
                if (block) {
                    block.style[property] = '';
                    normalizeElementStyle(block, allowedFontFamilies);
                }
            }
            syncSourceFn();
            return;
        }

        if (range.collapsed) {
            const block = getActiveBlock(editor, range);
            if (block) {
                block.style[property] = value;
                normalizeElementStyle(block, allowedFontFamilies);
                syncSourceFn();
                return;
            }
        }

        const span = document.createElement('span');
        span.style[property] = value;

        try {
            range.surroundContents(span);
        } catch (error) {
            const fragment = range.extractContents();
            span.appendChild(fragment);
            range.insertNode(span);
        }

        normalizeElementStyle(span, allowedFontFamilies);
        selection.removeAllRanges();
        const newRange = document.createRange();
        newRange.selectNodeContents(span);
        newRange.collapse(false);
        selection.addRange(newRange);
        syncSourceFn();
    }

    function countCharacters(html) {
        const temp = document.createElement('div');
        temp.innerHTML = String(html || '');
        return (temp.textContent || '').length;
    }

    function placeholderHtml(count) {
        const paragraphs = [];
        const total = Math.max(1, Math.min(20, parseInt(count, 10) || 1));

        for (let i = 0; i < total; i++) {
            paragraphs.push('<p>' + PLACEHOLDER_SENTENCE + '</p>');
        }

        return paragraphs.join('');
    }

    function initWysiwyg(root) {
        if (!root || root.dataset.wysiwygInitialized === '1') {
            return;
        }

        const sourceId = root.dataset.wysiwygSource || 'body';
        const editorId = root.dataset.wysiwygEditor || 'body-editor';
        const formatId = root.dataset.wysiwygFormat || 'body-format';
        const source = document.getElementById(sourceId);
        const editor = document.getElementById(editorId);
        const format = document.getElementById(formatId);
        const toolbar = root.querySelector('.wysiwyg-toolbar');
        const form = root.closest('form');
        const statusMode = root.querySelector('[data-wysiwyg-status-mode]');
        const statusCount = root.querySelector('[data-wysiwyg-status-count]');
        const findPanel = root.querySelector('[data-wysiwyg-find-panel]');
        const findInput = root.querySelector('[data-wysiwyg-find]');
        const replaceInput = root.querySelector('[data-wysiwyg-replace]');
        const linkPanel = root.querySelector('[data-wysiwyg-link-panel]');
        const imagePanel = root.querySelector('[data-wysiwyg-image-panel]');
        const linkUrlInput = root.querySelector('[data-wysiwyg-link-url]');
        const imageUrlInput = root.querySelector('[data-wysiwyg-image-url]');
        const imageFileInput = root.querySelector('[data-wysiwyg-image-file]');
        const uploadUrl = root.dataset.wysiwygUploadUrl || '';
        const csrfName = root.dataset.wysiwygCsrfName || '';
        const csrfHash = root.dataset.wysiwygCsrfHash || '';
        const placeholderCount = root.querySelector('[data-wysiwyg-placeholder-count]');
        const colorInput = root.querySelector('[data-wysiwyg-color]');
        const fontFamilySelect = root.querySelector('[data-wysiwyg-font-family]');
        const fontSizeSelect = root.querySelector('[data-wysiwyg-font-size]');
        const previewButtonId = root.dataset.wysiwygPreviewButton || '';
        const previewButton = previewButtonId ? document.getElementById(previewButtonId) : null;
        const previewPanelId = root.dataset.wysiwygPreviewPanel || 'content-preview';
        const preview = document.getElementById(previewPanelId);
        const previewTitleId = root.dataset.wysiwygPreviewTitle || 'preview-title';
        const previewTitle = document.getElementById(previewTitleId);
        const previewMetaId = root.dataset.wysiwygPreviewMeta || 'preview-meta';
        const previewMeta = previewMetaId ? document.getElementById(previewMetaId) : null;
        const previewBodyId = root.dataset.wysiwygPreviewBody || 'preview-body';
        const previewBody = document.getElementById(previewBodyId);
        const previewTitleField = root.dataset.wysiwygPreviewTitleField || 'title';
        const previewMetaField = root.dataset.wysiwygPreviewMetaField || '';
        const previewMetaSelector = root.dataset.wysiwygPreviewMetaSelector || '';
        const previewMetaPrefix = root.dataset.wysiwygPreviewMetaPrefix || '';
        const previewEmptyTitle = root.dataset.wysiwygPreviewEmptyTitle || 'Untitled preview';
        const previewEmptyBody = root.dataset.wysiwygPreviewEmptyBody || '<p>No content yet.</p>';

        let sourceMode = false;

        if (!source || !editor || !form || !toolbar) {
            return;
        }

        const allowedFontFamilies = loadAllowedFontFamilies(root);

        root.dataset.wysiwygInitialized = '1';
        const initialHtml = normalizeEditorHtml(source.value);
        editor.innerHTML = initialHtml;
        source.value = initialHtml;
        updateStatus();

        function currentHtml() {
            return sourceMode ? source.value : editor.innerHTML;
        }

        function syncSource() {
            if (!sourceMode) {
                source.value = consolidateParagraphs(editor.innerHTML);
            }
            updateStatus();
        }

        function syncEditor() {
            editor.innerHTML = normalizeEditorHtml(source.value);
            source.value = editor.innerHTML;
            updateStatus();
        }

        function applyHtml(html) {
            const cleaned = cleanEditorHtml(html, allowedFontFamilies);
            if (sourceMode) {
                source.value = cleaned;
            } else {
                editor.innerHTML = cleaned;
                source.value = cleaned;
            }
            updateStatus();
        }

        function updateStatus() {
            const html = currentHtml();
            const chars = countCharacters(html);

            if (statusMode) {
                statusMode.textContent = sourceMode ? 'HTML source' : 'Visual';
            }

            if (statusCount) {
                statusCount.textContent = chars + (chars === 1 ? ' character' : ' characters');
            }
        }

        function focusEditor() {
            if (sourceMode) {
                source.focus();
            } else {
                editor.focus();
            }
        }

        function normalizeCreatedLinks() {
            editor.querySelectorAll('a[href]').forEach(function (link) {
                if (/^https?:\/\//i.test(link.getAttribute('href') || '')) {
                    link.setAttribute('target', '_blank');
                    link.setAttribute('rel', 'noopener noreferrer');
                }
            });
        }

        function previewFieldValue(fieldId, selector) {
            if (selector) {
                const node = form.querySelector(selector);
                return node ? node.textContent.trim() : '';
            }
            if (!fieldId) {
                return '';
            }
            const node = document.getElementById(fieldId);
            return node ? String(node.value || '').trim() : '';
        }

        function showPreview() {
            if (!preview || !previewTitle || !previewBody) {
                return;
            }

            if (!sourceMode) {
                syncSource();
            }

            const title = previewFieldValue(previewTitleField, '') || previewEmptyTitle;
            const body = source.value.trim();

            previewTitle.textContent = title;

            if (previewMeta) {
                const metaValue = previewFieldValue(previewMetaField, previewMetaSelector);
                const metaText = metaValue === '' ? '' : previewMetaPrefix + metaValue;
                previewMeta.textContent = metaText;
                previewMeta.style.display = metaText === '' ? 'none' : 'block';
            }

            previewBody.innerHTML = body || previewEmptyBody;
            preview.classList.add('is-visible');
            preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function showPanelMessage(panel, text, isError) {
            if (!panel) {
                return;
            }

            const msg = panel.querySelector('[data-wysiwyg-panel-message]');
            if (!msg) {
                return;
            }

            msg.textContent = text;
            msg.hidden = text === '';
            msg.classList.toggle('is-error', Boolean(isError));
        }

        function closeAuxPanels() {
            [findPanel, linkPanel, imagePanel].forEach(function (panel) {
                if (!panel) {
                    return;
                }

                panel.classList.remove('is-open');
                panel.hidden = true;
                showPanelMessage(panel, '', false);
            });
        }

        function toggleAuxPanel(panel, focusInput) {
            if (!panel) {
                return;
            }

            const wasOpen = panel.classList.contains('is-open');
            closeAuxPanels();
            if (!wasOpen) {
                panel.classList.add('is-open');
                panel.hidden = false;
                if (focusInput) {
                    focusInput.focus();
                    if (typeof focusInput.select === 'function') {
                        focusInput.select();
                    }
                }
            }
        }

        function toggleFindPanel() {
            toggleAuxPanel(findPanel, findInput);
        }

        function insertLinkAtSelection(url) {
            focusEditor();
            document.execCommand('createLink', false, url);
            normalizeCreatedLinks();
            syncSource();
        }

        function insertImageAtCursor(url) {
            focusEditor();
            document.execCommand('insertImage', false, url);
            syncSource();
        }

        function uploadAndInsertImage() {
            const file = imageFileInput && imageFileInput.files ? imageFileInput.files[0] : null;
            if (!file) {
                showPanelMessage(imagePanel, 'Choose an image file.', true);
                return;
            }

            if (!uploadUrl) {
                showPanelMessage(imagePanel, 'Image upload is not configured.', true);
                return;
            }

            const uploadButton = imagePanel ? imagePanel.querySelector('[data-action="upload-image"]') : null;
            if (uploadButton) {
                uploadButton.disabled = true;
            }

            showPanelMessage(imagePanel, 'Uploading…', false);

            const formData = new FormData();
            formData.append('image', file);
            if (csrfName !== '' && csrfHash !== '') {
                formData.append(csrfName, csrfHash);
            }

            fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { response: response, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.response.ok || !result.data.success) {
                        showPanelMessage(imagePanel, result.data.error || 'Upload failed.', true);
                        return;
                    }

                    insertImageAtCursor(result.data.url);
                    closeAuxPanels();
                    if (imageFileInput) {
                        imageFileInput.value = '';
                    }
                    if (imageUrlInput) {
                        imageUrlInput.value = '';
                    }
                })
                .catch(function () {
                    showPanelMessage(imagePanel, 'Upload failed. Try again.', true);
                })
                .finally(function () {
                    if (uploadButton) {
                        uploadButton.disabled = false;
                    }
                });
        }

        function runFindReplace() {
            const findText = findInput ? findInput.value : '';
            if (findText === '') {
                return;
            }

            const replaceText = replaceInput ? replaceInput.value : '';

            if (!sourceMode) {
                syncSource();
            }

            source.value = source.value.split(findText).join(replaceText);

            if (sourceMode) {
                updateStatus();
            } else {
                syncEditor();
            }
        }

        function insertPlaceholder() {
            const count = placeholderCount ? placeholderCount.value : '1';
            const html = placeholderHtml(count);

            if (sourceMode) {
                source.value = source.value.trim() === '' ? html : source.value + html;
                updateStatus();
                return;
            }

            focusEditor();
            if (editor.innerHTML.trim() === '' || editor.innerHTML.trim() === '<p><br></p>') {
                editor.innerHTML = html;
            } else {
                document.execCommand('insertHTML', false, html);
            }
            syncSource();
        }

        if (format) {
            format.addEventListener('change', function () {
                if (sourceMode) {
                    return;
                }
                focusEditor();
                applyFormatBlock(format.value);
                format.selectedIndex = 0;
                syncSource();
            });
        }

        if (colorInput) {
            colorInput.addEventListener('input', function () {
                if (sourceMode) {
                    return;
                }
                focusEditor();
                document.execCommand('foreColor', false, colorInput.value);
                syncSource();
            });
        }

        if (fontFamilySelect) {
            fontFamilySelect.addEventListener('change', function () {
                if (sourceMode) {
                    return;
                }
                focusEditor();
                applyStyleProperty(editor, 'fontFamily', fontFamilySelect.value, syncSource, allowedFontFamilies);
                fontFamilySelect.selectedIndex = 0;
            });
        }

        if (fontSizeSelect) {
            fontSizeSelect.addEventListener('change', function () {
                if (sourceMode) {
                    return;
                }
                focusEditor();
                applyStyleProperty(editor, 'fontSize', fontSizeSelect.value, syncSource, allowedFontFamilies);
                fontSizeSelect.selectedIndex = 0;
            });
        }

        function bindPanelEnter(input, actionName) {
            if (!input) {
                return;
            }

            input.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                const trigger = root.querySelector('[data-action="' + actionName + '"]');
                if (trigger) {
                    trigger.click();
                }
            });
        }

        bindPanelEnter(linkUrlInput, 'insert-link');
        bindPanelEnter(imageUrlInput, 'insert-image-url');

        root.addEventListener('click', function (event) {
            const button = event.target.closest('button');
            if (!button || !root.contains(button)) {
                return;
            }

            if (sourceMode && button.dataset.action !== 'source' && button.dataset.action !== 'toggle-find' && button.dataset.action !== 'find-replace' && button.dataset.action !== 'close-insert-panels') {
                return;
            }

            const command = button.dataset.command;
            const block = button.dataset.block;
            const action = button.dataset.action;

            if (command) {
                if (sourceMode) {
                    return;
                }
                focusEditor();
                document.execCommand(command, false, null);
                syncSource();
                return;
            }

            if (block) {
                if (sourceMode) {
                    return;
                }
                focusEditor();
                applyFormatBlock(block);
                syncSource();
                return;
            }

            if (action === 'toggle-link') {
                if (sourceMode) {
                    return;
                }
                toggleAuxPanel(linkPanel, linkUrlInput);
                return;
            }

            if (action === 'toggle-image') {
                if (sourceMode) {
                    return;
                }
                toggleAuxPanel(imagePanel, imageUrlInput);
                return;
            }

            if (action === 'insert-link') {
                if (sourceMode) {
                    return;
                }
                const linkUrl = linkUrlInput ? linkUrlInput.value.trim() : '';
                if (linkUrl === '') {
                    showPanelMessage(linkPanel, 'Enter a URL.', true);
                    return;
                }
                insertLinkAtSelection(linkUrl);
                closeAuxPanels();
                if (linkUrlInput) {
                    linkUrlInput.value = '';
                }
                return;
            }

            if (action === 'insert-image-url') {
                if (sourceMode) {
                    return;
                }
                const imageUrl = imageUrlInput ? imageUrlInput.value.trim() : '';
                if (imageUrl === '') {
                    showPanelMessage(imagePanel, 'Enter an image URL.', true);
                    return;
                }
                insertImageAtCursor(imageUrl);
                closeAuxPanels();
                if (imageUrlInput) {
                    imageUrlInput.value = '';
                }
                return;
            }

            if (action === 'upload-image') {
                if (sourceMode) {
                    return;
                }
                uploadAndInsertImage();
                return;
            }

            if (action === 'close-insert-panels') {
                closeAuxPanels();
                return;
            }

            if (action === 'clean') {
                applyHtml(cleanEditorHtml(currentHtml()));
                return;
            }

            if (action === 'toggle-find') {
                toggleFindPanel();
                return;
            }

            if (action === 'find-replace') {
                runFindReplace();
                return;
            }

            if (action === 'placeholder') {
                insertPlaceholder();
                return;
            }

            if (action === 'unlink') {
                if (sourceMode) {
                    return;
                }
                focusEditor();
                document.execCommand('unlink', false, null);
                syncSource();
                return;
            }

            if (action === 'source') {
                const sourceButton = toolbar.querySelector('[data-action="source"]');
                if (sourceMode) {
                    sourceMode = false;
                    syncEditor();
                    source.classList.remove('is-visible');
                    editor.classList.remove('is-hidden');
                    root.classList.remove('is-source-mode');
                    const sourceLabel = sourceButton ? sourceButton.querySelector('.wysiwyg-source-label') : null;
                    if (sourceLabel) {
                        sourceLabel.textContent = 'HTML';
                    }
                    focusEditor();
                } else {
                    syncSource();
                    sourceMode = true;
                    source.classList.add('is-visible');
                    editor.classList.add('is-hidden');
                    root.classList.add('is-source-mode');
                    const sourceLabel = sourceButton ? sourceButton.querySelector('.wysiwyg-source-label') : null;
                    if (sourceLabel) {
                        sourceLabel.textContent = 'Visual';
                    }
                    source.focus();
                }
                updateStatus();
            }
        });

        editor.addEventListener('input', syncSource);

        source.addEventListener('input', function () {
            updateStatus();
        });

        if (previewButton) {
            previewButton.addEventListener('click', function () {
                if (!sourceMode) {
                    syncSource();
                }
                showPreview();
            });
        }

        form.addEventListener('submit', function () {
            if (sourceMode) {
                syncEditor();
            } else {
                syncSource();
            }
            source.value = cleanEditorHtml(source.value, allowedFontFamilies);
        });
    }

    function initAll() {
        document.querySelectorAll('[data-wysiwyg]').forEach(initWysiwyg);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
