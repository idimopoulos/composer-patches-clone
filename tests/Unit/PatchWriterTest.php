<?php

declare(strict_types=1);

namespace PatchManager\Tests\Unit;

use PatchManager\Patch\PatchWriter;
use PatchManager\Tests\Support\Filesystem;
use PHPUnit\Framework\TestCase;

final class PatchWriterTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'patch-writer-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        Filesystem::removeDirectory($this->projectRoot);
    }

    public function testItWritesPatchUsingUrlBasename(): void
    {
        $writer = new PatchWriter($this->projectRoot);

        $path = $writer->write(
            'drupal/core',
            'Example patch',
            "--- a/file.txt\n+++ b/file.txt\n",
            'https://example.com/example.patch'
        );

        self::assertSame('resources/patch/drupal/core/example.patch', $path);
    }

    public function testItCreatesDirectoriesAndWritesContents(): void
    {
        $writer = new PatchWriter($this->projectRoot);
        $contents = "--- a/file.txt\n+++ b/file.txt\n";

        $path = $writer->write(
            'drupal/core',
            'Example patch',
            $contents,
            'https://example.com/example.patch'
        );

        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        self::assertDirectoryExists(
            $this->projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'patch' . DIRECTORY_SEPARATOR . 'drupal' . DIRECTORY_SEPARATOR . 'core'
        );
        self::assertFileExists($absolutePath);
        self::assertSame($contents, file_get_contents($absolutePath));
    }

    public function testItAppendsPatchExtensionWhenMissing(): void
    {
        $writer = new PatchWriter($this->projectRoot);

        $path = $writer->write(
            'drupal/core',
            'Example patch',
            "--- a/file.txt\n+++ b/file.txt\n",
            'https://example.com/example'
        );

        self::assertSame('resources/patch/drupal/core/example.patch', $path);
    }

    public function testItSupportsCustomBasePathAndPatchName(): void
    {
        $writer = new PatchWriter($this->projectRoot);

        $path = $writer->write(
            'drupal/core',
            'Example patch',
            "--- a/file.txt\n+++ b/file.txt\n",
            'https://example.com/example.patch',
            '/resources/patch/drupal',
            'custom-name'
        );

        self::assertSame('resources/patch/drupal/drupal/core/custom-name.patch', $path);
    }
}
