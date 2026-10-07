<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

use Composer\Package\Locker;

final class CustomComposerFileTest extends ComposerTestCase
{
    public function testCommandsUseTheFileNamedByTheComposerVariable(): void
    {
        $this->startPatchServer();
        $defaultPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $customPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'custom.json';
        $defaultLock = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock';
        $customLock = $this->workingDirectory . DIRECTORY_SEPARATOR . 'custom.lock';
        copy($defaultPath, $customPath);
        copy($defaultLock, $customLock);
        $defaultBefore = file_get_contents($defaultPath);
        $defaultLockBefore = file_get_contents($defaultLock);
        $env = ['COMPOSER' => 'custom.json'];

        $this->runComposer(
            'patches:clone drupal/core ' . $this->patchUrl('example.patch') . ' --description="Example patch"',
            $env
        );

        self::assertSame($defaultBefore, file_get_contents($defaultPath));
        self::assertSame($defaultLockBefore, file_get_contents($defaultLock));

        $lock = json_decode((string) file_get_contents($customLock), true);
        self::assertIsArray($lock);
        self::assertSame(Locker::getContentHash((string) file_get_contents($customPath)), $lock['content-hash']);

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
