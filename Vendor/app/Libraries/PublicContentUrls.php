<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Public content (blog/page) URLs — uses {@see SecurityCangService} {@code c_id} when Security Manager is enabled.
 */
final class PublicContentUrls
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
     * @param array<string, mixed> $content Row with `id`, optional `c_id`, and optional `slug`
     */
    public static function postUrl(array $content): string
    {
        $postId = (int) ($content['id'] ?? 0);
        $cId = trim((string) ($content['c_id'] ?? ''));
        $slug = trim((string) ($content['slug'] ?? ''));

        if (self::securityManagerUsesCangIds() && $cId !== '') {
            return site_url('Content/Public/' . rawurlencode($cId));
        }

        if ($slug !== '') {
            return site_url('Content/Public/' . rawurlencode($slug));
        }

        return $postId > 0 ? site_url('Content/Public/View/' . $postId) : site_url('Content/Public/Index');
    }

    /**
     * Path segment for {@code Public/{ref}} routes.
     *
     * @param array<string, mixed> $content
     */
    public static function postRefForUrl(array $content): string
    {
        $postId = (int) ($content['id'] ?? 0);
        $cId = trim((string) ($content['c_id'] ?? ''));
        $slug = trim((string) ($content['slug'] ?? ''));

        if (self::securityManagerUsesCangIds() && $cId !== '') {
            return $cId;
        }

        if ($slug !== '') {
            return $slug;
        }

        return (string) $postId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolveRef(string $ref): ?array
    {
        $ref = trim(rawurldecode($ref));
        if ($ref === '') {
            return null;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('public_contents')) {
            return null;
        }

        $securityOn = self::securityManagerUsesCangIds();

        if ($securityOn) {
            $byCid = $db->table('public_contents')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $byCid;
            }
        }

        if (ctype_digit($ref)) {
            $byId = $db->table('public_contents')->where('id', (int) $ref)->get()->getRowArray();
            if (is_array($byId)) {
                return $byId;
            }
        }

        $slug = self::normalizeSlug($ref);
        if ($slug !== '') {
            $bySlug = $db->table('public_contents')->where('slug', $slug)->get()->getRowArray();
            if (is_array($bySlug)) {
                return $bySlug;
            }
        }

        if (! $securityOn) {
            $byCid = $db->table('public_contents')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $byCid;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function canonicalPostRedirectIfNeeded(array $content, string $requestedRef): ?string
    {
        return self::canonicalAdminRedirectIfNeeded($content, $requestedRef, 'post');
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function editUrl(array $content): string
    {
        return site_url('Content/Public/Edit/' . rawurlencode(self::postRefForUrl($content)));
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function deleteUrl(array $content): string
    {
        return site_url('Content/Public/Delete/' . rawurlencode(self::postRefForUrl($content)));
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function canonicalEditRedirectIfNeeded(array $content, string $requestedRef): ?string
    {
        return self::canonicalAdminRedirectIfNeeded($content, $requestedRef, 'edit');
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function canonicalDeleteRedirectIfNeeded(array $content, string $requestedRef): ?string
    {
        return self::canonicalAdminRedirectIfNeeded($content, $requestedRef, 'delete');
    }

    /**
     * @param array<string, mixed> $content
     */
    private static function canonicalAdminRedirectIfNeeded(array $content, string $requestedRef, string $kind): ?string
    {
        if (! self::securityManagerUsesCangIds()) {
            return null;
        }

        $cId = trim((string) ($content['c_id'] ?? ''));
        if ($cId === '') {
            return null;
        }

        $requestedRef = trim(rawurldecode($requestedRef));
        if ($requestedRef === $cId) {
            return null;
        }

        return match ($kind) {
            'edit'   => self::editUrl($content),
            'delete' => self::deleteUrl($content),
            default  => self::postUrl($content),
        };
    }

    private static function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';

        return trim($slug, '-');
    }
}
