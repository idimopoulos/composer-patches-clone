<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

final class PatchListTest extends ComposerTestCase
{
    public function testListShowsClonedPatchWithItsSourceUrl(): void
    {
        $this->startPatchServer();
        $url = $this->patchUrl('example.patch');

        $this->runComposer('patches:clone drupal/core ' . $url . ' --description="Example patch"');
        $output = $this->runComposer('patches:list "drupal/*"');

        self::assertStringContainsString('Example patch', $output);
        self::assertStringContainsString('resources/patch/drupal/core/example.patch', $output);
        self::assertStringContainsString($url, $output);

        $output = $this->runComposer('patches:list other/package');

        self::assertStringContainsString('No patches found for other/package.', $output);
    }
}
