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
    /**
     * @param array<string, mixed> $user User row with numeric `id` and optional `c_id`
     */
    public static function publicProfileUrl(array $user): string
    {
        $userId = (int) ($user['id'] ?? 0);
        $cid = trim((string) ($user['c_id'] ?? ''));
        try {
            $useCang = (new ModuleSettings())->isEnabled(ModuleSettings::SECURITY_MANAGER);
        } catch (\Throwable) {
            $useCang = true;
        }

        if ($useCang && $cid !== '') {
            return site_url('Member/User/Profile/' . rawurlencode($cid));
        }

        return site_url('Member/User/Profile/' . $userId);
    }

    public static function activationUrl(string $token): string
    {
        return site_url('Member/User/Activate/' . rawurlencode($token));
    }

    public static function deactivationUrl(string $token): string
    {
        return site_url('Member/User/DeActivate/' . rawurlencode($token));
    }

    /**
     * Normalizes a token path segment from the router (handles encoded special characters).
     */
    public static function tokenFromPath(string $segment): string
    {
        return rawurldecode($segment);
    }
}
