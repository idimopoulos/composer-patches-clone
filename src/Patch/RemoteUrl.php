<?php

declare(strict_types=1);

namespace PatchManager\Patch;

final class RemoteUrl
{
    /**
     * Whether a patch path is an http(s) URL rather than a local file.
     */
    public static function isRemote(string $path): bool
    {
        if (filter_var($path, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
