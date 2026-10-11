<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

final class PatchMigrateTest extends ComposerTestCase
{
    public function testMigrateConvertsRemotePatchesToLocalFiles(): void
    {
        $this->startPatchServer();
        $this->seedRemotePatch();

        $output = $this->runComposer('patches:migrate');

        self::assertStringContainsString('Migrated 1 remote patch(es).', $output);
        self::assertFileExists(
            $this->workingDirectory
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'patch'
            . DIRECTORY_SEPARATOR
            . 'drupal'
            . DIRECTORY_SEPARATOR
            . 'core'
            . DIRECTORY_SEPARATOR
            . 'example.patch'
        );

        $composerJson = file_get_contents($this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json');

        self::assertIsString($composerJson);
        self::assertStringContainsString(
            '"Example patch": "resources/patch/drupal/core/example.patch"',
            $composerJson
        );

        $data = json_decode($composerJson, true);
        self::assertIsArray($data);
        self::assertSame(
            ['Example patch' => $this->patchUrl('example.patch')],
            $data['extra']['patches-sources']['drupal/core']
        );
    }

    private function seedRemotePatch(): void
    {
        $composerJsonPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);

        $data = json_decode($contents, true);

        self::assertIsArray($data);

        $data['extra']['patches']['drupal/core']['Example patch'] = $this->patchUrl('example.patch');

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        self::assertNotFalse($encoded);

        file_put_contents($composerJsonPath, $encoded . "\n");
    }
}
