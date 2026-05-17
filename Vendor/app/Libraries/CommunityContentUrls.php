<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Community topic/post public URLs — uses {@see SecurityCangService} {@code c_id} when Security Manager is enabled.
 */
final class CommunityContentUrls
{
    public static function securityManagerUsesCangIds(): bool
    {
        return CommunityForumUrls::securityManagerUsesCangIds();
    }

    /**
     * @param array<string, mixed> $post Topic or content row with `id` and optional `c_id`
     */
    public static function topicUrl(array $post): string
    {
        $postId = (int) ($post['id'] ?? 0);
        $cId = trim((string) ($post['c_id'] ?? ''));

        if (self::securityManagerUsesCangIds() && $cId !== '') {
            return site_url('Content/Community/Topic/' . rawurlencode($cId));
        }

        return site_url('Content/Community/Topic/' . $postId);
    }

    /**
     * Path segment for {@code Topic/{ref}} routes and forms.
     *
     * @param array<string, mixed> $post
     */
    public static function topicRefForUrl(array $post): string
    {
        $postId = (int) ($post['id'] ?? 0);
        $cId = trim((string) ($post['c_id'] ?? ''));

        if (self::securityManagerUsesCangIds() && $cId !== '') {
            return $cId;
        }

        return (string) $postId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolveTopicRef(string $ref): ?array
    {
        $ref = trim(rawurldecode($ref));
        if ($ref === '') {
            return null;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('community_contents')) {
            return null;
        }

        $securityOn = self::securityManagerUsesCangIds();

        if ($securityOn) {
            $byCid = $db->table('community_contents')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $byCid;
            }
        }

        if (ctype_digit($ref)) {
            $byId = $db->table('community_contents')->where('id', (int) $ref)->get()->getRowArray();
            if (is_array($byId)) {
                return $byId;
            }
        }

        if (! $securityOn) {
            $byCid = $db->table('community_contents')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $byCid;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function canonicalTopicRedirectIfNeeded(array $post, string $requestedRef): ?string
    {
        return self::canonicalAdminRedirectIfNeeded($post, $requestedRef, 'topic');
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function editUrl(array $post): string
    {
        return site_url('Content/Community/Edit/' . rawurlencode(self::topicRefForUrl($post)));
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function deleteUrl(array $post): string
    {
        return site_url('Content/Community/Delete/' . rawurlencode(self::topicRefForUrl($post)));
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function canonicalEditRedirectIfNeeded(array $post, string $requestedRef): ?string
    {
        return self::canonicalAdminRedirectIfNeeded($post, $requestedRef, 'edit');
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function canonicalDeleteRedirectIfNeeded(array $post, string $requestedRef): ?string
    {
        return self::canonicalAdminRedirectIfNeeded($post, $requestedRef, 'delete');
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function canonicalAdminRedirectIfNeeded(array $post, string $requestedRef, string $kind): ?string
    {
        if (! self::securityManagerUsesCangIds()) {
            return null;
        }

        $cId = trim((string) ($post['c_id'] ?? ''));
        if ($cId === '') {
            return null;
        }

        $requestedRef = trim(rawurldecode($requestedRef));
        if ($requestedRef === $cId) {
            return null;
        }

        return match ($kind) {
            'edit'   => self::editUrl($post),
            'delete' => self::deleteUrl($post),
            default  => self::topicUrl($post),
        };
    }
}
