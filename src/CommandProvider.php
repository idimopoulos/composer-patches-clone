<?php

declare(strict_types=1);

namespace PatchManager;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use PatchManager\Composer\ComposerJsonUpdater;
use PatchManager\Command\ClonePatchCommand;
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

        return [
            new ClonePatchCommand(
                new PatchDownloader(),
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($projectRoot . DIRECTORY_SEPARATOR . 'composer.json')
            ),
            new MigratePatchesCommand(
                new PatchDownloader(),
                new PatchWriter($projectRoot),
                new ComposerJsonUpdater($projectRoot . DIRECTORY_SEPARATOR . 'composer.json')
            ),
        ];
    }
}
