# Composer Patches Clone

`idimopoulos/composer-patches-clone` is a Composer plugin built to work alongside [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches).

It helps turn remote patch definitions into local patch files that live in your project, and updates `composer.json` so your patch configuration points at stable local paths.

## Current Commands

- `composer patches:clone <package> <url> --description="..."`  
  Download a remote patch, save it locally, and register it in `composer.json`.

- `composer patches:migrate`  
  Scan existing `extra.patches` entries, download remote patch URLs, and replace them with local file paths.

- `composer patches:list [package] [--format=table|json]`  
  List configured patches with their local path and the URL they were downloaded from. Filter by package, using wildcards if needed (`drupal/*`).

## Patches files

Patches kept in a separate file are supported too. The plugin follows the rules of the installed cweagans/composer-patches version:

- **1.x** reads `extra.patches-file`, and only when `composer.json` has no `extra.patches`.
- **2.x** reads the file named by `COMPOSER_PATCHES_PATCHES_FILE`, else `extra.composer-patches.patches-file`, else `patches.json` in the project root, and merges it with `extra.patches`.
- **2.x `disable-resolvers`** (or `COMPOSER_PATCHES_DISABLE_RESOLVERS`) is respected: a disabled `RootComposer` or `PatchesFile` resolver means that source is neither read nor written.

- `patches:migrate` and `patches:list` cover both `composer.json` and the patches file.
- `patches:clone` updates a patch where it is already defined. New patches go to the patches file when `composer.json` has no `extra.patches` of its own, and to `composer.json` otherwise.
- Source URLs for patches in the patches file are stored in that file, under a top-level `patches-sources` key. cweagans/composer-patches only reads `patches`, so the extra key is ignored there.
- The expanded format of cweagans/composer-patches 2.x (a list of `{"description": …, "url": …}` objects) isn't edited: `patches:clone` refuses to touch such a package and leaves the file unchanged, and `patches:migrate` skips it.

## cweagans/composer-patches 2.x

Version 2.x applies patches from its own `patches.lock.json`, not directly from the definitions. When that file exists, `patches:clone` and `patches:migrate` remind you to run `composer patches-relock` and then `composer patches-repatch` so the change is applied.

## Source URLs

`patches:clone` and `patches:migrate` record where each patch came from in `extra.patches-sources`, keyed the same way as `extra.patches`:

```json
"extra": {
    "patches": {
        "drupal/core": {
            "Fix something": "resources/patch/drupal/core/1234.diff.patch"
        }
    },
    "patches-sources": {
        "drupal/core": {
            "Fix something": "https://git.drupalcode.org/project/drupal/-/merge_requests/1234.diff"
        }
    }
}
```

The URLs are stored in `composer.json` rather than `composer.lock`. Composer rewrites the lock file on every update without custom keys, and dependency bots run updates with plugins disabled, so they would be lost there. Patches added by hand show `(unknown)` as their source in `patches:list`.

## Requirements

- PHP 8.1 or newer
- Composer 2.3 or newer

## Local Development

The repository includes a Docker-based test setup.

```bash
make install
make test
```

Or run commands directly:

```bash
docker compose run --rm php composer install
docker compose run --rm php composer test
```

`composer test:unit` runs only the fast unit tests. `composer test:integration` runs the end-to-end tests, which call a real `composer` binary and need network access to Packagist.

`composer.lock` is resolved for PHP 8.1 (`config.platform.php`), so it installs on every supported PHP version.

## Notes

- The PHP namespace is `Idimopoulos\ComposerPatchesClone\`.
- By default, local patches are written under `resources/patch`.
- `patches:clone` also supports `--base-path` and `--patch-name`. `--patch-name` must be a plain filename and `--base-path` must not contain `..` segments.
- Running `patches:clone` again for an existing description refreshes that patch in place, and turns a remote entry into a local one.
- If a different patch already uses the target filename, the new file gets a numeric suffix (`fix-2.patch`) instead of overwriting it. Identical content reuses the existing file.
- `patches:migrate` only handles `"description": "https://..."` entries; other formats are reported and skipped. Failed downloads are reported, the remaining patches are still migrated, and the command exits non-zero.
- `composer.json` is edited in place, so formatting and unrelated content are preserved. If the `COMPOSER` environment variable names another file, that file is edited instead.
- Composer hashes the whole `extra` section into `composer.lock`, so changing patches would normally make the lock look out of date. When the lock was up to date before the change, `patches:clone` and `patches:migrate` refresh its `content-hash` (nothing else in the lock changes); run `composer install` afterwards to apply the patches. A lock that was already out of date is left alone, with a hint to run `composer update --lock`. Projects with `"lock": false` are left alone entirely.
- Patches are downloaded with Composer's own HTTP client, so `auth.json` credentials, proxy settings and `secure-http` apply. Only `http://` and `https://` URLs are accepted, and plain `http://` needs `"secure-http": false`, just like Composer itself.

## Continuous Integration

Every pull request runs the test suite on GitHub Actions (`.github/workflows/ci.yml`). The jobs cover PHP 8.1 to 8.4 with the newest allowed dependencies, PHP 8.1 with the oldest allowed dependencies and Composer 2.3, and PHP 8.3 with the committed `composer.lock`.

## License

MIT, see [LICENSE](LICENSE).
