<?php

declare(strict_types=1);

namespace PatchManager\Tests\Support;

use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Util\HttpDownloader;

/**
 * Builds real Composer services for tests.
 */
final class ComposerServices
{
    /**
     * Creates an HttpDownloader configured like Composer would be.
     *
     * secure-http is off by default because the fixture server speaks plain
     * http on 127.0.0.1.
     *
     * @param array<string, mixed> $config
     *   Composer "config" settings, e.g. ['http-basic' => [...]].
     */
    public static function httpDownloader(array $config = []): HttpDownloader
    {
        $composerConfig = new Config(false);
        $composerConfig->merge(['config' => $config + ['secure-http' => false]]);

        $io = new NullIO();
        $io->loadConfiguration($composerConfig);

        return Factory::createHttpDownloader($io, $composerConfig);
    }
}
