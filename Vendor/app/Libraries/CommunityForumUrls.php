<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Community forum (category) public URLs — uses {@see SecurityCangService} {@code c_id} when Security Manager is enabled.
 */
final class CommunityForumUrls
{
    private static ?bool $securityManagerCache = null;

    public static function securityManagerUsesCangIds(): bool
    {
        if (self::$securityManagerCache !== null) {
            return self::$securityManagerCache;
        }

        try {
            return self::$securityManagerCache = ModuleSettings::isEnabledCached(ModuleSettings::SECURITY_MANAGER);
        } catch (\Throwable) {
            return self::$securityManagerCache = true;
        }
    }

    /**
     * @param array<string, mixed> $category Category row with `id` and optional `c_id`
     */
    public static function forumUrl(array $category): string
    {
        $categoryId = (int) ($category['id'] ?? 0);
        $cId = trim((string) ($category['c_id'] ?? ''));

        if (self::securityManagerUsesCangIds() && $cId !== '') {
            return site_url('Content/Community/Forum/' . rawurlencode($cId));
        }

        return site_url('Content/Community/Forum/' . $categoryId);
    }

    /**
     * Query value for {@code ?forum=} on new-topic links.
     *
     * @param array<string, mixed> $category
     */
    public static function forumRefForUrl(array $category): string
    {
        $categoryId = (int) ($category['id'] ?? 0);
        $cId = trim((string) ($category['c_id'] ?? ''));

        if (self::securityManagerUsesCangIds() && $cId !== '') {
            return $cId;
        }

        return (string) $categoryId;
    }

    /**
     * Resolve a forum URL segment to a category row (numeric `id` or `c_id` when Security Manager is on).
     *
     * @return array<string, mixed>|null
     */
    public static function resolveForumRef(string $ref): ?array
    {
        $ref = trim(rawurldecode($ref));
        if ($ref === '') {
            return null;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('community_categories')) {
            return null;
        }

        $categories = new CommunityCategoryService();
        $securityOn = self::securityManagerUsesCangIds();

        if ($securityOn) {
            $byCid = $db->table('community_categories')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $categories->normalizeRow($byCid);
            }
        }

        if (ctype_digit($ref)) {
            $byId = $categories->findById((int) $ref);
            if ($byId !== null) {
                return $byId;
            }
        }

        if (! $securityOn) {
            $byCid = $db->table('community_categories')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $categories->normalizeRow($byCid);
            }
        }

        return $categories->findBySlugOrName($ref);
    }

    /**
     * Redirect to canonical forum URL when Security Manager expects {@code c_id} in the path.
     *
     * @param array<string, mixed> $category
     */
    public static function canonicalForumRedirectIfNeeded(array $category, string $requestedRef): ?string
    {
        if (! self::securityManagerUsesCangIds()) {
            return null;
        }

        $cId = trim((string) ($category['c_id'] ?? ''));
        if ($cId === '') {
            return null;
        }

        $requestedRef = trim(rawurldecode($requestedRef));
        if ($requestedRef === $cId) {
            return null;
        }

        return self::forumUrl($category);
    }
}
