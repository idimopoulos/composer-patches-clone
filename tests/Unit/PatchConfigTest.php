<?php

declare(strict_types=1);

namespace Idimopoulos\ComposerPatchesClone\Tests\Unit;

use Idimopoulos\ComposerPatchesClone\Composer\PatchConfig;
use Idimopoulos\ComposerPatchesClone\Tests\Support\Filesystem;
use PHPUnit\Framework\TestCase;

final class PatchConfigTest extends TestCase
{
    private string $projectRoot;
    private string $previousCwd;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'patch-config-' . uniqid('', true);
        mkdir($this->projectRoot, 0777, true);
        // Patches-file paths are relative to the working directory, as in Composer.
        $this->previousCwd = (string) getcwd();
        chdir($this->projectRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        Filesystem::removeDirectory($this->projectRoot);
    }

    public function testWithoutAPatchesFileEverythingLivesInComposerJson(): void
    {
        $config = $this->config(['extra' => ['patches' => []]]);

        self::assertSame(['composer.json'], $this->storeNames($config));
        self::assertSame('composer.json', basename($config->storeFor('drupal/core', 'Fix')->getPath()));
        self::assertNull($config->patchesFilePath());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function patchesFileSettings(): array
    {
        return [
            'cweagans 1.x' => [['patches-file' => 'composer.patches.json'], 'composer.patches.json'],
            'cweagans 2.x' => [['composer-patches' => ['patches-file' => 'patches/custom.json']], 'patches/custom.json'],
            'cweagans 2.x default' => [[], 'patches.json'],
        ];
    }

    /**
     * @dataProvider patchesFileSettings
     *
     * @param array<string, mixed> $extra
     */
    public function testItFindsThePatchesFile(array $extra, string $path): void
    {
        $this->writeFile($path, ['patches' => ['drupal/core' => ['Fix' => 'https://example.com/fix.patch']]]);
        $config = $this->config($extra === [] ? [] : ['extra' => $extra]);

        self::assertSame($path, $config->patchesFilePath());
        self::assertSame(['composer.json', basename($path)], $this->storeNames($config));
        self::assertSame(
            ['drupal/core' => ['Fix' => 'https://example.com/fix.patch']],
            $config->stores()[1]->getPatches()
        );
    }

    public function testNewPatchesGoToThePatchesFileWhenComposerJsonHasNone(): void
    {
        $this->writeFile('composer.patches.json', ['patches' => []]);
        $config = $this->config(['extra' => ['patches-file' => 'composer.patches.json']]);

        self::assertSame('composer.patches.json', basename($config->storeFor('drupal/core', 'New')->getPath()));
    }

    public function testNewPatchesStayInComposerJsonWhenItAlreadyHasPatches(): void
    {
        $this->writeFile('composer.patches.json', ['patches' => ['drupal/core' => ['In file' => 'a.patch']]]);
        $config = $this->config([
            'extra' => [
                'patches-file' => 'composer.patches.json',
                'patches' => ['drupal/token' => ['In json' => 'b.patch']],
            ],
        ]);

        self::assertSame('composer.json', basename($config->storeFor('drupal/core', 'New')->getPath()));
        self::assertSame('composer.patches.json', basename($config->storeFor('drupal/core', 'In file')->getPath()));
        self::assertSame('composer.json', basename($config->storeFor('drupal/token', 'In json')->getPath()));
    }

    public function testAMissingConfiguredPatchesFileIsCreatedOnlyWhenWrittenTo(): void
    {
        $config = $this->config(['extra' => ['patches-file' => 'patches/composer.patches.json']]);

        self::assertSame(['composer.json'], $this->storeNames($config));
        self::assertFileDoesNotExist('patches/composer.patches.json');

        $store = $config->storeFor('drupal/core', 'New');

        self::assertSame('composer.patches.json', basename($store->getPath()));
        self::assertFileExists('patches/composer.patches.json');
        self::assertSame([], $store->getPatches());
    }

    public function testADefaultPatchesJsonIsNotCreated(): void
    {
        $config = $this->config([]);

        self::assertSame('composer.json', basename($config->storeFor('drupal/core', 'New')->getPath()));
        self::assertFileDoesNotExist('patches.json');
    }

    /**
     * @param array<string, mixed> $composerJson
     */
    private function config(array $composerJson): PatchConfig
    {
        $this->writeFile('composer.json', $composerJson === [] ? new \stdClass() : $composerJson);

        return new PatchConfig('composer.json');
    }

    /**
     * @return list<string>
     */
    private function storeNames(PatchConfig $config): array
    {
        return array_map(static fn ($store): string => basename($store->getPath()), $config->stores());
    }

    private function writeFile(string $path, mixed $data): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
