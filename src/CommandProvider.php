<?php

declare(strict_types=1);

namespace PatchManager;

use Composer\Factory;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use PatchManager\Composer\ComposerJsonUpdater;
use PatchManager\Command\ClonePatchCommand;
use PatchManager\Command\ListPatchesCommand;
use PatchManager\Command\MigratePatchesCommand;
use PatchManager\Patch\PatchDownloader;
use PatchManager\Patch\PatchWriter;

final class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        $projectRoot = getcwd();

        if ($projectRoot === false) {
            $projectRoot = '.';
        }

        // Honour the COMPOSER environment variable the same way Composer does.
        $composerFile = $this->resolvePath($projectRoot, Factory::getComposerFile());

        return [
            new ClonePatchCommand(
                new PatchDownloader(),
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($composerFile)
            ),
            new MigratePatchesCommand(
                new PatchDownloader(),
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($composerFile)
            ),
            new ListPatchesCommand(
                $projectRoot,
                new ComposerJsonUpdater($composerFile)
            ),
        ];
    }

    private function resolvePath(string $projectRoot, string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return $projectRoot . DIRECTORY_SEPARATOR . $path;
    }
}
