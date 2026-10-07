<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Composer;

use RuntimeException;

/**
 * Knows where a project keeps its patch definitions.
 *
 * Patches can live in composer.json (extra.patches) and in a separate
 * patches file, following the rules of the installed
 * cweagans/composer-patches version:
 * - 1.x reads extra.patches-file, and only when extra.patches is absent;
 * - 2.x reads COMPOSER_PATCHES_PATCHES_FILE, else
 *   extra.composer-patches.patches-file, else patches.json, and merges it
 *   with extra.patches;
 * - when the version is unknown, either setting or an existing
 *   patches.json is used.
 * Patch paths and the patches-file path are relative to the working
 * directory, as they are for Composer and cweagans/composer-patches.
 */
final class PatchConfig
{
    public const DEFAULT_PATCHES_FILE = 'patches.json';

    private readonly ComposerJsonUpdater $composerJson;

    /**
     * @param int|null $composerPatchesMajor
     *   The installed cweagans/composer-patches major version, if known.
     */
    public function __construct(
        string $composerJsonPath,
        private readonly ?int $composerPatchesMajor = null
    ) {
        $this->composerJson = new ComposerJsonUpdater($composerJsonPath);
    }

    public function composerJson(): ComposerJsonUpdater
    {
        return $this->composerJson;
    }

    /**
     * The patches file the installed cweagans/composer-patches would read.
     */
    public function patchesFilePath(): ?string
    {
        $extra = $this->composerJson->getExtra();
        $legacy = $this->stringOrNull($extra['patches-file'] ?? null);
        $current = $this->stringOrNull(getenv('COMPOSER_PATCHES_PATCHES_FILE'))
            ?? $this->stringOrNull($extra['composer-patches']['patches-file'] ?? null);
        $default = is_file(self::DEFAULT_PATCHES_FILE) ? self::DEFAULT_PATCHES_FILE : null;

        return match ($this->composerPatchesMajor) {
            // 1.x returns extra.patches before it looks at a patches file.
            1 => array_key_exists('patches', $extra) ? null : $legacy,
            2 => $current ?? $default,
            default => $current ?? $legacy ?? $default,
        };
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

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
