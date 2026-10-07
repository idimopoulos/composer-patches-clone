<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Composer;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use RuntimeException;
use stdClass;

/**
 * Reads and writes patch definitions in a JSON file.
 *
 * In composer.json they live under "extra"; in a cweagans patches file
 * ("patches-file") they live at the root of the file.
 */
final class ComposerJsonUpdater
{
    public const SOURCES_KEY = 'patches-sources';

    public function __construct(
        private readonly string $composerJsonPath,
        private readonly bool $patchesAtRoot = false
    ) {
    }

    public static function forPatchesFile(string $path): self
    {
        return new self($path, true);
    }

    public function getPath(): string
    {
        return $this->composerJsonPath;
    }

    /**
     * Returns the "extra" section of the file (empty for a patches file).
     *
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        if ($this->patchesAtRoot) {
            return [];
        }

        $extra = $this->readData()['extra'] ?? [];

        return is_array($extra) ? $extra : [];
    }

    public function addPatch(string $package, string $description, string $path): void
    {
        $patches = $this->getPatches();

        if (isset($patches[$package][$description])) {
            return;
        }

        $this->setPatch($patches, $package, $description, $path);
    }

    /**
     * Returns the patches as written; entries are not guaranteed to be strings.
     *
     * @return array<string, mixed>
     */
    public function getPatches(): array
    {
        $patches = $this->patchNode()['patches'] ?? [];

        if (!is_array($patches)) {
            return [];
        }

        return $patches;
    }

    /**
     * Points a patch at a path, optionally recording where it was downloaded from.
     */
    public function replacePatch(string $package, string $description, string $path, ?string $sourceUrl = null): void
    {
        $this->setPatch($this->getPatches(), $package, $description, $path, $sourceUrl);
    }

    public function getPatchPath(string $package, string $description): ?string
    {
        $patches = $this->getPatches();

        $path = is_array($patches[$package] ?? null) ? ($patches[$package][$description] ?? null) : null;

        return is_string($path) ? $path : null;
    }

    /**
     * Returns patches-sources: package => description => source URL.
     *
     * @return array<string, array<string, string>>
     */
    public function getSources(): array
    {
        $sources = $this->patchNode()[self::SOURCES_KEY] ?? [];

        if (!is_array($sources)) {
            return [];
        }

        $valid = [];

        foreach ($sources as $package => $packageSources) {
            if (!is_string($package) || !is_array($packageSources)) {
                continue;
            }

            foreach ($packageSources as $description => $url) {
                if (is_string($description) && is_string($url)) {
                    $valid[$package][$description] = $url;
                }
            }
        }

        return $valid;
    }

    /**
     * @param array<string, mixed> $patches
     */
    private function setPatch(array $patches, string $package, string $description, string $path, ?string $sourceUrl = null): void
    {
        if (!isset($patches[$package]) || !is_array($patches[$package])) {
            $patches[$package] = [];
        }

        $patches[$package][$description] = $path;
        $nodes = ['patches' => $patches];

        if ($sourceUrl !== null) {
            $sources = $this->getSources();
            $sources[$package][$description] = $sourceUrl;
            $nodes[self::SOURCES_KEY] = $sources;
        }

        $this->writeNodes($nodes);
    }

    /**
     * The object holding "patches": "extra" in composer.json, the root of a patches file.
     *
     * @return array<string, mixed>
     */
    private function patchNode(): array
    {
        $data = $this->readData();

        if ($this->patchesAtRoot) {
            return $data;
        }

        $extra = $data['extra'] ?? [];

        return is_array($extra) ? $extra : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function readData(): array
    {
        $data = json_decode($this->readContents(), true);

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('The file %s does not contain valid JSON.', $this->composerJsonPath));
        }

        return $data;
    }

    private function readContents(): string
    {
        $contents = @file_get_contents($this->composerJsonPath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read %s.', $this->composerJsonPath));
        }

        return $contents;
    }

    /**
     * Writes the given nodes while leaving the rest of the file untouched.
     *
     * @param array<string, array<string, mixed>> $nodes
     */
    private function writeNodes(array $nodes): void
    {
        $contents = $this->readContents();
        $manipulator = new JsonManipulator($contents);
        $manipulated = true;

        foreach ($nodes as $name => $value) {
            $manipulated = $manipulated && ($this->patchesAtRoot
                ? $manipulator->addMainKey($name, $value)
                : $manipulator->addSubNode('extra', $name, $value));
        }

        if ($manipulated) {
            $this->writeContents($manipulator->getContents());

            return;
        }

        // The manipulator could not match the file; re-encode it while keeping
        // empty objects as objects.
        $data = json_decode($contents);

        if (!$data instanceof stdClass) {
            throw new RuntimeException(sprintf('The file %s does not contain valid JSON.', $this->composerJsonPath));
        }

        if (!$this->patchesAtRoot && (!isset($data->extra) || !$data->extra instanceof stdClass)) {
            $data->extra = new stdClass();
        }

        $target = $this->patchesAtRoot ? $data : $data->extra;

        foreach ($nodes as $name => $value) {
            $target->{$name} = $value;
        }

        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";

        $this->writeContents(str_replace("\n", $newline, JsonFile::encode($data)) . $newline);
    }

    private function writeContents(string $contents): void
    {
        if (file_put_contents($this->composerJsonPath, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write %s.', $this->composerJsonPath));
        }
    }
}
