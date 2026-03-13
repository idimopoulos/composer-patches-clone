<?php

declare(strict_types=1);

namespace PatchManager\Patch;

use InvalidArgumentException;
use RuntimeException;

final class PatchWriter
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $defaultBasePath = 'resources/patch'
    ) {
    }

    public function write(
        string $package,
        string $description,
        string $patchContent,
        string $url,
        ?string $basePath = null,
        ?string $patchName = null
    ): string {
        [$vendor, $name] = $this->splitPackage($package);

        $filename = $this->resolveFilename($url, $patchName);
        $resolvedBasePath = $this->normalizeBasePath($basePath ?? $this->defaultBasePath);
        $relativePath = sprintf('%s/%s/%s/%s', $resolvedBasePath, $vendor, $name, $filename);
        $this->writeToRelativePath($relativePath, $patchContent);

        return $relativePath;
    }

    public function writeToRelativePath(string $relativePath, string $patchContent): string
    {
        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create patch directory %s.', $directory));
        }

        if (file_put_contents($absolutePath, $patchContent) === false) {
            throw new RuntimeException(sprintf('Unable to write patch file %s.', $absolutePath));
        }

        return $relativePath;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPackage(string $package): array
    {
        $parts = explode('/', trim($package));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException('Package must be in vendor/package format.');
        }

        return [$parts[0], $parts[1]];
    }

    private function resolveFilename(string $url, ?string $patchName): string
    {
        $filename = $patchName !== null && trim($patchName) !== ''
            ? trim($patchName)
            : basename((string) parse_url($url, PHP_URL_PATH));

        if ($filename === '' || $filename === '.' || $filename === '/' || $filename === '\\') {
            throw new InvalidArgumentException('URL must contain a valid patch filename.');
        }

        if (!str_ends_with(strtolower($filename), '.patch')) {
            $filename .= '.patch';
        }

        return $filename;
    }

    private function normalizeBasePath(string $basePath): string
    {
        $normalized = trim(str_replace('\\', '/', $basePath));
        $normalized = trim($normalized, '/');

        if ($normalized === '') {
            throw new InvalidArgumentException('Base path must not be empty.');
        }

        return $normalized;
    }
}
