<?php

declare(strict_types=1);

namespace App\Libraries;

use DOMDocument;
use DOMElement;

/**
 * Shared rich HTML storage, sanitization, and display for WYSIWYG content.
 */
final class RichHtml
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><pre><code><a><img><hr><div><span>';

    /** @var list<string> */
    private const FONT_SIZES = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'];

    /**
     * Sanitize submitted editor HTML before saving to the database.
     */
    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if ($html === strip_tags($html)) {
            return self::plainTextToHtml($html);
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="rich-html-fragment">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $wrapper = $doc->getElementById('rich-html-fragment');
        if ($wrapper === null) {
            return '';
        }

        foreach ($doc->getElementsByTagName('*') as $node) {
            if (! $node instanceof DOMElement || $node->getAttribute('id') === 'rich-html-fragment') {
                continue;
            }

            self::sanitizeElementAttributes($node);
        }

        self::consolidateFragment($wrapper);

        $output = '';
        foreach ($wrapper->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Prepare stored HTML for safe output in views.
     */
    public static function render(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        if ($body === strip_tags($body)) {
            return self::plainTextToHtml($body);
        }

        return self::sanitize($body);
    }

    public static function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($escaped !== '') {
                $html[] = '<p>' . nl2br($escaped, false) . '</p>';
            }
        }

        return implode("\n", $html);
    }

    private static function sanitizeElementAttributes(DOMElement $node): void
    {
        $tag = strtolower($node->nodeName);
        $allowed = match ($tag) {
            'a'     => ['href', 'title', 'target', 'rel'],
            'img'   => ['src', 'alt', 'title'],
            'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'span' => ['style'],
            default => [],
        };

        $remove = [];
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);
            if (! in_array($name, $allowed, true)) {
                $remove[] = $attribute->nodeName;

                continue;
            }

            $value = trim($attribute->nodeValue ?? '');
            if (($name === 'href' || $name === 'src') && ! self::isSafeContentUrl($value)) {
                $remove[] = $attribute->nodeName;

                continue;
            }

            if ($name === 'target' && ! in_array($value, ['_blank', '_self'], true)) {
                $remove[] = $attribute->nodeName;

                continue;
            }

            if ($name === 'style') {
                $style = self::sanitizeInlineStyle($value);
                if ($style === '') {
                    $remove[] = $attribute->nodeName;
                } else {
                    $node->setAttribute('style', $style);
                }
            }
        }

        foreach ($remove as $attributeName) {
            $node->removeAttribute($attributeName);
        }

        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function consolidateFragment(DOMElement $wrapper): void
    {
        self::unwrapNestedParagraphs($wrapper);
        self::flattenDivParagraphWrappers($wrapper);
        self::convertDivBlocksToParagraphs($wrapper);
        self::unwrapNestedParagraphs($wrapper);
        self::mergeDuplicateAdjacentBlocks($wrapper);
        self::collapseConsecutiveEmptyBlocks($wrapper);
        self::trimLeadingEmptyBlocks($wrapper);
        self::removeEmptyBlockChildren($wrapper);
    }

    private static function unwrapNestedParagraphs(DOMElement $root): void
    {
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($root->getElementsByTagName('p') as $inner) {
                if (! $inner instanceof DOMElement) {
                    continue;
                }

                $outer = $inner->parentNode;
                if (! $outer instanceof DOMElement || strtolower($outer->nodeName) !== 'p') {
                    continue;
                }

                while ($inner->firstChild !== null) {
                    $outer->insertBefore($inner->firstChild, $inner);
                }
                $outer->removeChild($inner);
                $changed = true;
            }
        }
    }

    private static function flattenDivParagraphWrappers(DOMElement $root): void
    {
        $children = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        foreach ($children as $child) {
            if (strtolower($child->nodeName) !== 'div') {
                continue;
            }

            $elementChildren = [];
            foreach ($child->childNodes as $node) {
                if ($node instanceof DOMElement) {
                    $elementChildren[] = $node;
                }
            }

            if (count($elementChildren) !== 1 || strtolower($elementChildren[0]->nodeName) !== 'p') {
                continue;
            }

            $root->replaceChild($elementChildren[0], $child);
        }
    }

    private static function convertDivBlocksToParagraphs(DOMElement $root): void
    {
        $children = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        foreach ($children as $child) {
            if (strtolower($child->nodeName) !== 'div' || self::hasNestedBlocks($child)) {
                continue;
            }

            $paragraph = $root->ownerDocument?->createElement('p');
            if ($paragraph === null) {
                continue;
            }

            while ($child->firstChild !== null) {
                $paragraph->appendChild($child->firstChild);
            }

            $root->replaceChild($paragraph, $child);
        }
    }

    private static function mergeDuplicateAdjacentBlocks(DOMElement $root): void
    {
        $previous = null;

        foreach (iterator_to_array($root->childNodes) as $child) {
            if (! $child instanceof DOMElement || ! self::isBlockTag($child)) {
                $previous = null;

                continue;
            }

            $text = self::blockText($child);
            if (
                $previous instanceof DOMElement
                && $text !== ''
                && $text === self::blockText($previous)
                && strtolower($child->nodeName) === strtolower($previous->nodeName)
            ) {
                $root->removeChild($child);

                continue;
            }

            $previous = $child;
        }
    }

    private static function collapseConsecutiveEmptyBlocks(DOMElement $root): void
    {
        $lastWasEmpty = false;

        foreach (iterator_to_array($root->childNodes) as $child) {
            if (! $child instanceof DOMElement || ! self::isBlockTag($child)) {
                $lastWasEmpty = false;

                continue;
            }

            if (self::isEmptyBlock($child)) {
                if ($lastWasEmpty) {
                    $root->removeChild($child);
                } else {
                    $lastWasEmpty = true;
                }

                continue;
            }

            $lastWasEmpty = false;
        }
    }

    private static function trimLeadingEmptyBlocks(DOMElement $root): void
    {
        while (($first = $root->firstChild) instanceof DOMElement && self::isEmptyBlock($first)) {
            $root->removeChild($first);
        }
    }

    private static function removeEmptyBlockChildren(DOMElement $root): void
    {
        $removed = true;

        while ($removed) {
            $removed = false;

            foreach (iterator_to_array($root->getElementsByTagName('*')) as $node) {
                if (! $node instanceof DOMElement || ! self::isBlockTag($node)) {
                    continue;
                }

                if (self::isEmptyBlock($node)) {
                    $node->parentNode?->removeChild($node);
                    $removed = true;
                }
            }
        }
    }

    private static function hasNestedBlocks(DOMElement $element): bool
    {
        foreach (['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'ul', 'ol'] as $tag) {
            if ($element->getElementsByTagName($tag)->length > 0) {
                return true;
            }
        }

        return false;
    }

    private static function isBlockTag(DOMElement $element): bool
    {
        return in_array(strtolower($element->nodeName), ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'ul', 'ol', 'hr'], true);
    }

    private static function blockText(DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', $element->textContent ?? '')) ?? '';

        return trim($text);
    }

    private static function isEmptyBlock(DOMElement $element): bool
    {
        if (self::blockText($element) !== '') {
            return false;
        }

        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['img', 'hr', 'iframe', 'video', 'audio'], true)) {
                return false;
            }
        }

        return true;
    }

    private static function sanitizeInlineStyle(string $style): string
    {
        $parts = [];

        if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center|justify)\s*(?:;|$)/i', $style, $match) === 1) {
            $parts[] = 'text-align: ' . strtolower($match[1]);
        }

        if (preg_match('/(?:^|;)\s*color\s*:\s*(#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))\s*(?:;|$)/i', $style, $match) === 1) {
            $parts[] = 'color: ' . strtolower($match[1]);
        }

        if (preg_match('/(?:^|;)\s*font-family\s*:\s*([^;]+)\s*(?:;|$)/i', $style, $match) === 1) {
            $family = self::sanitizeFontFamily($match[1]);
            if ($family !== '') {
                $parts[] = 'font-family: ' . $family;
            }
        }

        if (preg_match('/(?:^|;)\s*font-size\s*:\s*(\d{1,2}px)\s*(?:;|$)/i', $style, $match) === 1) {
            $size = strtolower($match[1]);
            if (in_array($size, self::FONT_SIZES, true)) {
                $parts[] = 'font-size: ' . $size;
            }
        }

        if ($parts === []) {
            return '';
        }

        return implode('; ', $parts) . ';';
    }

    private static function sanitizeFontFamily(string $value): string
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', str_replace(['"', "'"], '', trim($value))) ?? '');

        if (WysiwygFonts::isAllowedStack($normalized)) {
            return WysiwygFonts::normalizeStack($value);
        }

        return '';
    }

    private static function isSafeContentUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }

        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null) {
            return ! str_contains($url, ':');
        }

        return in_array(strtolower((string) $scheme), ['http', 'https', 'mailto'], true);
    }
}
