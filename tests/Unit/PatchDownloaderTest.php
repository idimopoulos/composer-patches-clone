<?php

declare(strict_types=1);

namespace PatchManager\Tests\Unit;

use PatchManager\Patch\PatchDownloader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PatchDownloaderTest extends TestCase
{
    public function testItDownloadsAValidPatch(): void
    {
        $downloader = new PatchDownloader();
        $url = 'data://text/plain,' . rawurlencode("--- a/file.txt\n+++ b/file.txt\n@@\n-old line\n+new line\n");

        $contents = $downloader->download($url);

        self::assertStringContainsString('--- a/file.txt', $contents);
        self::assertStringContainsString('+++ b/file.txt', $contents);
    }

    public function testItThrowsWhenContentIsNotAPatch(): void
    {
        $downloader = new PatchDownloader();
        $url = 'data://text/plain,' . rawurlencode("plain text\nnothing patch-like here\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Downloaded content is not a valid patch.');

        $downloader->download($url);
    }

    public function testItThrowsWhenDownloadFails(): void
    {
        $downloader = new PatchDownloader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to download patch');

        $downloader->download('file:///definitely/not/a/real/path.patch');
    }
}
