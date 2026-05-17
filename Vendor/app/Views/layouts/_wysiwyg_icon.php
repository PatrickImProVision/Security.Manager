<?php
/**
 * Inline SVG icons for the shared WYSIWYG toolbar.
 *
 * @var string $icon Icon key
 */
$icon = trim((string) ($icon ?? ''));
$svgOpen = '<svg class="wysiwyg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

$icons = [
    'format' => $svgOpen . '<path d="M4 6h16M4 12h10M4 18h14"/></svg>',
    'quote' => $svgOpen . '<path d="M7 7h4v10H7z"/><path d="M13 7h4v10h-4z"/></svg>',
    'bold' => $svgOpen . '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zm0 7h7a3.5 3.5 0 0 1 0 7H7z"/></svg>',
    'italic' => $svgOpen . '<path d="M11 5h8M6 19h8M14 5 10 19"/></svg>',
    'underline' => $svgOpen . '<path d="M6 5v6a6 6 0 0 0 12 0V5M4 19h16"/></svg>',
    'strikethrough' => $svgOpen . '<path d="M4 12h16M7 5.5h10M7 18.5h10"/></svg>',
    'palette' => $svgOpen . '<circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
    'type' => $svgOpen . '<path d="M4 7V4h16v3M9 20h6M12 4v16"/></svg>',
    'clear' => $svgOpen . '<path d="M3 6h18M8 6l1 14h6l1-14M10 10v6M14 10v6"/></svg>',
    'bullets' => $svgOpen . '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.2" fill="currentColor" stroke="none"/></svg>',
    'numbers' => $svgOpen . '<path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 6h1v4M4 10h2M6 18H4l2-3H4"/></svg>',
    'outdent' => $svgOpen . '<path d="M3 8h14M3 12h10M3 16h14"/><path d="M18 8l3 4-3 4"/></svg>',
    'indent' => $svgOpen . '<path d="M3 8h14M3 12h10M3 16h14"/><path d="M21 8l-3 4 3 4"/></svg>',
    'align-left' => $svgOpen . '<path d="M3 6h18M3 12h12M3 18h16"/></svg>',
    'align-center' => $svgOpen . '<path d="M3 6h18M6 12h12M4 18h16"/></svg>',
    'align-right' => $svgOpen . '<path d="M3 6h18M9 12h12M7 18h16"/></svg>',
    'align-justify' => $svgOpen . '<path d="M3 6h18M3 12h18M3 18h18"/></svg>',
    'link' => $svgOpen . '<path d="M10 13a5 5 0 0 1 7-7l1 1a5 5 0 0 1-7 7z"/><path d="M14 11a5 5 0 0 1-7 7l-1-1a5 5 0 0 1 7-7z"/></svg>',
    'unlink' => $svgOpen . '<path d="M10 13a5 5 0 0 1 7-7l1 1"/><path d="M14 11a5 5 0 0 1-7 7l-1-1"/><path d="M4 4l16 16"/></svg>',
    'image' => $svgOpen . '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="M21 17l-5-5-4 4-2-2-5 5"/></svg>',
    'line' => $svgOpen . '<path d="M3 12h18"/></svg>',
    'clean' => $svgOpen . '<path d="M12 3l1.5 3.5L17 8l-3.5 1.5L12 13l-1.5-3.5L7 8l3.5-1.5z"/><path d="M5 19h14"/></svg>',
    'find' => $svgOpen . '<circle cx="11" cy="11" r="6"/><path d="M20 20l-3-3"/></svg>',
    'draft' => $svgOpen . '<path d="M4 6h16M4 10h16M4 14h10M4 18h8"/></svg>',
    'undo' => $svgOpen . '<path d="M9 7H5v4"/><path d="M5 11a7 7 0 1 1 2 5"/></svg>',
    'redo' => $svgOpen . '<path d="M15 7h4v4"/><path d="M19 11a7 7 0 1 1-2 5"/></svg>',
    'code' => $svgOpen . '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4"/></svg>',
    'replace' => $svgOpen . '<path d="M14 7h6v6M10 17H4v-6"/><path d="M20 7l-6 6M4 17l6-6"/></svg>',
];

echo $icons[$icon] ?? '';
