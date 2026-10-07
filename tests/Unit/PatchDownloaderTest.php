<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Unit;

use Idimopoulos\ComposerPatchesClone\Patch\PatchDownloader;
use Idimopoulos\ComposerPatchesClone\Tests\Support\ComposerServices;
use Idimopoulos\ComposerPatchesClone\Tests\Support\PatchServer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PatchDownloaderTest extends TestCase
{
    private static PatchServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = PatchServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testItDownloadsAValidPatch(): void
    {
        $downloader = new PatchDownloader(ComposerServices::httpDownloader());

        $contents = $downloader->download(self::$server->url('example.patch'));

        self::assertStringEqualsFile(PatchServer::fixturesDirectory() . '/example.patch', $contents);
    }

    public function testItThrowsWhenContentIsNotAPatch(): void
    {
        $downloader = new PatchDownloader(ComposerServices::httpDownloader());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Downloaded content is not a valid patch.');

        $downloader->download(self::$server->url('not-a-patch.html'));
    }

    public function testItThrowsWhenDownloadFails(): void
    {
        $downloader = new PatchDownloader(ComposerServices::httpDownloader());
        $url = self::$server->url('missing.patch');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to download patch from ' . $url);

        $downloader->download($url);
    }

    public function testItSendsCredentialsFromComposerAuthConfig(): void
    {
        $url = self::$server->url('private/secret.patch');
        // Composer keys credentials by host, plus the port when there is one.
        $origin = parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT);
        $withAuth = new PatchDownloader(ComposerServices::httpDownloader([
            'http-basic' => [$origin => ['username' => 'user', 'password' => 'secret']],
        ]));

        self::assertStringContainsString('+new line', $withAuth->download($url));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to download patch');

        (new PatchDownloader(ComposerServices::httpDownloader()))->download($url);
    }

    public function testItRespectsSecureHttp(): void
    {
        $downloader = new PatchDownloader(ComposerServices::httpDownloader(['secure-http' => true]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('secure-http');

        $downloader->download(self::$server->url('example.patch'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonHttpUrls(): array
    {
        return [
            'local path' => ['/etc/passwd'],
            'file scheme' => ['file:///etc/passwd'],
            'data scheme' => ['data://text/plain,--- a'],
            'phar scheme' => ['phar:///tmp/x.phar/patch'],
        ];
    }

    /**
     * @dataProvider nonHttpUrls
     */
    public function testItOnlyAcceptsHttpUrls(string $url): void
    {
        $downloader = new PatchDownloader(ComposerServices::httpDownloader());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an http or https URL');

        $downloader->download($url);
    }
}
