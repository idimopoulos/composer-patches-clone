<?php

declare(strict_types=1);

namespace PatchManager\Command;

use Composer\Command\BaseCommand;
use PatchManager\Composer\ComposerJsonUpdater;
use PatchManager\Patch\PatchDownloader;
use PatchManager\Patch\PatchWriter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ClonePatchCommand extends BaseCommand
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

        $existingPath = $this->composerJsonUpdater->getPatchPath($package, $description);
        if (is_string($existingPath) && !$this->isRemoteUrl($existingPath)) {
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

        $this->composerJsonUpdater->replacePatch($package, $description, $patchPath);
        $output->writeln('composer.json updated');

        return self::SUCCESS;
    }

    private function isRemoteUrl(string $path): bool
    {
        if (filter_var($path, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
