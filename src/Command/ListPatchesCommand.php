<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Command;

use Composer\Command\BaseCommand;
use Idimopoulos\ComposerPatchesClone\Composer\ComposerJsonUpdater;
use Idimopoulos\ComposerPatchesClone\Patch\RemoteUrl;
use InvalidArgumentException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ListPatchesCommand extends BaseCommand
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ComposerJsonUpdater $composerJsonUpdater
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('patches:list')
            ->setDescription('List configured patches and the URLs they were downloaded from.')
            ->addArgument('package', InputArgument::OPTIONAL, 'Only list patches for this package. Wildcards are allowed, e.g. "drupal/*".')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table or json.', 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string) $input->getOption('format');

        if (!in_array($format, ['table', 'json'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported format "%s". Use "table" or "json".', $format));
        }

        $filter = $input->getArgument('package');
        $rows = $this->collectRows(is_string($filter) && $filter !== '' ? strtolower($filter) : null);

        if ($format === 'json') {
            $output->writeln((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $output->writeln(is_string($filter) && $filter !== ''
                ? sprintf('No patches found for %s.', $filter)
                : 'No patches found.');

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Package', 'Description', 'Patch', 'Source URL']);

        foreach ($rows as $row) {
            $patch = $row['path'];

            if ($row['remote']) {
                $patch = '(remote)';
            } elseif (!$row['exists']) {
                $patch .= ' (missing)';
            }

            $table->addRow([$row['package'], $row['description'], $patch, $row['source'] ?? '(unknown)']);
        }

        $table->render();

        return self::SUCCESS;
    }

    /**
     * @return list<array{package: string, description: string, path: string, source: ?string, remote: bool, exists: bool}>
     */
    private function collectRows(?string $filter): array
    {
        $sources = $this->composerJsonUpdater->getSources();
        $rows = [];

        foreach ($this->composerJsonUpdater->getPatches() as $package => $patches) {
            if (!is_array($patches) || ($filter !== null && !fnmatch($filter, strtolower($package)))) {
                continue;
            }

            foreach ($patches as $description => $path) {
                if (!is_string($description) || !is_string($path)) {
                    continue;
                }

                $remote = RemoteUrl::isRemote($path);

                $rows[] = [
                    'package' => $package,
                    'description' => $description,
                    'path' => $path,
                    'source' => $remote ? $path : ($sources[$package][$description] ?? null),
                    'remote' => $remote,
                    'exists' => $remote || is_file($this->resolvePath($path)),
                ];
            }
        }

        return $rows;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
