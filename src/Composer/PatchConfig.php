<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Composer;

use RuntimeException;

/**
 * Knows where a project keeps its patch definitions.
 *
 * Patches can live in composer.json (extra.patches) and in a separate
 * patches file:
 * - cweagans/composer-patches 1.x reads extra.patches-file, and only when
 *   extra.patches is absent;
 * - 2.x reads extra.composer-patches.patches-file, defaulting to
 *   patches.json, and merges it with extra.patches.
 * Patch paths and the patches-file path are relative to the working
 * directory, as they are for Composer and cweagans/composer-patches.
 */
final class PatchConfig
{
    public const DEFAULT_PATCHES_FILE = 'patches.json';

    private readonly ComposerJsonUpdater $composerJson;

    public function __construct(string $composerJsonPath)
    {
        $this->composerJson = new ComposerJsonUpdater($composerJsonPath);
    }

    public function composerJson(): ComposerJsonUpdater
    {
        return $this->composerJson;
    }

    /**
     * The configured patches file, or patches.json when it exists.
     */
    public function patchesFilePath(): ?string
    {
        $configured = $this->configuredPatchesFile();

        if ($configured !== null) {
            return $configured;
        }

        return is_file(self::DEFAULT_PATCHES_FILE) ? self::DEFAULT_PATCHES_FILE : null;
    }

    /**
     * Every file that currently holds patch definitions, composer.json first.
     *
     * @return list<ComposerJsonUpdater>
     */
    public function stores(): array
    {
        $stores = [$this->composerJson];
        $patchesFile = $this->patchesFilePath();

        if ($patchesFile !== null && is_file($patchesFile)) {
            $stores[] = ComposerJsonUpdater::forPatchesFile($patchesFile);
        }

        return $stores;
    }

    /**
     * Where a patch belongs: the file that already defines it, otherwise the
     * patches file when composer.json has no patches of its own, otherwise
     * composer.json.
     */
    public function storeFor(string $package, string $description): ComposerJsonUpdater
    {
        foreach ($this->stores() as $store) {
            if ($this->defines($store, $package, $description)) {
                return $store;
            }
        }

        $patchesFile = $this->patchesFilePath();

        if ($patchesFile === null || array_key_exists('patches', $this->composerJson->getExtra())) {
            return $this->composerJson;
        }

        if (!is_file($patchesFile)) {
            $this->createPatchesFile($patchesFile);
        }

        return ComposerJsonUpdater::forPatchesFile($patchesFile);
    }

    /**
     * A reminder for cweagans/composer-patches 2.x, or null when not needed.
     *
     * 2.x applies patches from its patches lock, not from the definitions, so
     * after a change the lock must be rebuilt and the patches re-applied.
     */
    public function relockHint(): ?string
    {
        $lockFile = $this->patchesLockPath();

        if (!is_file($lockFile)) {
            return null;
        }

        return sprintf(
            '%s found (cweagans/composer-patches 2.x): run "composer patches-relock" and then "composer patches-repatch" to apply the change.',
            basename($lockFile)
        );
    }

    /**
     * Mirrors cweagans\Composer\Plugin\Patches::getPatchesLockFilePath().
     */
    private function patchesLockPath(): string
    {
        $composerFile = $this->composerJson->getPath();
        $directory = dirname(realpath($composerFile) ?: $composerFile);
        $base = pathinfo($composerFile, PATHINFO_FILENAME);

        return $directory . DIRECTORY_SEPARATOR . ($base === 'composer' ? 'patches.lock.json' : $base . '-patches.lock.json');
    }

    private function configuredPatchesFile(): ?string
    {
        $extra = $this->composerJson->getExtra();
        $path = $extra['composer-patches']['patches-file'] ?? $extra['patches-file'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function defines(ComposerJsonUpdater $store, string $package, string $description): bool
    {
        $patches = $store->getPatches()[$package] ?? null;

        return is_array($patches) && array_key_exists($description, $patches);
    }

    private function createPatchesFile(string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory %s.', $directory));
        }

        if (file_put_contents($path, "{\n    \"patches\": {}\n}\n") === false) {
            throw new RuntimeException(sprintf('Unable to create %s.', $path));
        }
    }
}
