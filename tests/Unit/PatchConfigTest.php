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
     * @return array<string, array{0: int|null, 1: array<string, mixed>, 2: string}>
     */
    public static function patchesFileSettings(): array
    {
        return [
            'cweagans 1.x' => [1, ['patches-file' => 'composer.patches.json'], 'composer.patches.json'],
            'cweagans 2.x' => [2, ['composer-patches' => ['patches-file' => 'patches/custom.json']], 'patches/custom.json'],
            'cweagans 2.x default' => [2, [], 'patches.json'],
            'unknown version, 1.x setting' => [null, ['patches-file' => 'composer.patches.json'], 'composer.patches.json'],
            'unknown version, default' => [null, [], 'patches.json'],
        ];
    }

    /**
     * @dataProvider patchesFileSettings
     *
     * @param array<string, mixed> $extra
     */
    public function testItFindsThePatchesFile(?int $major, array $extra, string $path): void
    {
        $this->writeFile($path, ['patches' => ['drupal/core' => ['Fix' => 'https://example.com/fix.patch']]]);
        $config = $this->config($extra === [] ? [] : ['extra' => $extra], $major);

        self::assertSame($path, $config->patchesFilePath());
        self::assertSame(['composer.json', basename($path)], $this->storeNames($config));
        self::assertSame(
            ['drupal/core' => ['Fix' => 'https://example.com/fix.patch']],
            $config->stores()[1]->getPatches()
        );
    }

    /**
     * @return array<string, array{0: int, 1: array<string, mixed>, 2: string}>
     */
    public static function patchesFilesTheInstalledVersionIgnores(): array
    {
        return [
            '1.x has no patches.json default' => [1, [], 'patches.json'],
            '1.x ignores the 2.x setting' => [1, ['composer-patches' => ['patches-file' => 'p.json']], 'p.json'],
            '1.x ignores the file when extra.patches exists' => [
                1,
                ['patches-file' => 'composer.patches.json', 'patches' => ['drupal/token' => ['A' => 'a.patch']]],
                'composer.patches.json',
            ],
            '2.x ignores the 1.x setting' => [2, ['patches-file' => 'composer.patches.json'], 'composer.patches.json'],
        ];
    }

    /**
     * @dataProvider patchesFilesTheInstalledVersionIgnores
     *
     * @param array<string, mixed> $extra
     */
    public function testItIgnoresPatchesFilesTheInstalledVersionDoesNotRead(int $major, array $extra, string $path): void
    {
        $this->writeFile($path, ['patches' => ['drupal/core' => ['Fix' => 'https://example.com/fix.patch']]]);
        $config = $this->config($extra === [] ? [] : ['extra' => $extra], $major);

        self::assertNull($config->patchesFilePath());
        self::assertSame(['composer.json'], $this->storeNames($config));
        self::assertSame('composer.json', basename($config->storeFor('drupal/core', 'Fix')->getPath()));
    }

    public function testComposerPatches2ReadsThePatchesFileFromTheEnvironment(): void
    {
        $this->writeFile('from-env.json', ['patches' => []]);
        putenv('COMPOSER_PATCHES_PATCHES_FILE=from-env.json');

        try {
            $config = $this->config(['extra' => ['composer-patches' => ['patches-file' => 'patches/custom.json']]], 2);

            self::assertSame('from-env.json', $config->patchesFilePath());
        } finally {
            putenv('COMPOSER_PATCHES_PATCHES_FILE');
        }
    }

    public function testComposerPatches2MergesThePatchesFileWithExtraPatches(): void
    {
        $this->writeFile('patches.json', ['patches' => ['drupal/core' => ['In file' => 'a.patch']]]);
        $config = $this->config(['extra' => ['patches' => ['drupal/token' => ['In json' => 'b.patch']]]], 2);

        self::assertSame(['composer.json', 'patches.json'], $this->storeNames($config));
        self::assertSame('patches.json', basename($config->storeFor('drupal/core', 'In file')->getPath()));
        self::assertSame('composer.json', basename($config->storeFor('drupal/core', 'New')->getPath()));
    }

    public function testComposerPatches2SkipsADisabledPatchesFileResolver(): void
    {
        $this->writeFile('patches.json', ['patches' => ['drupal/core' => ['In file' => 'a.patch']]]);
        $config = $this->config(['extra' => ['composer-patches' => [
            'disable-resolvers' => ['\\cweagans\\Composer\\Resolver\\PatchesFile'],
        ]]], 2);

        self::assertNull($config->patchesFilePath());
        self::assertSame(['composer.json'], $this->storeNames($config));
        self::assertSame('composer.json', basename($config->storeFor('drupal/core', 'In file')->getPath()));
    }

    public function testComposerPatches2SkipsADisabledRootComposerResolver(): void
    {
        $config = $this->config(['extra' => [
            'patches' => ['drupal/token' => ['In json' => 'b.patch']],
            'composer-patches' => ['disable-resolvers' => ['\\cweagans\\Composer\\Resolver\\RootComposer']],
        ]], 2);

        self::assertSame([], $this->storeNames($config));

        $store = $config->storeFor('drupal/token', 'In json');

        self::assertSame('patches.json', basename($store->getPath()));
        self::assertFileExists('patches.json');
        self::assertSame(['patches.json'], $this->storeNames($config));
    }

    public function testComposerPatches2ReadsDisabledResolversFromTheEnvironment(): void
    {
        $this->writeFile('patches.json', ['patches' => []]);
        putenv('COMPOSER_PATCHES_DISABLE_RESOLVERS=\\cweagans\\Composer\\Resolver\\PatchesFile');

        try {
            self::assertNull($this->config([], 2)->patchesFilePath());
        } finally {
            putenv('COMPOSER_PATCHES_DISABLE_RESOLVERS');
        }
    }

    public function testItRefusesToStorePatchesWhenEveryResolverIsDisabled(): void
    {
        $config = $this->config(['extra' => ['composer-patches' => ['disable-resolvers' => [
            '\\cweagans\\Composer\\Resolver\\RootComposer',
            '\\cweagans\\Composer\\Resolver\\PatchesFile',
        ]]]], 2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disable-resolvers');

        $config->storeFor('drupal/core', 'New');
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
     * @param int|null $composerPatchesMajor
     *   The installed cweagans/composer-patches major version, if known.
     */
    private function config(array $composerJson, ?int $composerPatchesMajor = null): PatchConfig
    {
        $this->writeFile('composer.json', $composerJson === [] ? new \stdClass() : $composerJson);

        return new PatchConfig('composer.json', $composerPatchesMajor);
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
