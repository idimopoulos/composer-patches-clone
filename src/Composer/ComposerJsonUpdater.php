<?php

declare(strict_types=1);

namespace PatchManager\Composer;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use RuntimeException;
use stdClass;

final class ComposerJsonUpdater
{
    public const SOURCES_KEY = 'patches-sources';

    public function __construct(
        private readonly string $composerJsonPath
    ) {
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
     * Returns extra.patches as written; entries are not guaranteed to be strings.
     *
     * @return array<string, mixed>
     */
    public function getPatches(): array
    {
        $data = $this->readData();

        $patches = $data['extra']['patches'] ?? [];

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
     * Returns extra.patches-sources: package => description => source URL.
     *
     * @return array<string, array<string, string>>
     */
    public function getSources(): array
    {
        $data = $this->readData();
        $sources = $data['extra'][self::SOURCES_KEY] ?? [];

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

        $this->writeExtra($nodes);
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
     * Writes the given extra.* nodes while leaving the rest of the file untouched.
     *
     * @param array<string, array<string, mixed>> $nodes
     */
    private function writeExtra(array $nodes): void
    {
        $contents = $this->readContents();
        $manipulator = new JsonManipulator($contents);
        $manipulated = true;

        foreach ($nodes as $name => $value) {
            $manipulated = $manipulated && $manipulator->addSubNode('extra', $name, $value);
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

        if (!isset($data->extra) || !$data->extra instanceof stdClass) {
            $data->extra = new stdClass();
        }

        foreach ($nodes as $name => $value) {
            $data->extra->{$name} = $value;
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
