<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Unit\Command;

use InvalidArgumentException;
use RuntimeException;

final class ClonePatchCommandTest extends CommandTestCase
{
    public function testItReplacesAnExistingRemoteEntryWithTheLocalPath(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => ['Fix' => 'https://example.com/old/fix.patch']]],
        ]);

        $tester = $this->cloneCommand();
        $tester->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('example.patch'),
            '--description' => 'Fix',
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(
            ['Fix' => 'resources/patch/drupal/core/example.patch'],
            $this->patchesFor('drupal/core')
        );
        self::assertFileExists($this->projectFile('resources/patch/drupal/core/example.patch'));
    }

    public function testItRefreshesTheFileOfAnExistingLocalEntry(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => ['Fix' => 'patches/core/fix.patch']]],
        ]);
        mkdir($this->projectFile('patches/core'), 0777, true);
        file_put_contents($this->projectFile('patches/core/fix.patch'), "stale\n");

        $tester = $this->cloneCommand();
        $tester->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('one/fix.patch'),
            '--description' => 'Fix',
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(['Fix' => 'patches/core/fix.patch'], $this->patchesFor('drupal/core'));
        self::assertStringEqualsFile(
            $this->projectFile('patches/core/fix.patch'),
            (string) file_get_contents($this->fixture('one/fix.patch'))
        );
    }

    public function testItRecordsTheSourceUrl(): void
    {
        $this->writeComposerJson([]);

        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('one/fix.patch'),
            '--description' => 'Fix',
        ]);

        self::assertSame(['Fix' => $this->patchUrl('one/fix.patch')], $this->sourcesFor('drupal/core'));
    }

    public function testRecloningFromANewUrlUpdatesTheSourceAndKeepsOthers(): void
    {
        $this->writeComposerJson([
            'extra' => [
                'patches' => ['drupal/core' => [
                    'Fix' => 'resources/patch/drupal/core/fix.patch',
                    'Other' => 'resources/patch/drupal/core/other.patch',
                ]],
                'patches-sources' => ['drupal/core' => [
                    'Fix' => 'https://example.com/old.patch',
                    'Other' => 'https://example.com/other.patch',
                ]],
            ],
        ]);

        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('two/fix.patch'),
            '--description' => 'Fix',
        ]);

        self::assertSame(
            ['Fix' => $this->patchUrl('two/fix.patch'), 'Other' => 'https://example.com/other.patch'],
            $this->sourcesFor('drupal/core')
        );
        self::assertSame('resources/patch/drupal/core/fix.patch', $this->patchesFor('drupal/core')['Fix']);
    }

    public function testItKeepsAFreshLockFresh(): void
    {
        $this->writeComposerJson(['require' => ['drupal/core' => '^11']]);
        $this->writeLock();

        $tester = $this->cloneCommand();
        $tester->execute(['package' => 'drupal/core', 'url' => $this->patchUrl('example.patch')]);

        $tester->assertCommandIsSuccessful();
        self::assertTrue($this->lockIsFresh());
        self::assertStringContainsString('composer.lock hash updated.', $tester->getDisplay());
    }

    public function testItLeavesAStaleLockStaleAndSaysSo(): void
    {
        $this->writeComposerJson(['require' => ['drupal/core' => '^11']]);
        $this->writeLock('0123456789abcdef0123456789abcdef');

        $tester = $this->cloneCommand();
        $tester->execute(['package' => 'drupal/core', 'url' => $this->patchUrl('example.patch')]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('0123456789abcdef0123456789abcdef', (string) file_get_contents($this->lockPath()));
        self::assertStringContainsString('composer.lock was already out of date', $tester->getDisplay());
    }

    public function testItWorksWithoutALock(): void
    {
        $this->writeComposerJson([]);

        $tester = $this->cloneCommand();
        $tester->execute(['package' => 'drupal/core', 'url' => $this->patchUrl('example.patch')]);

        $tester->assertCommandIsSuccessful();
        self::assertFileDoesNotExist($this->lockPath());
        self::assertStringNotContainsString('composer.lock', $tester->getDisplay());
    }

    public function testItAddsNewPatchesToThePatchesFile(): void
    {
        $this->writeComposerJson(['require' => ['drupal/core' => '^11'], 'extra' => ['patches-file' => 'composer.patches.json']]);
        file_put_contents('composer.patches.json', "{\n    \"patches\": {}\n}\n");
        $this->writeLock();
        $composerJsonBefore = file_get_contents($this->composerJsonPath());
        $lockBefore = file_get_contents($this->lockPath());

        $tester = $this->cloneCommand();
        $tester->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('example.patch'),
            '--description' => 'Fix',
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('composer.patches.json updated', $tester->getDisplay());
        self::assertSame($composerJsonBefore, file_get_contents($this->composerJsonPath()));
        self::assertSame($lockBefore, file_get_contents($this->lockPath()));

        $patchesFile = json_decode((string) file_get_contents('composer.patches.json'), true);
        self::assertIsArray($patchesFile);
        self::assertSame(['Fix' => 'resources/patch/drupal/core/example.patch'], $patchesFile['patches']['drupal/core']);
        self::assertSame(['Fix' => $this->patchUrl('example.patch')], $patchesFile['patches-sources']['drupal/core']);
    }

    public function testItUpdatesAPatchWhereItIsDefined(): void
    {
        $this->writeComposerJson(['extra' => [
            'patches-file' => 'composer.patches.json',
            'patches' => ['drupal/token' => ['Other' => 'patches/other.patch']],
        ]]);
        file_put_contents('composer.patches.json', (string) json_encode([
            'patches' => ['drupal/core' => ['Fix' => 'https://example.com/old.patch']],
        ]));

        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('example.patch'),
            '--description' => 'Fix',
        ]);

        $patchesFile = json_decode((string) file_get_contents('composer.patches.json'), true);
        self::assertIsArray($patchesFile);
        self::assertSame(['Fix' => 'resources/patch/drupal/core/example.patch'], $patchesFile['patches']['drupal/core']);
        self::assertSame(['drupal/token' => ['Other' => 'patches/other.patch']], $this->readComposerJson()['extra']['patches']);
    }

    public function testItRefusesToEditTheExpandedFormatAndChangesNothing(): void
    {
        $this->writeComposerJson(['extra' => ['patches-file' => 'composer.patches.json']]);
        $patchesFile = (string) json_encode([
            'patches' => ['drupal/core' => [['description' => 'Existing', 'url' => 'https://example.com/a.patch']]],
        ]);
        file_put_contents('composer.patches.json', $patchesFile);

        try {
            $this->cloneCommand()->execute([
                'package' => 'drupal/core',
                'url' => $this->patchUrl('example.patch'),
                '--description' => 'New',
            ]);
            self::fail('Expected the expanded format to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('expanded patch format', $exception->getMessage());
        }

        self::assertSame($patchesFile, file_get_contents('composer.patches.json'));
        self::assertFileDoesNotExist($this->projectFile('resources/patch/drupal/core/example.patch'));
    }

    public function testItTellsComposerPatches2UsersToRelock(): void
    {
        $this->writeComposerJson([]);
        file_put_contents('patches.lock.json', "{}\n");

        $tester = $this->cloneCommand();
        $tester->execute(['package' => 'drupal/core', 'url' => $this->patchUrl('example.patch')]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('composer patches-relock', $tester->getDisplay());
        self::assertStringContainsString('composer patches-repatch', $tester->getDisplay());
    }

    public function testItDoesNotMentionRelockingWithoutAPatchesLock(): void
    {
        $this->writeComposerJson([]);

        $tester = $this->cloneCommand();
        $tester->execute(['package' => 'drupal/core', 'url' => $this->patchUrl('example.patch')]);

        self::assertStringNotContainsString('patches-relock', $tester->getDisplay());
    }

    public function testItDoesNotOverwriteAnotherPatchWithTheSameFilename(): void
    {
        $this->writeComposerJson([]);

        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('one/fix.patch'),
            '--description' => 'One',
        ]);
        $tester = $this->cloneCommand();
        $tester->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('two/fix.patch'),
            '--description' => 'Two',
        ]);

        $tester->assertCommandIsSuccessful();
        $patches = $this->patchesFor('drupal/core');
        self::assertSame('resources/patch/drupal/core/fix.patch', $patches['One']);
        self::assertSame('resources/patch/drupal/core/fix-2.patch', $patches['Two']);
        self::assertStringEqualsFile(
            $this->projectFile($patches['One']),
            (string) file_get_contents($this->fixture('one/fix.patch'))
        );
        self::assertStringEqualsFile(
            $this->projectFile($patches['Two']),
            (string) file_get_contents($this->fixture('two/fix.patch'))
        );
    }

    public function testItReusesAnIdenticalExistingFile(): void
    {
        $this->writeComposerJson([]);

        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('one/fix.patch'),
            '--description' => 'One',
        ]);
        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('one/fix.patch'),
            '--description' => 'Same patch again',
        ]);

        $patches = $this->patchesFor('drupal/core');
        self::assertSame($patches['One'], $patches['Same patch again']);
        self::assertFileDoesNotExist($this->projectFile('resources/patch/drupal/core/fix-2.patch'));
    }

    public function testItIgnoresTheQueryStringWhenNamingTheFile(): void
    {
        $this->writeComposerJson([]);

        $tester = $this->cloneCommand();
        $tester->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('example.patch?download=1'),
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(
            ['example.patch' => 'resources/patch/drupal/core/example.patch'],
            $this->patchesFor('drupal/core')
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafePatchNames(): array
    {
        return [
            'parent directory' => ['../../../../escaped'],
            'nested directory' => ['nested/escaped'],
            'windows separator' => ['..\\escaped'],
            'dot dot' => ['..'],
        ];
    }

    /**
     * @dataProvider unsafePatchNames
     */
    public function testItRejectsPatchNamesThatAreNotPlainFilenames(string $patchName): void
    {
        $this->writeComposerJson([]);

        try {
            $this->cloneCommand()->execute([
                'package' => 'drupal/core',
                'url' => $this->patchUrl('example.patch'),
                '--patch-name' => $patchName,
            ]);
            self::fail('Expected the patch name to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Patch name', $exception->getMessage());
        }

        self::assertFileDoesNotExist($this->projectFile('escaped.patch'));
        self::assertArrayNotHasKey('extra', $this->readComposerJson());
    }

    public function testItRejectsBasePathsThatLeaveTheProject(): void
    {
        $this->writeComposerJson([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Base path');

        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('example.patch'),
            '--base-path' => 'patches/../../outside',
        ]);
    }

    public function testItRejectsContentThatIsNotAPatch(): void
    {
        $this->writeComposerJson([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a valid patch');

        $this->cloneCommand()->execute([
            'package' => 'drupal/core',
            'url' => $this->patchUrl('not-a-patch.html'),
        ]);
    }

    public function testItFailsOnMissingPatchWithoutTouchingComposerJson(): void
    {
        $this->writeComposerJson([]);

        try {
            $this->cloneCommand()->execute([
                'package' => 'drupal/core',
                'url' => $this->patchUrl('missing.patch'),
            ]);
            self::fail('Expected the download to fail.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Unable to download patch', $exception->getMessage());
        }

        self::assertArrayNotHasKey('extra', $this->readComposerJson());
    }

    private function fixture(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'patches'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
