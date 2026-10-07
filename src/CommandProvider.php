<?php

declare(strict_types=1);

namespace PatchManager;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Util\HttpDownloader;
use PatchManager\Command\ClonePatchCommand;
use PatchManager\Command\ListPatchesCommand;
use PatchManager\Command\MigratePatchesCommand;
use PatchManager\Composer\ComposerJsonUpdater;
use PatchManager\Patch\PatchDownloader;
use PatchManager\Patch\PatchWriter;

final class CommandProvider implements CommandProviderCapability
{
    /**
     * @param array<string, mixed> $args
     *   Provided by Composer: 'composer', 'io' and 'plugin'.
     */
    public function __construct(
        private readonly array $args = []
    ) {
    }

    public function getCommands(): array
    {
        $projectRoot = getcwd();

        if ($projectRoot === false) {
            $projectRoot = '.';
        }

        // Honour the COMPOSER environment variable the same way Composer does.
        $composerFile = $this->resolvePath($projectRoot, Factory::getComposerFile());
        $patchDownloader = new PatchDownloader($this->httpDownloader());

        return [
            new ClonePatchCommand(
                $patchDownloader,
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($composerFile)
            ),
            new MigratePatchesCommand(
                $patchDownloader,
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($composerFile)
            ),
            new ListPatchesCommand(
                $projectRoot,
                new ComposerJsonUpdater($composerFile)
            ),
        ];
    }

    /**
     * Reuses Composer's HTTP client so auth.json, proxies and secure-http apply.
     */
    private function httpDownloader(): HttpDownloader
    {
        $composer = $this->args['composer'] ?? null;

        if ($composer instanceof Composer) {
            return $composer->getLoop()->getHttpDownloader();
        }

        $io = $this->args['io'] ?? null;
        $io = $io instanceof IOInterface ? $io : new NullIO();

        return Factory::createHttpDownloader($io, Factory::createConfig($io));
    }

    private function resolvePath(string $projectRoot, string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return $projectRoot . DIRECTORY_SEPARATOR . $path;
    }
}
