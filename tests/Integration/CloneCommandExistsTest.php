<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

final class CloneCommandExistsTest extends ComposerTestCase
{
    public function testCloneCommandIsRegistered(): void
    {
        $this->startPatchServer();

        $output = $this->runComposer('patches:clone drupal/core ' . $this->patchUrl('example.patch'));

        self::assertStringContainsString('Patch downloaded', $output);
        self::assertStringContainsString('composer.json updated', $output);
    }
}
