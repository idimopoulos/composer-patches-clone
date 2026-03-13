<?php

declare(strict_types=1);

namespace PatchManager\Tests\Integration;

final class PluginLoadTest extends ComposerTestCase
{
    public function testComposerListRunsSuccessfully(): void
    {
        $output = $this->runComposer('list');

        self::assertNotSame('', trim($output));
    }
}
