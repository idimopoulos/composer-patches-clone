<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

final class PatchesFileTest extends ComposerTestCase
{
    public function testCommandsWorkWithAPatchesFile(): void
    {
        $this->startPatchServer();
        $composerJsonPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $patchesFilePath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.patches.json';

        $data = json_decode((string) file_get_contents($composerJsonPath), true);
        self::assertIsArray($data);
        $data['extra']['patches-file'] = 'composer.patches.json';
        file_put_contents($composerJsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        file_put_contents($patchesFilePath, (string) json_encode([
            'patches' => ['drupal/core' => ['Remote' => $this->patchUrl('one/fix.patch')]],
        ]));

        $this->runComposer('patches:migrate');
        $this->runComposer('patches:clone drupal/token ' . $this->patchUrl('example.patch') . ' --description="Cloned"');
        $output = $this->runComposer('patches:list');

        $patchesFile = json_decode((string) file_get_contents($patchesFilePath), true);
        self::assertIsArray($patchesFile);
        self::assertSame(
            [
                'drupal/core' => ['Remote' => 'resources/patch/drupal/core/fix.patch'],
                'drupal/token' => ['Cloned' => 'resources/patch/drupal/token/example.patch'],
            ],
            $patchesFile['patches']
        );
        self::assertStringContainsString($this->patchUrl('one/fix.patch'), $output);
        self::assertStringContainsString($this->patchUrl('example.patch'), $output);
        self::assertArrayNotHasKey('patches', $data['extra']);
    }
}
