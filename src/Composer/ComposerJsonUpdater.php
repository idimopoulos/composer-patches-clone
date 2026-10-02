<?php

declare(strict_types=1);

namespace PatchManager\Composer;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use RuntimeException;
use stdClass;

final class ComposerJsonUpdater
{
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

    public function replacePatch(string $package, string $description, string $path): void
    {
        $this->setPatch($this->getPatches(), $package, $description, $path);
    }

    public function getPatchPath(string $package, string $description): ?string
    {
        $patches = $this->getPatches();

        $path = is_array($patches[$package] ?? null) ? ($patches[$package][$description] ?? null) : null;

        return is_string($path) ? $path : null;
    }

    /**
     * @param array<string, mixed> $patches
     */
    private function setPatch(array $patches, string $package, string $description, string $path): void
    {
        if (!isset($patches[$package]) || !is_array($patches[$package])) {
            $patches[$package] = [];
        }

        $patches[$package][$description] = $path;

        $this->writePatches($patches);
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
     * Writes extra.patches while leaving the rest of the file untouched.
     *
     * @param array<string, mixed> $patches
     */
    private function writePatches(array $patches): void
    {
        $contents = $this->readContents();
        $manipulator = new JsonManipulator($contents);

        if ($manipulator->addSubNode('extra', 'patches', $patches)) {
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

        $data->extra->patches = $patches;
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
