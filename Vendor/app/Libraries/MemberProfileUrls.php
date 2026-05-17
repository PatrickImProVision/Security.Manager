<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Builds public member profile URLs: uses {@see SecurityCangService} {@code c_id} when Security Manager is enabled.
 *
 * @see User::viewUser()
 */
final class MemberProfileUrls
{
    /** Prefix for URL path tokens so CANG/special characters never break routing. */
    private const URL_TOKEN_PREFIX = 't.';

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
     * Query value for {@code recipient_id} on personal message create links.
     *
     * @param array<string, mixed> $user User row with numeric `id` and optional `c_id`
     */
    public static function recipientRefForUrl(array $user): string
    {
        $userId = (int) ($user['id'] ?? 0);
        $cid = trim((string) ($user['c_id'] ?? ''));
        if (self::securityManagerUsesCangIds() && $cid !== '') {
            return $cid;
        }

        return (string) $userId;
    }

    /**
     * @param array<string, mixed> $user User row with numeric `id` and optional `c_id`
     */
    public static function personalMessageCreateUrl(array $user): string
    {
        $ref = self::recipientRefForUrl($user);
        if ($ref === '' || $ref === '0') {
            return site_url('Content/Personal/Create');
        }

        return site_url('Content/Personal/Create') . '?recipient_id=' . rawurlencode($ref);
    }

    /**
     * Resolve a user reference from a URL segment or query value (numeric `id` or `c_id`).
     *
     * @return array<string, mixed>|null
     */
    public static function resolveUserRef(string $ref): ?array
    {
        $ref = trim(rawurldecode($ref));
        if ($ref === '') {
            return null;
        }

        $db = AppDatabase::connection();
        $securityManagerOn = self::securityManagerUsesCangIds();

        if ($securityManagerOn) {
            $byCid = $db->table('users')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $byCid;
            }
        }

        if (ctype_digit($ref)) {
            $byId = $db->table('users')->where('id', (int) $ref)->get()->getRowArray();
            if (is_array($byId)) {
                return $byId;
            }
        }

        if (! $securityManagerOn) {
            $byCid = $db->table('users')->where('c_id', $ref)->get()->getRowArray();
            if (is_array($byCid)) {
                return $byCid;
            }
        }

        return null;
    }
    /**
     * @param array<string, mixed> $user User row with numeric `id` and optional `c_id`
     */
    public static function publicProfileUrl(array $user): string
    {
        $userId = (int) ($user['id'] ?? 0);
        $cid = trim((string) ($user['c_id'] ?? ''));

        if (self::securityManagerUsesCangIds() && $cid !== '') {
            return site_url('Member/User/Profile/' . rawurlencode($cid));
        }

        return site_url('Member/User/Profile/' . $userId);
    }

    public static function activationUrl(string $token): string
    {
        return site_url('Member/User/Activate/' . self::urlTokenEncode($token));
    }

    public static function deactivationUrl(string $token): string
    {
        return site_url('Member/User/DeActivate/' . self::urlTokenEncode($token));
    }

    public static function resetPasswordUrl(string $token): string
    {
        return site_url('Member/User/ResetPassword/' . self::urlTokenEncode($token));
    }

    public static function resetPasswordProposeUrl(string $token): string
    {
        return site_url('Member/User/ResetPassword/Propose/' . self::urlTokenEncode($token));
    }

    /**
     * Encodes a stored token for a URL path (only letters, digits, -, _, and t.).
     */
    public static function urlTokenEncode(string $token): string
    {
        return self::URL_TOKEN_PREFIX . rtrim(strtr(base64_encode($token), '+/', '-_'), '=');
    }

    /**
     * Normalizes a token path segment from the router (URL-safe wrapper or legacy encoding).
     */
    public static function tokenFromPath(string $segment): string
    {
        $segment = rawurldecode($segment);

        if (str_starts_with($segment, self::URL_TOKEN_PREFIX)) {
            $decoded = self::base64UrlDecode(substr($segment, strlen(self::URL_TOKEN_PREFIX)));
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return $segment;
    }

    private static function base64UrlDecode(string $payload): ?string
    {
        if ($payload === '') {
            return null;
        }

        $padded = strtr($payload, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
