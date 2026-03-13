<?php

declare(strict_types=1);

namespace PatchManager\Command;

use Composer\Command\BaseCommand;
use PatchManager\Composer\ComposerJsonUpdater;
use PatchManager\Patch\PatchDownloader;
use PatchManager\Patch\PatchWriter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MigratePatchesCommand extends BaseCommand
{
    public function __construct(
        private readonly PatchDownloader $patchDownloader,
        private readonly PatchWriter $patchWriter,
        private readonly ComposerJsonUpdater $composerJsonUpdater
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('patches:migrate')
            ->setDescription('Convert remote patch URLs in composer.json to local patch files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migratedCount = 0;

        foreach ($this->composerJsonUpdater->getPatches() as $package => $patches) {
            foreach ($patches as $description => $path) {
                if (!$this->isRemoteUrl($path)) {
                    continue;
                }

                $patchContents = $this->patchDownloader->download($path);
                $localPath = $this->patchWriter->write($package, $description, $patchContents, $path);
                $this->composerJsonUpdater->replacePatch($package, $description, $localPath);

                $output->writeln(sprintf('Migrated %s: %s -> %s', $package, $description, $localPath));
                $migratedCount++;
            }
        }

        if ($migratedCount === 0) {
            $output->writeln('No remote patches found.');
        } else {
            $output->writeln(sprintf('Migrated %d remote patch(es).', $migratedCount));
        }

        return self::SUCCESS;
    }

    private function isRemoteUrl(string $path): bool
    {
        if (filter_var($path, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($path, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
