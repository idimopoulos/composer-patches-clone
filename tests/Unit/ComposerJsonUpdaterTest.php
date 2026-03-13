<?php

declare(strict_types=1);

namespace PatchManager\Tests\Unit;

use PatchManager\Composer\ComposerJsonUpdater;
use PHPUnit\Framework\TestCase;

final class ComposerJsonUpdaterTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'composer-json-updater-' . uniqid('', true);
        mkdir($this->workspace, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);
    }

    public function testItCreatesMissingPatchSectionsAutomatically(): void
    {
        $composerJsonPath = $this->copyFixture('composer.without-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $updater->addPatch('drupal/core', 'Description', 'patches/drupal/core/file.patch');

        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);
        self::assertStringContainsString('"patches": {', $contents);
        self::assertStringContainsString('"drupal/core": {', $contents);
        self::assertStringContainsString('"Description": "patches/drupal/core/file.patch"', $contents);
        self::assertStringContainsString('"custom-config": {', $contents);
    }

    public function testItPreservesExistingPatches(): void
    {
        $composerJsonPath = $this->copyFixture('composer.with-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $updater->addPatch('drupal/core', 'New patch', 'patches/drupal/core/new.patch');

        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);
        self::assertStringContainsString('"Existing patch": "patches/drupal/core/existing.patch"', $contents);
        self::assertStringContainsString('"New patch": "patches/drupal/core/new.patch"', $contents);
    }

    public function testItDoesNotOverwriteExistingPatchDescriptions(): void
    {
        $composerJsonPath = $this->copyFixture('composer.with-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $updater->addPatch('drupal/core', 'Existing patch', 'patches/drupal/core/replacement.patch');

        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);
        self::assertStringContainsString('"Existing patch": "patches/drupal/core/existing.patch"', $contents);
        self::assertStringNotContainsString('"replacement.patch"', $contents);
    }

    public function testItReturnsConfiguredPatches(): void
    {
        $composerJsonPath = $this->copyFixture('composer.with-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        self::assertSame(
            [
                'drupal/core' => [
                    'Existing patch' => 'patches/drupal/core/existing.patch',
                ],
            ],
            $updater->getPatches()
        );
    }

    public function testItCanReplaceExistingPatchPaths(): void
    {
        $composerJsonPath = $this->copyFixture('composer.with-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $updater->replacePatch('drupal/core', 'Existing patch', 'patches/drupal/core/local.patch');

        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);
        self::assertStringContainsString('"Existing patch": "patches/drupal/core/local.patch"', $contents);
        self::assertStringNotContainsString('"existing.patch"', $contents);
    }

    public function testItCanReturnExistingPatchPath(): void
    {
        $composerJsonPath = $this->copyFixture('composer.with-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        self::assertSame(
            'patches/drupal/core/existing.patch',
            $updater->getPatchPath('drupal/core', 'Existing patch')
        );
    }

    private function copyFixture(string $fixture): string
    {
        $source = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . $fixture;
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'composer.json';

        copy($source, $destination);

        return $destination;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
