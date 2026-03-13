<?php

declare(strict_types=1);

namespace PatchManager\Tests\Integration;

final class CloneCommandOptionsTest extends ComposerTestCase
{
    public function testCloneSupportsBasePathAndPatchName(): void
    {
        $this->startPatchServer();

        $this->runComposer(
            'patches:clone drupal/core http://localhost:8123/example.patch --description="Example patch" --base-path="/resources/patch/drupal" --patch-name="custom-name"'
        );

        self::assertFileExists(
            $this->workingDirectory
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'patch'
            . DIRECTORY_SEPARATOR
            . 'drupal'
            . DIRECTORY_SEPARATOR
            . 'drupal'
            . DIRECTORY_SEPARATOR
            . 'core'
            . DIRECTORY_SEPARATOR
            . 'custom-name.patch'
        );

        $composerJson = file_get_contents($this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json');

        self::assertIsString($composerJson);
        self::assertStringContainsString(
            '"Example patch": "resources/patch/drupal/drupal/core/custom-name.patch"',
            $composerJson
        );
    }

    public function testClonePreservesExistingCustomLocalPath(): void
    {
        $this->startPatchServer();
        $composerJsonPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $contents = file_get_contents($composerJsonPath);
        self::assertIsString($contents);
        $data = json_decode($contents, true);
        self::assertIsArray($data);
        $data['extra']['patches']['drupal/core']['Example patch'] = 'resources/patch/custom/core/my-fix.patch';
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        self::assertNotFalse($encoded);
        file_put_contents($composerJsonPath, $encoded . "\n");

        $this->runComposer(
            'patches:clone drupal/core http://localhost:8123/example.patch --description="Example patch"'
        );

        self::assertFileExists(
            $this->workingDirectory
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'patch'
            . DIRECTORY_SEPARATOR
            . 'custom'
            . DIRECTORY_SEPARATOR
            . 'core'
            . DIRECTORY_SEPARATOR
            . 'my-fix.patch'
        );

        $composerJson = file_get_contents($composerJsonPath);
        self::assertIsString($composerJson);
        self::assertStringContainsString(
            '"Example patch": "resources/patch/custom/core/my-fix.patch"',
            $composerJson
        );
    }
}
