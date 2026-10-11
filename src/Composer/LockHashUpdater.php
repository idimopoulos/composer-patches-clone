<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Composer;

use Composer\Package\Locker;
use RuntimeException;

/**
 * Keeps the lock file's content-hash in step with patch changes.
 *
 * Composer hashes the whole "extra" section of composer.json, so editing
 * extra.patches makes the lock look out of date even though dependency
 * resolution is unaffected. When the lock was fresh before the change, only
 * its content-hash is rewritten; a lock that was already stale is left alone.
 */
final class LockHashUpdater
{
    /**
     * @param string|null $lockPath
     *   Null when the project disables the lock file ("lock": false).
     */
    public function __construct(
        private readonly string $composerJsonPath,
        private readonly ?string $lockPath
    ) {
    }

    /**
     * Whether the lock currently matches composer.json.
     *
     * Call this before changing composer.json. Returns null when there is no
     * usable lock file.
     */
    public function wasFresh(): ?bool
    {
        $lockHash = $this->readLockHash();
        $composerJson = @file_get_contents($this->composerJsonPath);

        if ($lockHash === null || $composerJson === false) {
            return null;
        }

        return $lockHash === Locker::getContentHash($composerJson);
    }

    /**
     * Brings the lock up to date after composer.json changed.
     *
     * @return string|null
     *   A message for the user, or null when there is no lock to talk about.
     */
    public function sync(?bool $wasFresh): ?string
    {
        if ($wasFresh === null) {
            return null;
        }

        $lockName = basename((string) $this->lockPath);

        if (!$wasFresh) {
            return sprintf('%s was already out of date; run "composer update --lock" to refresh it.', $lockName);
        }

        $this->refresh();

        return sprintf('%s hash updated.', $lockName);
    }

    private function refresh(): void
    {
        if ($this->lockPath === null) {
            return;
        }

        $lock = (string) file_get_contents($this->lockPath);
        $composerJson = @file_get_contents($this->composerJsonPath);

        if ($composerJson === false) {
            throw new RuntimeException(sprintf('Unable to read %s.', $this->composerJsonPath));
        }

        // Replace only the hash value so the rest of the file stays byte-identical.
        $updated = preg_replace(
            '/("content-hash"\s*:\s*")[^"]*(")/',
            '${1}' . Locker::getContentHash($composerJson) . '${2}',
            $lock,
            1
        );

        if ($updated === null) {
            throw new RuntimeException(sprintf('Unable to update %s.', $this->lockPath));
        }

        $mtime = filemtime($this->lockPath);

        if (file_put_contents($this->lockPath, $updated) === false) {
            throw new RuntimeException(sprintf('Unable to write %s.', $this->lockPath));
        }

        // Like Composer's own Locker::updateHash(), keep the lock's mtime.
        if ($mtime !== false) {
            touch($this->lockPath, $mtime);
        }
    }

    private function readLockHash(): ?string
    {
        if ($this->lockPath === null) {
            return null;
        }

        $contents = @file_get_contents($this->lockPath);

        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);
        $hash = is_array($data) ? ($data['content-hash'] ?? null) : null;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }
}
