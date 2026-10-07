<?php

declare(strict_types=1);

namespace PatchManager\Tests\Support;

/**
 * Filesystem helpers for test fixtures.
 */
final class Filesystem
{
    public static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) && !is_link($path)) {
                self::removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
