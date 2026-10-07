<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Unit\Command;

use Symfony\Component\Console\Command\Command;

final class MigratePatchesCommandTest extends CommandTestCase
{
    public function testItKeepsPatchesWithTheSameFilenameApart(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => [
                'One' => $this->patchUrl('one/fix.patch'),
                'Two' => $this->patchUrl('two/fix.patch'),
            ]]],
        ]);

        $tester = $this->migrateCommand();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(
            [
                'One' => 'resources/patch/drupal/core/fix.patch',
                'Two' => 'resources/patch/drupal/core/fix-2.patch',
            ],
            $this->patchesFor('drupal/core')
        );
        self::assertStringContainsString('+one', (string) file_get_contents($this->projectFile('resources/patch/drupal/core/fix.patch')));
        self::assertStringContainsString('+two', (string) file_get_contents($this->projectFile('resources/patch/drupal/core/fix-2.patch')));
    }

    public function testItRecordsSourcesOnlyForMigratedPatches(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => [
                'Good' => $this->patchUrl('example.patch'),
                'Missing' => $this->patchUrl('missing.patch'),
                'Local' => 'patches/local.patch',
            ]]],
        ]);

        $this->migrateCommand()->execute([]);

        self::assertSame(['Good' => $this->patchUrl('example.patch')], $this->sourcesFor('drupal/core'));
    }

    public function testItKeepsAFreshLockFreshAfterMigrating(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => ['Fix' => $this->patchUrl('example.patch')]]],
        ]);
        $this->writeLock();

        $tester = $this->migrateCommand();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertTrue($this->lockIsFresh());
        self::assertStringContainsString('composer.lock hash updated.', $tester->getDisplay());
    }

    public function testItDoesNotTouchTheLockWhenNothingWasMigrated(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => ['Missing' => $this->patchUrl('missing.patch')]]],
        ]);
        $this->writeLock('0123456789abcdef0123456789abcdef');
        $lockBefore = file_get_contents($this->lockPath());

        $tester = $this->migrateCommand();
        $tester->execute([]);

        self::assertSame($lockBefore, file_get_contents($this->lockPath()));
        self::assertStringNotContainsString('composer.lock', $tester->getDisplay());
    }

    public function testItLeavesLocalAndNonHttpEntriesUntouched(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => [
                'Local' => 'patches/local.patch',
                'File scheme' => 'file:///tmp/local.patch',
            ]]],
        ]);

        $tester = $this->migrateCommand();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('No remote patches found.', $tester->getDisplay());
        self::assertSame(
            ['Local' => 'patches/local.patch', 'File scheme' => 'file:///tmp/local.patch'],
            $this->patchesFor('drupal/core')
        );
    }

    public function testItSkipsUnsupportedPatchDefinitionsAndMigratesTheRest(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => [
                'drupal/core' => [$this->patchUrl('one/fix.patch')],
                'drupal/token' => ['Expanded' => ['url' => $this->patchUrl('two/fix.patch')]],
                'drupal/views' => 'not-a-map',
                'drupal/pathauto' => ['Fix' => $this->patchUrl('example.patch')],
            ]],
        ]);

        $tester = $this->migrateCommand();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('Skipped drupal/core', $display);
        self::assertStringContainsString('Skipped drupal/token', $display);
        self::assertStringContainsString('Skipped drupal/views', $display);
        self::assertStringContainsString('Migrated 1 remote patch(es).', $display);

        $patches = $this->readComposerJson()['extra']['patches'];
        self::assertSame([$this->patchUrl('one/fix.patch')], $patches['drupal/core']);
        self::assertSame(['Expanded' => ['url' => $this->patchUrl('two/fix.patch')]], $patches['drupal/token']);
        self::assertSame('not-a-map', $patches['drupal/views']);
        self::assertSame(['Fix' => 'resources/patch/drupal/pathauto/example.patch'], $patches['drupal/pathauto']);
    }

    public function testItContinuesAfterAFailedDownloadAndReportsFailure(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => [
                'Missing' => $this->patchUrl('missing.patch'),
                'Not a patch' => $this->patchUrl('not-a-patch.html'),
                'Good' => $this->patchUrl('example.patch'),
            ]]],
        ]);

        $tester = $this->migrateCommand();
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Failed drupal/core: Missing', $display);
        self::assertStringContainsString('Failed drupal/core: Not a patch', $display);
        self::assertStringContainsString('Migrated 1 remote patch(es), 2 failed.', $display);
        self::assertSame(
            [
                'Missing' => $this->patchUrl('missing.patch'),
                'Not a patch' => $this->patchUrl('not-a-patch.html'),
                'Good' => 'resources/patch/drupal/core/example.patch',
            ],
            $this->patchesFor('drupal/core')
        );
    }

    public function testItHandlesProjectsWithoutPatches(): void
    {
        $this->writeComposerJson(['name' => 'example/project']);

        $tester = $this->migrateCommand();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('No remote patches found.', $tester->getDisplay());
        self::assertSame(['name' => 'example/project'], $this->readComposerJson());
    }
}
