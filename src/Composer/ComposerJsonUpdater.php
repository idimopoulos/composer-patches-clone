<?php

declare(strict_types=1);

namespace PatchManager\Composer;

use RuntimeException;

final class ComposerJsonUpdater
{
    public function __construct(
        private readonly string $composerJsonPath
    ) {
    }

    public function addPatch(string $package, string $description, string $path): void
    {
        $data = $this->readData();

        if (!isset($data['extra']) || !is_array($data['extra'])) {
            $data['extra'] = [];
        }

        if (!isset($data['extra']['patches']) || !is_array($data['extra']['patches'])) {
            $data['extra']['patches'] = [];
        }

        if (!isset($data['extra']['patches'][$package]) || !is_array($data['extra']['patches'][$package])) {
            $data['extra']['patches'][$package] = [];
        }

        if (isset($data['extra']['patches'][$package][$description])) {
            return;
        }

        $data['extra']['patches'][$package][$description] = $path;

        $this->writeData($data);
    }

    /**
     * @return array<string, array<string, string>>
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
        $data = $this->readData();

        if (!isset($data['extra']) || !is_array($data['extra'])) {
            $data['extra'] = [];
        }

        if (!isset($data['extra']['patches']) || !is_array($data['extra']['patches'])) {
            $data['extra']['patches'] = [];
        }

        if (!isset($data['extra']['patches'][$package]) || !is_array($data['extra']['patches'][$package])) {
            $data['extra']['patches'][$package] = [];
        }

        $data['extra']['patches'][$package][$description] = $path;

        $this->writeData($data);
    }

    public function getPatchPath(string $package, string $description): ?string
    {
        $patches = $this->getPatches();

        $path = $patches[$package][$description] ?? null;

        return is_string($path) ? $path : null;
    }

    private function readData(): array
    {
        $contents = file_get_contents($this->composerJsonPath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read %s.', $this->composerJsonPath));
        }

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('The file %s does not contain valid JSON.', $this->composerJsonPath));
        }

        return $data;
    }

    private function writeData(array $data): void
    {
        $contents = file_get_contents($this->composerJsonPath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read %s.', $this->composerJsonPath));
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException(sprintf('Unable to encode %s.', $this->composerJsonPath));
        }

        $newline = str_ends_with($contents, "\r\n") ? "\r\n" : "\n";

        if (file_put_contents($this->composerJsonPath, $encoded . $newline) === false) {
            throw new RuntimeException(sprintf('Unable to write %s.', $this->composerJsonPath));
        }
    }
}
