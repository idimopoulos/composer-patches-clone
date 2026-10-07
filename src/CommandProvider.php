<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Util\HttpDownloader;
use Idimopoulos\ComposerPatchesClone\Command\ClonePatchCommand;
use Idimopoulos\ComposerPatchesClone\Command\ListPatchesCommand;
use Idimopoulos\ComposerPatchesClone\Command\MigratePatchesCommand;
use Idimopoulos\ComposerPatchesClone\Composer\ComposerJsonUpdater;
use Idimopoulos\ComposerPatchesClone\Composer\LockHashUpdater;
use Idimopoulos\ComposerPatchesClone\Patch\PatchDownloader;
use Idimopoulos\ComposerPatchesClone\Patch\PatchWriter;

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

        // Honour the COMPOSER environment variable the same way Composer does;
        // the path is relative to the working directory, as Composer uses it.
        $composerFile = Factory::getComposerFile();
        $patchDownloader = new PatchDownloader($this->httpDownloader());
        $lockHashUpdater = new LockHashUpdater(
            $composerFile,
            $this->lockEnabled() ? Factory::getLockFile($composerFile) : null
        );

        return [
            new ClonePatchCommand(
                $patchDownloader,
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($composerFile),
                $lockHashUpdater
            ),
            new MigratePatchesCommand(
                $patchDownloader,
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($composerFile),
                $lockHashUpdater
            ),
            new ListPatchesCommand(
                $projectRoot,
                new ComposerJsonUpdater($composerFile)
            ),
        ];
    }

    /**
     * Whether the project uses a lock file ("lock": false disables it).
     */
    private function lockEnabled(): bool
    {
        $composer = $this->args['composer'] ?? null;

        return !$composer instanceof Composer || $composer->getConfig()->get('lock') !== false;
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
}
