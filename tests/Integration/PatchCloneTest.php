<?php

declare(strict_types=1);

namespace PatchManager\Tests\Integration;

final class PatchCloneTest extends ComposerTestCase
{
    public function testPatchCloneDownloadsWritesAndRegistersThePatch(): void
    {
        $this->startPatchServer();

        $this->runComposer(
            'patches:clone drupal/core ' . $this->patchUrl('example.patch') . ' --description="Example patch"'
        );

        $patchPath = $this->workingDirectory
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'patch'
            . DIRECTORY_SEPARATOR
            . 'drupal'
            . DIRECTORY_SEPARATOR
            . 'core'
            . DIRECTORY_SEPARATOR
            . 'example.patch';

        self::assertFileExists($patchPath);

        $composerJson = file_get_contents($this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json');

        self::assertIsString($composerJson);
        self::assertStringContainsString('"patches": {', $composerJson);
        self::assertStringContainsString('"drupal/core": {', $composerJson);
        self::assertStringContainsString(
            '"Example patch": "resources/patch/drupal/core/example.patch"',
            $composerJson
        );
    }

    public function testPatchCloneUsesComposerAuthentication(): void
    {
        $this->startPatchServer();
        $url = $this->patchUrl('private/secret.patch');
        $origin = parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT);
        $auth = json_encode(['http-basic' => [$origin => ['username' => 'user', 'password' => 'secret']]]);
        self::assertIsString($auth);

        $this->runComposer('patches:clone drupal/core ' . $url . ' --description="Private patch"', ['COMPOSER_AUTH' => $auth]);

        self::assertFileExists(
            $this->workingDirectory . DIRECTORY_SEPARATOR . 'resources/patch/drupal/core/secret.patch'
        );
    }
}
