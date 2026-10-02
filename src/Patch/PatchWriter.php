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
        $directory = sprintf('%s/%s/%s', $resolvedBasePath, $vendor, $name);
        $relativePath = $this->resolveAvailablePath($directory, $filename, $patchContent);
        $this->writeToRelativePath($relativePath, $patchContent);

        return $relativePath;
    }

    public function writeToRelativePath(string $relativePath, string $patchContent): string
    {
        $absolutePath = $this->absolutePath($relativePath);
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
     * Returns a path that is free or already holds identical content.
     *
     * Two different patches can share a URL basename (e.g. "fix.patch" from
     * two issues). The second one gets a numeric suffix instead of silently
     * replacing the first.
     */
    private function resolveAvailablePath(string $directory, string $filename, string $patchContent): string
    {
        $extensionPosition = strrpos($filename, '.');
        $stem = $extensionPosition === false ? $filename : substr($filename, 0, $extensionPosition);
        $extension = $extensionPosition === false ? '' : substr($filename, $extensionPosition);

        for ($attempt = 1; ; $attempt++) {
            $candidate = $attempt === 1 ? $filename : sprintf('%s-%d%s', $stem, $attempt, $extension);
            $relativePath = $directory . '/' . $candidate;
            $absolutePath = $this->absolutePath($relativePath);

            if (!file_exists($absolutePath) || file_get_contents($absolutePath) === $patchContent) {
                return $relativePath;
            }
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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
        if ($patchName !== null && trim($patchName) !== '') {
            $filename = trim($patchName);

            if (preg_match('#[/\\\\]#', $filename) === 1 || $filename === '.' || $filename === '..') {
                throw new InvalidArgumentException('Patch name must be a plain filename without directories.');
            }
        } else {
            $filename = basename((string) parse_url($url, PHP_URL_PATH));
        }

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

        if (in_array('..', explode('/', $normalized), true)) {
            throw new InvalidArgumentException('Base path must not contain ".." segments.');
        }

        return $normalized;
    }
}
