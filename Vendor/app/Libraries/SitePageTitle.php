<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Builds consistent "{site name} · {page/forum/topic/...}" labels for titles, headers, and breadcrumbs.
 */
final class SitePageTitle
{
    private static ?string $siteNameCache = null;

    public static function siteName(): string
    {
        if (self::$siteNameCache !== null) {
            return self::$siteNameCache;
        }

        try {
            $web = (new WebSettings())->homeSettings();

            return self::$siteNameCache = trim((string) ($web['web_name'] ?? '')) ?: WebSettings::defaultWebName();
        } catch (\Throwable) {
            return self::$siteNameCache = WebSettings::defaultWebName();
        }
    }

    /**
     * Browser/document title: Site Name · Page · Forum · Topic
     *
     * @param string ...$contextParts Page, category, forum, topic, etc. (broad → specific)
     */
    public static function format(string ...$contextParts): string
    {
        $trail = self::trail(...$contextParts);

        return $trail !== '' ? self::siteName() . ' · ' . $trail : self::siteName();
    }

    /**
     * Normalizes a controller-provided title for the HTML &lt;title&gt; element.
     * Accepts either a full "Site Name · …" string or a plain context label.
     */
    public static function documentTitle(?string $title = null): string
    {
        $title = trim((string) $title);
        $siteName = self::siteName();

        if ($title === '' || $title === $siteName) {
            return $siteName;
        }

        $prefix = $siteName . ' · ';

        return str_starts_with($title, $prefix) ? $title : self::format($title);
    }

    /**
     * Context trail without site name (for hero subtitle / breadcrumbs body).
     *
     * @param string ...$contextParts
     */
    public static function trail(string ...$contextParts): string
    {
        $parts = [];
        foreach ($contextParts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @param list<array{label: string, url?: string|null}> $items
     *
     * @return list<array{label: string, url?: string|null}>
     */
    public static function breadcrumbs(array $items): array
    {
        $crumbs = [
            ['label' => self::siteName(), 'url' => site_url('/')],
        ];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $crumb = ['label' => $label];
            $url = trim((string) ($item['url'] ?? ''));
            if ($url !== '') {
                $crumb['url'] = $url;
            }

            $crumbs[] = $crumb;
        }

        return $crumbs;
    }
}
