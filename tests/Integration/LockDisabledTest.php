<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

use Composer\Package\Locker;

final class LockDisabledTest extends ComposerTestCase
{
    public function testALeftoverLockIsNotTouchedWhenTheLockFileIsDisabled(): void
    {
        $this->startPatchServer();
        $composerJsonPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $lockPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock';

        $data = json_decode((string) file_get_contents($composerJsonPath), true);
        self::assertIsArray($data);
        $data['config']['lock'] = false;
        file_put_contents($composerJsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        // A leftover lock that would look fresh if it were still in use.
        $lock = json_decode((string) file_get_contents($lockPath), true);
        self::assertIsArray($lock);
        $lock['content-hash'] = Locker::getContentHash((string) file_get_contents($composerJsonPath));
        file_put_contents($lockPath, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $lockBefore = file_get_contents($lockPath);

        $output = $this->runComposer(
            'patches:clone drupal/core ' . $this->patchUrl('example.patch') . ' --description="Example patch"'
        );

        self::assertSame($lockBefore, file_get_contents($lockPath));
        // Newer Composer versions print their own "ignored" warning, so only
        // check that this plugin said nothing about the lock.
        self::assertStringNotContainsString('hash updated', $output);
        self::assertStringNotContainsString('was already out of date', $output);
    }
}
