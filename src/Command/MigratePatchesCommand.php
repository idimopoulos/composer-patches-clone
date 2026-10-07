<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Command;

use Composer\Command\BaseCommand;
use Idimopoulos\ComposerPatchesClone\Composer\ComposerJsonUpdater;
use Idimopoulos\ComposerPatchesClone\Composer\LockHashUpdater;
use Idimopoulos\ComposerPatchesClone\Patch\PatchDownloader;
use Idimopoulos\ComposerPatchesClone\Patch\PatchWriter;
use Idimopoulos\ComposerPatchesClone\Patch\RemoteUrl;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MigratePatchesCommand extends BaseCommand
{
    public function __construct(
        private readonly PatchDownloader $patchDownloader,
        private readonly PatchWriter $patchWriter,
        private readonly ComposerJsonUpdater $composerJsonUpdater,
        private readonly LockHashUpdater $lockHashUpdater
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
        $failedCount = 0;
        $lockWasFresh = $this->lockHashUpdater->wasFresh();

        foreach ($this->composerJsonUpdater->getPatches() as $package => $patches) {
            if (!is_array($patches)) {
                $output->writeln(sprintf('<comment>Skipped %s: patch definitions must be an object of description => path.</comment>', $package));
                continue;
            }

            foreach ($patches as $description => $path) {
                if (!is_string($description) || !is_string($path)) {
                    $output->writeln(sprintf('<comment>Skipped %s: only "description": "url" entries are supported.</comment>', $package));
                    continue;
                }

                if (!RemoteUrl::isRemote($path)) {
                    continue;
                }

                try {
                    $patchContents = $this->patchDownloader->download($path);
                    $localPath = $this->patchWriter->write($package, $description, $patchContents, $path);
                } catch (RuntimeException | InvalidArgumentException $exception) {
                    $output->writeln(sprintf('<error>Failed %s: %s (%s)</error>', $package, $description, $exception->getMessage()));
                    $failedCount++;
                    continue;
                }

                $this->composerJsonUpdater->replacePatch($package, $description, $localPath, $path);

                $output->writeln(sprintf('Migrated %s: %s -> %s', $package, $description, $localPath));
                $migratedCount++;
            }
        }

        if ($migratedCount > 0) {
            $this->writeLockMessage($output, $this->lockHashUpdater->sync($lockWasFresh));
        }

        if ($migratedCount === 0 && $failedCount === 0) {
            $output->writeln('No remote patches found.');

            return self::SUCCESS;
        }

        if ($failedCount > 0) {
            $output->writeln(sprintf('Migrated %d remote patch(es), %d failed.', $migratedCount, $failedCount));

            return self::FAILURE;
        }

        $output->writeln(sprintf('Migrated %d remote patch(es).', $migratedCount));

        return self::SUCCESS;
    }

    private function writeLockMessage(OutputInterface $output, ?string $message): void
    {
        if ($message !== null) {
            $output->writeln($message);
        }
    }
}
