<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Command;

use Composer\Command\BaseCommand;
use Idimopoulos\ComposerPatchesClone\Composer\LockHashUpdater;
use Idimopoulos\ComposerPatchesClone\Composer\PatchConfig;
use Idimopoulos\ComposerPatchesClone\Patch\PatchDownloader;
use Idimopoulos\ComposerPatchesClone\Patch\PatchWriter;
use Idimopoulos\ComposerPatchesClone\Patch\RemoteUrl;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ClonePatchCommand extends BaseCommand
{
    public function __construct(
        private readonly PatchDownloader $patchDownloader,
        private readonly PatchWriter $patchWriter,
        private readonly PatchConfig $patchConfig,
        private readonly LockHashUpdater $lockHashUpdater
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('patches:clone')
            ->setDescription('Request patch cloning for a package.')
            ->addArgument('package', InputArgument::REQUIRED, 'The target package.')
            ->addArgument('url', InputArgument::REQUIRED, 'The patch URL.')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'A human-readable patch description.')
            ->addOption('base-path', null, InputOption::VALUE_REQUIRED, 'Base directory for local patches.')
            ->addOption('patch-name', null, InputOption::VALUE_REQUIRED, 'Override the saved patch filename.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $package = (string) $input->getArgument('package');
        $url = (string) $input->getArgument('url');
        $description = (string) ($input->getOption('description') ?: basename((string) parse_url($url, PHP_URL_PATH)));
        $basePath = $input->getOption('base-path');
        $patchName = $input->getOption('patch-name');

        $patchContents = $this->patchDownloader->download($url);
        $output->writeln('Patch downloaded');

        $store = $this->patchConfig->storeFor($package, $description);
        $store->assertCanStore($package);
        $existingPath = $store->getPatchPath($package, $description);
        if (is_string($existingPath) && !RemoteUrl::isRemote($existingPath)) {
            $patchPath = $this->patchWriter->writeToRelativePath($existingPath, $patchContents);
        } else {
            $patchPath = $this->patchWriter->write(
                $package,
                $description,
                $patchContents,
                $url,
                is_string($basePath) ? $basePath : null,
                is_string($patchName) ? $patchName : null
            );
        }

        $output->writeln(sprintf('Patch saved to %s', $patchPath));

        $lockWasFresh = $this->lockHashUpdater->wasFresh();
        $store->replacePatch($package, $description, $patchPath, $url);
        $output->writeln(sprintf('%s updated', basename($store->getPath())));

        // Only composer.json feeds the lock's content-hash.
        if ($store === $this->patchConfig->composerJson()) {
            $this->writeLockMessage($output, $this->lockHashUpdater->sync($lockWasFresh));
        }

        $this->writeLockMessage($output, $this->patchConfig->relockHint());

        return self::SUCCESS;
    }

    private function writeLockMessage(OutputInterface $output, ?string $message): void
    {
        if ($message !== null) {
            $output->writeln($message);
        }
    }
}
