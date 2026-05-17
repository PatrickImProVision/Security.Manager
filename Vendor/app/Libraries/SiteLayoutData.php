<?php

declare(strict_types=1);

namespace App\Libraries;

use Throwable;

/**
 * Cached layout data (web name, module flags, public nav) to avoid repeated remote DB queries per page.
 */
final class SiteLayoutData
{
    private const CACHE_KEY = 'site_layout_v1';

    private const CACHE_TTL = 120;

    /** @var array<string, mixed>|null */
    private static ?array $requestCache = null;

    /**
     * @return array{
     *     web_name: string,
     *     public_content_enabled: bool,
     *     public_content_nav_items: list<array<string, mixed>>
     * }
     */
    public static function get(): array
    {
        if (self::$requestCache !== null) {
            return self::$requestCache;
        }

        $defaults = [
            'web_name'                   => WebSettings::defaultWebName(),
            'public_content_enabled'     => true,
            'public_content_nav_items'   => [],
        ];

        if (! InstallationState::isInstalled()) {
            return self::$requestCache = $defaults;
        }

        $segments = service('request')->getUri()->getSegments();
        if (($segments[0] ?? '') === 'install') {
            $defaults['public_content_enabled'] = false;

            return self::$requestCache = $defaults;
        }

        try {
            $cached = cache()->get(self::CACHE_KEY);
            if (is_array($cached)) {
                return self::$requestCache = array_merge($defaults, $cached);
            }
        } catch (Throwable) {
            // Fall through to live load.
        }

        $data = self::loadFromDatabase();
        self::$requestCache = $data;

        try {
            cache()->save(self::CACHE_KEY, $data, self::CACHE_TTL);
        } catch (Throwable) {
            // Non-fatal.
        }

        return $data;
    }

    public static function clearCache(): void
    {
        self::$requestCache = null;

        try {
            cache()->delete(self::CACHE_KEY);
        } catch (Throwable) {
            // Non-fatal.
        }
    }

    /**
     * @return array{
     *     web_name: string,
     *     public_content_enabled: bool,
     *     public_content_nav_items: list<array<string, mixed>>
     * }
     */
    private static function loadFromDatabase(): array
    {
        $webSettings = (new WebSettings())->homeSettings();
        $publicEnabled = ModuleSettings::isEnabledCached(ModuleSettings::CONTENT_PUBLIC);
        $navItems = [];

        if ($publicEnabled) {
            $navItems = self::loadPublicNavItems();
        }

        return [
            'web_name'                 => trim((string) ($webSettings['web_name'] ?? '')) ?: WebSettings::defaultWebName(),
            'public_content_enabled'   => $publicEnabled,
            'public_content_nav_items' => $navItems,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadPublicNavItems(): array
    {
        try {
            $db = AppDatabase::connection();
            if (! $db->tableExists('public_contents')) {
                return [];
            }

            $fields = $db->getFieldNames('public_contents');
            if (
                ! in_array('show_in_nav', $fields, true)
                || ! in_array('nav_label', $fields, true)
                || ! in_array('nav_order', $fields, true)
            ) {
                return [];
            }

            return $db->table('public_contents')
                ->select('id, c_id, title, slug, nav_label, nav_order')
                ->where('status', 'published')
                ->where('show_in_nav', true)
                ->groupStart()
                ->where('published_at', null)
                ->orWhere('published_at <=', date('Y-m-d H:i:s'))
                ->groupEnd()
                ->orderBy('nav_order', 'ASC')
                ->orderBy('title', 'ASC')
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return [];
        }
    }
}
