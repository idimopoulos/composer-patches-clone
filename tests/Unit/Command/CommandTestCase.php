<?php

declare(strict_types=1);

namespace PatchManager\Tests\Unit\Command;

use Composer\Console\Application;
use Composer\Package\Locker;
use PatchManager\Command\ClonePatchCommand;
use PatchManager\Command\ListPatchesCommand;
use PatchManager\Command\MigratePatchesCommand;
use PatchManager\Composer\ComposerJsonUpdater;
use PatchManager\Composer\LockHashUpdater;
use PatchManager\Patch\PatchDownloader;
use PatchManager\Patch\PatchWriter;
use PatchManager\Tests\Support\ComposerServices;
use PatchManager\Tests\Support\Filesystem;
use PatchManager\Tests\Support\PatchServer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs plugin commands in-process against a temporary project.
 */
abstract class CommandTestCase extends TestCase
{
    private static ?PatchServer $patchServer = null;

    protected string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        self::$patchServer = PatchServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$patchServer?->stop();
        self::$patchServer = null;
    }

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'patch-command-' . uniqid('', true);
        mkdir($this->projectRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        Filesystem::removeDirectory($this->projectRoot);
    }

    protected function patchUrl(string $path): string
    {
        self::assertNotNull(self::$patchServer);

        return self::$patchServer->url($path);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function writeComposerJson(array $data): void
    {
        $encoded = json_encode($data === [] ? new \stdClass() : $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        self::assertIsString($encoded);
        file_put_contents($this->composerJsonPath(), $encoded . "\n");
    }

    /**
     * @return array<string, mixed>
     */
    protected function readComposerJson(): array
    {
        $contents = file_get_contents($this->composerJsonPath());
        self::assertIsString($contents);
        $data = json_decode($contents, true);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function patchesFor(string $package): array
    {
        $data = $this->readComposerJson();
        $patches = $data['extra']['patches'][$package] ?? null;
        self::assertIsArray($patches);

        return $patches;
    }

    protected function projectFile(string $relativePath): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    protected function cloneCommand(): CommandTester
    {
        return $this->tester(new ClonePatchCommand(
            new PatchDownloader(ComposerServices::httpDownloader()),
            new PatchWriter($this->projectRoot),
            new ComposerJsonUpdater($this->composerJsonPath()),
            $this->lockHashUpdater()
        ));
    }

    protected function migrateCommand(): CommandTester
    {
        return $this->tester(new MigratePatchesCommand(
            new PatchDownloader(ComposerServices::httpDownloader()),
            new PatchWriter($this->projectRoot),
            new ComposerJsonUpdater($this->composerJsonPath()),
            $this->lockHashUpdater()
        ));
    }

    protected function listCommand(): CommandTester
    {
        return $this->tester(new ListPatchesCommand(
            $this->projectRoot,
            new ComposerJsonUpdater($this->composerJsonPath())
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function sourcesFor(string $package): array
    {
        $sources = $this->readComposerJson()['extra']['patches-sources'][$package] ?? null;
        self::assertIsArray($sources);

        return $sources;
    }

    private function tester(Command $command): CommandTester
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommands([$command]);

        return new CommandTester($command);
    }

    /**
     * Writes a minimal composer.lock whose content-hash matches composer.json,
     * or the given hash.
     */
    protected function writeLock(?string $hash = null): void
    {
        $hash ??= Locker::getContentHash((string) file_get_contents($this->composerJsonPath()));
        file_put_contents(
            $this->lockPath(),
            "{\n    \"content-hash\": \"{$hash}\",\n    \"packages\": [],\n    \"platform\": {}\n}\n"
        );
    }

    protected function lockIsFresh(): bool
    {
        $lock = json_decode((string) file_get_contents($this->lockPath()), true);
        self::assertIsArray($lock);

        return $lock['content-hash'] === Locker::getContentHash((string) file_get_contents($this->composerJsonPath()));
    }

    protected function lockPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'composer.lock';
    }

    private function lockHashUpdater(): LockHashUpdater
    {
        return new LockHashUpdater($this->composerJsonPath(), $this->lockPath());
    }

    protected function composerJsonPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
    }
}
