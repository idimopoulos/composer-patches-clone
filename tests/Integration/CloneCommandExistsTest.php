<?php

declare(strict_types=1);

namespace PatchManager\Tests\Integration;

final class CloneCommandExistsTest extends ComposerTestCase
{
    public function testCloneCommandIsRegistered(): void
    {
        $this->startPatchServer();

        $output = $this->runComposer('patches:clone drupal/core http://localhost:8123/example.patch');

        self::assertStringContainsString('Patch downloaded', $output);
        self::assertStringContainsString('composer.json updated', $output);
    }
}
