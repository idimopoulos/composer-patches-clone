<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Unit\Command;

use InvalidArgumentException;

final class ListPatchesCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeComposerJson([
            'extra' => [
                'patches' => [
                    'drupal/core' => [
                        'Core fix' => 'resources/patch/drupal/core/fix.patch',
                        'Still remote' => 'https://example.com/remote.patch',
                    ],
                    'drupal/token' => [
                        'Hand-made' => 'patches/token.patch',
                        'Gone' => 'patches/gone.patch',
                    ],
                    'Vendor/Mixed-Case' => [
                        'Case' => 'patches/case.patch',
                    ],
                    'drupal/views' => ['https://example.com/list-format.patch'],
                ],
                'patches-sources' => [
                    'drupal/core' => ['Core fix' => 'https://www.drupal.org/files/issues/fix.patch'],
                    'drupal/pathauto' => ['Removed' => 'https://example.com/orphan.patch'],
                ],
            ],
        ]);

        foreach (['resources/patch/drupal/core/fix.patch', 'patches/token.patch', 'patches/case.patch'] as $file) {
            @mkdir(dirname($this->projectFile($file)), 0777, true);
            file_put_contents($this->projectFile($file), "--- a\n+++ b\n");
        }
    }

    public function testItListsAllPatchesWithTheirSources(): void
    {
        $tester = $this->listCommand();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();

        self::assertMatchesRegularExpression('/drupal\/core\s*\|\s*Core fix\s*\|\s*resources\/patch\/drupal\/core\/fix\.patch\s*\|\s*https:\/\/www\.drupal\.org\/files\/issues\/fix\.patch/', $display);
        self::assertMatchesRegularExpression('/Still remote\s*\|\s*\(remote\)\s*\|\s*https:\/\/example\.com\/remote\.patch/', $display);
        self::assertMatchesRegularExpression('/Hand-made\s*\|\s*patches\/token\.patch\s*\|\s*\(unknown\)/', $display);
        self::assertMatchesRegularExpression('/Gone\s*\|\s*patches\/gone\.patch \(missing\)\s*\|\s*\(unknown\)/', $display);
        self::assertStringNotContainsString('orphan.patch', $display);
        self::assertStringNotContainsString('list-format.patch', $display);
    }

    public function testItFiltersByExactPackage(): void
    {
        $rows = $this->listAsJson(['package' => 'drupal/token']);

        self::assertSame(['drupal/token', 'drupal/token'], array_column($rows, 'package'));
    }

    public function testItFiltersByWildcardCaseInsensitively(): void
    {
        self::assertSame(
            ['drupal/core', 'drupal/core', 'drupal/token', 'drupal/token'],
            array_column($this->listAsJson(['package' => 'Drupal/*']), 'package')
        );
        self::assertSame(
            ['Vendor/Mixed-Case'],
            array_column($this->listAsJson(['package' => 'vendor/mixed-case']), 'package')
        );
    }

    public function testItReportsWhenNothingMatches(): void
    {
        $tester = $this->listCommand();
        $tester->execute(['package' => 'drupal/nope']);

        $tester->assertCommandIsSuccessful();
        self::assertSame('No patches found for drupal/nope.', trim($tester->getDisplay()));
    }

    public function testItReportsWhenTheProjectHasNoPatches(): void
    {
        $this->writeComposerJson(['name' => 'example/project']);

        $tester = $this->listCommand();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame('No patches found.', trim($tester->getDisplay()));
        self::assertSame([], $this->listAsJson([]));
    }

    public function testJsonOutputContainsAllFields(): void
    {
        $rows = $this->listAsJson(['package' => 'drupal/core']);

        self::assertSame(
            [
                [
                    'package' => 'drupal/core',
                    'description' => 'Core fix',
                    'path' => 'resources/patch/drupal/core/fix.patch',
                    'source' => 'https://www.drupal.org/files/issues/fix.patch',
                    'remote' => false,
                    'exists' => true,
                    'file' => 'composer.json',
                ],
                [
                    'package' => 'drupal/core',
                    'description' => 'Still remote',
                    'path' => 'https://example.com/remote.patch',
                    'source' => 'https://example.com/remote.patch',
                    'remote' => true,
                    'exists' => true,
                    'file' => 'composer.json',
                ],
            ],
            $rows
        );
    }

    public function testItRejectsUnknownFormats(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported format "xml"');

        $this->listCommand()->execute(['--format' => 'xml']);
    }

    public function testItListsSourcesRecordedByCloneAndMigrate(): void
    {
        $this->writeComposerJson([
            'extra' => ['patches' => ['drupal/core' => ['Migrated' => $this->patchUrl('two/fix.patch')]]],
        ]);
        $this->cloneCommand()->execute([
            'package' => 'drupal/token',
            'url' => $this->patchUrl('one/fix.patch'),
            '--description' => 'Cloned',
        ]);
        $this->migrateCommand()->execute([]);

        $rows = $this->listAsJson([]);

        self::assertSame(
            [
                ['drupal/core', 'Migrated', $this->patchUrl('two/fix.patch')],
                ['drupal/token', 'Cloned', $this->patchUrl('one/fix.patch')],
            ],
            array_map(static fn (array $row): array => [$row['package'], $row['description'], $row['source']], $rows)
        );
    }

    public function testItListsPatchesFromThePatchesFile(): void
    {
        $this->writeComposerJson(['extra' => [
            'patches-file' => 'composer.patches.json',
            'patches' => ['drupal/core' => ['In composer.json' => 'resources/patch/drupal/core/fix.patch']],
            'patches-sources' => ['drupal/core' => ['In composer.json' => 'https://www.drupal.org/files/issues/fix.patch']],
        ]]);
        file_put_contents('composer.patches.json', (string) json_encode([
            'patches' => ['drupal/token' => ['In patches file' => 'patches/token.patch']],
            'patches-sources' => ['drupal/token' => ['In patches file' => 'https://example.com/token.patch']],
        ]));

        $rows = $this->listAsJson([]);

        self::assertSame(
            [
                ['drupal/core', 'In composer.json', 'composer.json', 'https://www.drupal.org/files/issues/fix.patch'],
                ['drupal/token', 'In patches file', 'composer.patches.json', 'https://example.com/token.patch'],
            ],
            array_map(static fn (array $row): array => [$row['package'], $row['description'], $row['file'], $row['source']], $rows)
        );
    }

    /**
     * @param array<string, string> $input
     *
     * @return list<array<string, mixed>>
     */
    private function listAsJson(array $input): array
    {
        $tester = $this->listCommand();
        $tester->execute($input + ['--format' => 'json']);
        $tester->assertCommandIsSuccessful();

        $rows = json_decode($tester->getDisplay(), true);
        self::assertIsArray($rows);

        return $rows;
    }
}
