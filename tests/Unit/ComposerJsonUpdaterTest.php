<?php

declare(strict_types=1);

namespace PatchManager\Tests\Unit;

use PatchManager\Composer\ComposerJsonUpdater;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function testItRecordsSourcesNextToPatches(): void
    {
        $composerJsonPath = $this->copyFixture('composer.with-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $updater->replacePatch('drupal/core', 'New', 'patches/new.patch', 'https://example.com/new.patch');

        self::assertSame(['drupal/core' => ['New' => 'https://example.com/new.patch']], $updater->getSources());
        self::assertSame('patches/new.patch', $updater->getPatchPath('drupal/core', 'New'));

        $contents = file_get_contents($composerJsonPath);
        self::assertIsString($contents);
        self::assertStringContainsString('"custom-config": {', $contents);
    }

    public function testReplacingWithoutASourceKeepsTheRecordedSource(): void
    {
        $composerJsonPath = $this->copyFixture('composer.with-patches.json');
        $updater = new ComposerJsonUpdater($composerJsonPath);
        $updater->replacePatch('drupal/core', 'New', 'patches/new.patch', 'https://example.com/new.patch');

        $updater->replacePatch('drupal/core', 'New', 'patches/moved.patch');

        self::assertSame(['drupal/core' => ['New' => 'https://example.com/new.patch']], $updater->getSources());
    }

    public function testItIgnoresMalformedSources(): void
    {
        $composerJsonPath = $this->workspace . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($composerJsonPath, (string) json_encode([
            'extra' => ['patches-sources' => [
                'drupal/core' => ['Valid' => 'https://example.com/a.patch', 'Invalid' => ['url' => 'x']],
                'drupal/token' => 'not-a-map',
            ]],
        ]));

        self::assertSame(
            ['drupal/core' => ['Valid' => 'https://example.com/a.patch']],
            (new ComposerJsonUpdater($composerJsonPath))->getSources()
        );
    }

    public function testItReturnsNoSourcesWhenNoneAreRecorded(): void
    {
        $updater = new ComposerJsonUpdater($this->copyFixture('composer.with-patches.json'));

        self::assertSame([], $updater->getSources());
    }

    public function testItPreservesFormattingOfUnrelatedContent(): void
    {
        $composerJsonPath = $this->workspace . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents(
            $composerJsonPath,
            "{\n  \"name\": \"example/project\",\n  \"description\": \"Ünïcode café\",\n  \"autoload\": {},\n  \"require\": {},\n  \"extra\": {\n    \"custom\": {}\n  }\n}\n"
        );
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $updater->replacePatch('drupal/core', 'Fix ✓', 'patches/drupal/core/fix.patch');

        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);
        self::assertStringContainsString("\n  \"description\": \"Ünïcode café\",\n", $contents);
        self::assertStringContainsString("\n  \"autoload\": {},\n", $contents);
        self::assertStringContainsString("\n  \"require\": {},\n", $contents);
        self::assertStringContainsString("\n    \"custom\": {},\n", $contents);
        self::assertStringContainsString('"Fix ✓": "patches/drupal/core/fix.patch"', $contents);
        self::assertStringEndsWith("}\n", $contents);
    }

    public function testItPreservesWindowsLineEndings(): void
    {
        $composerJsonPath = $this->workspace . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($composerJsonPath, "{\r\n    \"name\": \"example/project\"\r\n}\r\n");
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $updater->replacePatch('drupal/core', 'Fix', 'patches/fix.patch');

        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);
        self::assertStringEndsWith("}\r\n", $contents);
        self::assertSame(
            ['drupal/core' => ['Fix' => 'patches/fix.patch']],
            $updater->getPatches()
        );
    }

    public function testItFailsClearlyOnInvalidJson(): void
    {
        $composerJsonPath = $this->workspace . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($composerJsonPath, '{"name": ');
        $updater = new ComposerJsonUpdater($composerJsonPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not contain valid JSON');

        $updater->replacePatch('drupal/core', 'Fix', 'patches/fix.patch');
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
