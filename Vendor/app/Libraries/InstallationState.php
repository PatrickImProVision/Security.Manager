<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Persistent install flag (internal module: Installation Manager).
 */
final class InstallationState
{
    public const RELATIVE_FLAG_PATH = 'install/installed.flag';

    public static function flagPath(): string
    {
        return WRITEPATH . self::RELATIVE_FLAG_PATH;
    }

    private static ?bool $installedCache = null;

    public static function isInstalled(): bool
    {
        return self::$installedCache ??= is_file(self::flagPath());
    }

    public static function markInstalled(): void
    {
        self::$installedCache = null;
        $path = self::flagPath();
        $dir   = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(
                [
                    'installed_at' => date('c'),
                    'product'      => 'Product Store',
                ],
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
            ),
            LOCK_EX,
        );

        self::$installedCache = true;
    }

    public static function clearFlag(): void
    {
        self::$installedCache = null;
        if (is_file(self::flagPath())) {
            unlink(self::flagPath());
        }
    }
}
