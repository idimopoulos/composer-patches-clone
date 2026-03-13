# Composer Patches Clone

`idimopoulos/composer-patches-clone` is a Composer plugin built to work alongside [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches).

It helps turn remote patch definitions into local patch files that live in your project, and updates `composer.json` so your patch configuration points at stable local paths.

## Current Commands

- `composer patches:clone <package> <url> --description="..."`  
  Download a remote patch, save it locally, and register it in `composer.json`.

- `composer patches:migrate`  
  Scan existing `extra.patches` entries, download remote patch URLs, and replace them with local file paths.

## Local Development

The repository includes a Docker-based test setup.

```bash
make install
make test
```

Or run commands directly:

```bash
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
```

## Notes

- The plugin currently uses the `PatchManager\\` PHP namespace.
- By default, local patches are written under `resources/patch`.
- `patches:clone` also supports `--base-path` and `--patch-name`.
