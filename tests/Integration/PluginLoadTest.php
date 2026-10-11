<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

final class PluginLoadTest extends ComposerTestCase
{
    public function testComposerListRunsSuccessfully(): void
    {
        $output = $this->runComposer('list');

        self::assertNotSame('', trim($output));
    }
}
