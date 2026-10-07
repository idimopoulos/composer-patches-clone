<?php

declare(strict_types=1);

namespace PatchManager\Patch;

use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;
use InvalidArgumentException;
use RuntimeException;

/**
 * Downloads patches through Composer's HTTP client.
 *
 * Using Composer's client means auth.json credentials, proxy settings, the
 * CA bundle and secure-http apply to patch downloads too.
 */
final class PatchDownloader
{
    public function __construct(
        private readonly HttpDownloader $httpDownloader
    ) {
    }

    public function download(string $url): string
    {
        if (!RemoteUrl::isRemote($url)) {
            throw new InvalidArgumentException(sprintf('Patch URL must be an http or https URL, got "%s".', $url));
        }

        try {
            $contents = $this->httpDownloader->get($url)->getBody();
        } catch (TransportException $exception) {
            throw new RuntimeException(
                sprintf('Unable to download patch from %s: %s', $url, $exception->getMessage()),
                0,
                $exception
            );
        }

        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException(sprintf('Unable to download patch from %s: the response was empty.', $url));
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
