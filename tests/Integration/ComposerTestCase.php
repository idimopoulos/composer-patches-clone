<?php

declare(strict_types=1);

namespace PatchManager\Tests\Integration;

use PHPUnit\Framework\Assert;
use PatchManager\Tests\Support\PatchServer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

abstract class ComposerTestCase extends TestCase
{
    protected string $workingDirectory;
    private ?PatchServer $patchServer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'composer-test-' . uniqid('', true);
        $this->copyDirectory($this->fixtureDirectory(), $this->workingDirectory);
        $this->rewriteFixtureComposerRepository();

        $process = $this->createComposerProcess('install');
        $process->mustRun();
    }

    protected function tearDown(): void
    {
        if ($this->patchServer !== null) {
            $this->patchServer->stop();
            $this->patchServer = null;
        }

        $this->deleteDirectory($this->workingDirectory);

        parent::tearDown();
    }

    /**
     * @param array<string, string> $env
     */
    protected function runComposer(string $command, array $env = []): string
    {
        $process = $this->createComposerProcess($command, $env);
        $process->run();

        $this->assertComposerSuccess($process);

        return $process->getOutput() . $process->getErrorOutput();
    }

    protected function assertComposerSuccess(Process $process): void
    {
        Assert::assertTrue(
            $process->isSuccessful(),
            sprintf(
                "Composer command failed with exit code %s.\nSTDOUT:\n%s\nSTDERR:\n%s",
                (string) $process->getExitCode(),
                $process->getOutput(),
                $process->getErrorOutput()
            )
        );
    }

    protected function startPatchServer(): void
    {
        $this->patchServer ??= PatchServer::start();
    }

    protected function patchUrl(string $path): string
    {
        if ($this->patchServer === null) {
            throw new RuntimeException('Call startPatchServer() before requesting a patch URL.');
        }

        return $this->patchServer->url($path);
    }

    private function fixtureDirectory(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'test-project';
    }

    /**
     * @param array<string, string> $env
     */
    private function createComposerProcess(string $command, array $env = []): Process
    {
        return Process::fromShellCommandline(
            'composer ' . $command,
            $this->workingDirectory,
            $env + ['COMPOSER_ALLOW_SUPERUSER' => '1']
        );
    }

    private function rewriteFixtureComposerRepository(): void
    {
        $composerJsonPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $contents = file_get_contents($composerJsonPath);

        if ($contents === false) {
            return;
        }

        $data = json_decode($contents, true);

        if (!is_array($data) || !isset($data['repositories']) || !is_array($data['repositories'])) {
            return;
        }

        foreach ($data['repositories'] as &$repository) {
            if (($repository['type'] ?? null) !== 'path') {
                continue;
            }

            $repository['url'] = dirname(__DIR__, 2);
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        file_put_contents($composerJsonPath, $encoded . "\n");
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        if (!is_dir($source)) {
            throw new RuntimeException(sprintf('Fixture directory %s does not exist.', $source));
        }

        $items = scandir($source);

        if ($items === false) {
            throw new RuntimeException(sprintf('Unable to read fixture directory %s.', $source));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($from)) {
                $this->copyDirectory($from, $to);
                continue;
            }

            copy($from, $to);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
