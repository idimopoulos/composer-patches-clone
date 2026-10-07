<?php

declare(strict_types=1);

namespace PatchManager\Tests\Integration;

final class CustomComposerFileTest extends ComposerTestCase
{
    public function testCommandsUseTheFileNamedByTheComposerVariable(): void
    {
        $this->startPatchServer();
        $defaultPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $customPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'custom.json';
        copy($defaultPath, $customPath);
        $defaultBefore = file_get_contents($defaultPath);
        $env = ['COMPOSER' => 'custom.json'];

        $this->runComposer(
            'patches:clone drupal/core ' . $this->patchUrl('example.patch') . ' --description="Example patch"',
            $env
        );

        self::assertSame($defaultBefore, file_get_contents($defaultPath));

        $custom = json_decode((string) file_get_contents($customPath), true);
        self::assertIsArray($custom);
        self::assertSame(
            ['Example patch' => 'resources/patch/drupal/core/example.patch'],
            $custom['extra']['patches']['drupal/core']
        );

        $output = $this->runComposer('patches:list', $env);
        self::assertStringContainsString('Example patch', $output);
    }
}
