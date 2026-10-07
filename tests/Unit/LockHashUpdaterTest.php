<?php

declare(strict_types=1);

namespace PatchManager\Tests\Unit;

use Composer\Package\Locker;
use PatchManager\Composer\LockHashUpdater;
use PHPUnit\Framework\TestCase;

final class LockHashUpdaterTest extends TestCase
{
    private string $workspace;
    private string $composerJsonPath;
    private string $lockPath;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lock-hash-' . uniqid('', true);
        mkdir($this->workspace, 0777, true);
        $this->composerJsonPath = $this->workspace . DIRECTORY_SEPARATOR . 'composer.json';
        $this->lockPath = $this->workspace . DIRECTORY_SEPARATOR . 'composer.lock';
        file_put_contents($this->composerJsonPath, "{\n    \"require\": {}\n}\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workspace . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->workspace);
    }

    public function testItRefreshesTheHashOfAFreshLock(): void
    {
        $this->writeLock($this->hashOfComposerJson());
        touch($this->lockPath, 1_600_000_000);
        $updater = new LockHashUpdater($this->composerJsonPath, $this->lockPath);
        $wasFresh = $updater->wasFresh();
        $lockBefore = (string) file_get_contents($this->lockPath);

        file_put_contents($this->composerJsonPath, "{\n    \"require\": {},\n    \"extra\": {\"patches\": {}}\n}\n");
        $message = $updater->sync($wasFresh);

        self::assertTrue($wasFresh);
        self::assertSame('composer.lock hash updated.', $message);
        self::assertSame(
            str_replace($this->hashFromLock($lockBefore), $this->hashOfComposerJson(), $lockBefore),
            file_get_contents($this->lockPath),
            'Only the content-hash value may change.'
        );
        clearstatcache();
        self::assertSame(1_600_000_000, filemtime($this->lockPath));
    }

    public function testItLeavesAStaleLockAloneAndSaysSo(): void
    {
        $this->writeLock('0123456789abcdef0123456789abcdef');
        $updater = new LockHashUpdater($this->composerJsonPath, $this->lockPath);
        $lockBefore = file_get_contents($this->lockPath);

        $wasFresh = $updater->wasFresh();
        $message = $updater->sync($wasFresh);

        self::assertFalse($wasFresh);
        self::assertSame('composer.lock was already out of date; run "composer update --lock" to refresh it.', $message);
        self::assertSame($lockBefore, file_get_contents($this->lockPath));
    }

    public function testItIgnoresAMissingLock(): void
    {
        $updater = new LockHashUpdater($this->composerJsonPath, $this->lockPath);

        self::assertNull($updater->wasFresh());
        self::assertNull($updater->sync(null));
        self::assertFileDoesNotExist($this->lockPath);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unusableLocks(): array
    {
        return [
            'invalid json' => ['{"content-hash": '],
            'no content-hash' => ["{\n    \"hash\": \"abc\",\n    \"packages\": []\n}\n"],
        ];
    }

    /**
     * @dataProvider unusableLocks
     */
    public function testItIgnoresLocksItCannotRead(string $lock): void
    {
        file_put_contents($this->lockPath, $lock);
        $updater = new LockHashUpdater($this->composerJsonPath, $this->lockPath);

        self::assertNull($updater->wasFresh());
        self::assertSame($lock, file_get_contents($this->lockPath));
    }

    private function writeLock(string $hash): void
    {
        file_put_contents(
            $this->lockPath,
            "{\n    \"_readme\": [\"generated\"],\n    \"content-hash\": \"{$hash}\",\n    \"packages\": [],\n    \"platform\": {}\n}\n"
        );
    }

    private function hashOfComposerJson(): string
    {
        return Locker::getContentHash((string) file_get_contents($this->composerJsonPath));
    }

    private function hashFromLock(string $lock): string
    {
        $data = json_decode($lock, true);
        self::assertIsArray($data);

        return (string) $data['content-hash'];
    }
}
