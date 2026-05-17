<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Allowed WYSIWYG font stacks (editor UI, client cleanup, server sanitization).
 */
final class WysiwygFonts
{
    /**
     * @return list<array{label: string, stack: string}>
     */
    public static function families(): array
    {
        return [
            ['label' => 'Arial', 'stack' => 'Arial, Helvetica, sans-serif'],
            ['label' => 'Helvetica Neue', 'stack' => 'Helvetica Neue, Helvetica, Arial, sans-serif'],
            ['label' => 'Segoe UI', 'stack' => 'Segoe UI, system-ui, sans-serif'],
            ['label' => 'Tahoma', 'stack' => 'Tahoma, Geneva, sans-serif'],
            ['label' => 'Verdana', 'stack' => 'Verdana, Geneva, sans-serif'],
            ['label' => 'Trebuchet MS', 'stack' => 'Trebuchet MS, Helvetica, sans-serif'],
            ['label' => 'Calibri', 'stack' => 'Calibri, Candara, Segoe, Segoe UI, Optima, Arial, sans-serif'],
            ['label' => 'Lucida Sans', 'stack' => 'Lucida Sans Unicode, Lucida Grande, sans-serif'],
            ['label' => 'Century Gothic', 'stack' => 'Century Gothic, CenturyGothic, AppleGothic, sans-serif'],
            ['label' => 'Franklin Gothic', 'stack' => 'Franklin Gothic Medium, Arial Narrow, Arial, sans-serif'],
            ['label' => 'Arial Black', 'stack' => 'Arial Black, Gadget, sans-serif'],
            ['label' => 'Impact', 'stack' => 'Impact, Haettenschweiler, Franklin Gothic Bold, sans-serif'],
            ['label' => 'Georgia', 'stack' => 'Georgia, serif'],
            ['label' => 'Times New Roman', 'stack' => 'Times New Roman, Times, serif'],
            ['label' => 'Garamond', 'stack' => 'Garamond, Baskerville, Times New Roman, serif'],
            ['label' => 'Palatino', 'stack' => 'Palatino Linotype, Palatino, serif'],
            ['label' => 'Book Antiqua', 'stack' => 'Book Antiqua, Palatino, serif'],
            ['label' => 'Cambria', 'stack' => 'Cambria, Georgia, serif'],
            ['label' => 'Didot', 'stack' => 'Didot, Baskerville, Segoe UI, serif'],
            ['label' => 'Courier New', 'stack' => 'Courier New, Courier, monospace'],
            ['label' => 'Consolas', 'stack' => 'Consolas, Monaco, monospace'],
            ['label' => 'Lucida Console', 'stack' => 'Lucida Console, Monaco, monospace'],
            ['label' => 'Comic Sans MS', 'stack' => 'Comic Sans MS, Comic Sans, cursive'],
            ['label' => 'Brush Script MT', 'stack' => 'Brush Script MT, cursive'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function normalizedStacks(): array
    {
        $stacks = [];
        foreach (self::families() as $family) {
            $stacks[] = self::normalizeStack((string) $family['stack']);
        }

        return array_values(array_unique($stacks));
    }

    public static function normalizeStack(string $stack): string
    {
        return strtolower(preg_replace('/\s+/', ' ', str_replace(['"', "'"], '', trim($stack))) ?? '');
    }

    public static function isAllowedStack(string $stack): bool
    {
        return in_array(self::normalizeStack($stack), self::normalizedStacks(), true);
    }
}
