<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Integration;

final class ComposerEnvironmentTest extends ComposerTestCase
{
    public function testComposerInstallCreatesVendorDirectory(): void
    {
        self::assertDirectoryExists($this->workingDirectory . DIRECTORY_SEPARATOR . 'vendor');
    }
}
