<?php

declare(strict_types=1);

namespace PatchManager\Patch;

use RuntimeException;

final class PatchDownloader
{
    public function download(string $url): string
    {
        $contents = @file_get_contents($url);

        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException(sprintf('Unable to download patch from %s.', $url));
        }

        if (!$this->looksLikePatch($contents)) {
            throw new RuntimeException('Downloaded content is not a valid patch.');
        }

        return $contents;
    }

    private function looksLikePatch(string $contents): bool
    {
        foreach (preg_split("/\r\n|\n|\r/", $contents) ?: [] as $line) {
            if (
                str_starts_with($line, '--- ') ||
                str_starts_with($line, '+++ ') ||
                str_starts_with($line, 'diff --')
            ) {
                return true;
            }
        }

        return false;
    }
}
