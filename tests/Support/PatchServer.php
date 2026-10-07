<?php

declare(strict_types=1);

namespace PatchManager\Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Serves tests/Fixtures/patches over HTTP on a free local port.
 */
final class PatchServer
{
    private const HOST = '127.0.0.1';
    private const STARTUP_TIMEOUT_SECONDS = 5.0;

    private Process $process;
    private int $port;

    private function __construct()
    {
    }

    public static function start(?string $documentRoot = null): self
    {
        $documentRoot ??= self::fixturesDirectory();

        if (!is_dir($documentRoot)) {
            throw new RuntimeException(sprintf('Patch server document root %s does not exist.', $documentRoot));
        }

        $server = new self();
        $server->port = self::findFreePort();
        $server->process = new Process(
            [PHP_BINARY, '-S', self::HOST . ':' . $server->port, '-t', $documentRoot, self::routerScript()],
            $documentRoot
        );
        $server->process->start();
        $server->waitUntilReady();

        return $server;
    }

    public static function fixturesDirectory(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'patches';
    }

    public static function routerScript(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'patch-server-router.php';
    }

    public function url(string $path): string
    {
        return sprintf('http://%s:%d/%s', self::HOST, $this->port, ltrim($path, '/'));
    }

    public function stop(): void
    {
        if ($this->process->isRunning()) {
            $this->process->stop(1);
        }
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + self::STARTUP_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            if (!$this->process->isRunning()) {
                break;
            }

            $socket = @fsockopen(self::HOST, $this->port, $errorCode, $errorMessage, 0.1);

            if (is_resource($socket)) {
                fclose($socket);

                return;
            }

            usleep(50000);
        }

        $this->stop();

        throw new RuntimeException(
            "Patch fixture server failed to start.\nSTDOUT:\n"
            . $this->process->getOutput()
            . "\nSTDERR:\n"
            . $this->process->getErrorOutput()
        );
    }

    private static function findFreePort(): int
    {
        $socket = @stream_socket_server('tcp://' . self::HOST . ':0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException(sprintf('Unable to allocate a free port: %s', $errorMessage));
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
