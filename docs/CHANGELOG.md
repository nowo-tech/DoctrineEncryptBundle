# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- (No changes yet.)

### Changed

- (No changes yet.)

### Fixed

- (No changes yet.)

---

## [2.0.5] - 2026-02-20

### Added

- **doctrine:encrypt:generate-secret-key:** Optional argument `config` to generate the key for a single config; when the key file already exists, the command asks for confirmation before overwriting. Without argument, the command now checks all configs (all `secret_directory_path` entries) and creates a missing key file for each Halite or Defuse config; configs that already have a key are skipped with a message.
- **Makefile:** Targets `validate` (runs `composer validate --strict`) and `update` (runs `composer update`); `make qa` now runs `validate` before cs-check and test.

### Changed

- **Test directory:** The test directory has been renamed from `Tests` to `tests` (lowercase). PHP namespaces remain `Nowo\DoctrineEncryptBundle\Tests\`; only the folder and references in `phpunit.xml.dist`, `composer.json` (autoload-dev), and `.php-cs-fixer.dist.php` changed.
- **symfony/var-exporter:** Kept in `require` (not only in require-dev) so that Doctrine ORM 3.x LazyGhost is available in all environments (CI and production when using lazy loading). This fixes functional test failures on PHP 8.4/8.5 with Symfony 8.0 ("Symfony LazyGhost is not available").
- **CI:** Install step pins `symfony/var-exporter:^7.4` so Doctrine ORM accepts the installed version for LazyGhost; coverage job also requires `symfony/var-exporter`.
- **Documentation:** [COMMANDS.md](docs/COMMANDS.md) updated: encrypt/decrypt database commands use argument `config` (config alias); generate-secret-key documents the new behaviour (all configs vs single config with confirmation).

No upgrade steps required from 2.0.4. See [UPGRADING.md](UPGRADING.md#upgrading-to-205).

---

## [2.0.4] - 2026-02-19

### Fixed

- **PHPUnit:** Unit tests for `EncryptUtil` and `DecryptExtension` (Twig) no longer mock the final class `EncryptorRegistry`; they use real `EncryptorRegistry` instances with mocked `EncryptorInterface` where needed. This fixes `ClassIsFinalException: Class ... is declared "final" and cannot be doubled` when running `make test-coverage` or `composer test-coverage` with PHPUnit 10/11.
- **Symfony Console:** Commands `doctrine:encrypt:database` and `doctrine:decrypt:database` now register arguments in an idempotent way: `config` and `batchSize` are only added when not already present. This avoids `LogicException: An argument with name "config" already exists` when the Console component builds the command definition more than once (e.g. with certain Symfony Console 7/8 setups or when tests call `getDefinition()`).

No upgrade steps required from 2.0.3. See [UPGRADING.md](UPGRADING.md#upgrading-to-204).

---

## [2.0.3] - 2026-02-19

### Changed

- **paragonie/sodium_compat:** Constraint relaxed from `^1.20` to `^1.20 || ^2.0` so the bundle installs in projects that require `sodium_compat` 2.x (e.g. 2.5.0).

No upgrade steps required from 2.0.2. See [UPGRADING.md](UPGRADING.md#upgrading-to-203).

---

## [2.0.2] - 2026-02-19

### Fixed

- **Symfony Console 8.0:** Command unit tests (`DoctrineEncryptDatabaseCommandTest`, `DoctrineDecryptDatabaseCommandTest`) now use `$command->setApplication($application)` instead of `$application->add($command)`, since `Application::add()` was removed in Symfony Console 8.0.
- **CI:** Ensure `symfony/var-exporter` is installed so Doctrine ORM 3.x LazyGhost works in functional tests; CI install step now runs a full `composer update` so the package is resolved and installed on all matrix jobs.

No upgrade steps required from 2.0.1. See [UPGRADING.md](UPGRADING.md#upgrading-to-202).

---

## [2.0.1] - 2026-02-19

### Added

- **require-dev:** `symfony/var-exporter` ^7.0 || ^8.0 for Doctrine ORM 3.x LazyGhost support in functional tests.

### Changed

- **Documentation:** README, INSTALLATION, and ROADMAP now state explicitly that the bundle is **compatible with Symfony 7 and 8**; Requirements and ROADMAP focus updated to 2.x.

### Fixed

- Unit tests for `DefuseEncryptor` and `HaliteEncryptor` no longer depend on fixture key files or `sys_get_temp_dir()`; they create key files under the test `fixtures` directory so CI (e.g. GitHub Actions) runs reliably.

No upgrade steps required from 2.0.0. See [UPGRADING.md](UPGRADING.md#upgrading-to-201).

---

## [2.0.0] - 2026-02-19

### Removed

- **Symfony 6 support.** The bundle now requires Symfony ^7.0 || ^8.0. If you are on Symfony 6.x, stay on `nowo-tech/doctrine-encrypt-bundle` ^1.0 or upgrade your application to Symfony 7 or 8.
- **Demo for Symfony 6.** The `demo/symfony6` project has been removed. Demos for Symfony 7 and 8 remain in `demo/symfony7` and `demo/symfony8`.

### Changed

- Composer requirements: all `symfony/*` constraints updated from `^6.0 || ^7.0 || ^8.0` to `^7.0 || ^8.0`.
- CI (GitHub Actions) and documentation now target only Symfony 7.0 and 8.0.

For upgrade steps, see [UPGRADING.md](UPGRADING.md#upgrading-to-200).

---

## [1.0.0] - 2025-02-19

First release under **nowo-tech**. Fork from `hec-franco/doctrine-encrypt-bundle` with Halite and Defuse encryptors, Doctrine ORM 2.x/3.x, and attribute/annotation support.

### Added

- **nowo-tech fork:** Bundle in namespace `Nowo\DoctrineEncryptBundle` and package `nowo-tech/doctrine-encrypt-bundle`.
- Support for Symfony 7 and 8; PHP 8.1+.
- **Multiple encryptors:** Config key `configs` (e.g. `personal_data`, `financial_data`) with `default_config`; each config has its own encryptor and key file (`.{Encryptor}.{alias}.key`, e.g. `.Halite.personal_data.key`).
- Attribute `#[Encrypted('alias')]` to assign a field to a named config; use `"default"` for the default config.
- Documentation in `docs/` (CHANGELOG, UPGRADING, RELEASE, CONFIGURATION, INSTALLATION, USAGE, COMMANDS, DEMO, custom_encryptor, etc.).
- Makefile and Docker setup for development (`test`, `cs-check`, `cs-fix`, `qa`).
- Demo applications for Symfony 7 and 8 (entity `SensitiveRecord`, CRUD, fixtures, Halite + Defuse).
- Symfony Flex recipe (config with `configs` only); demo apps excluded from the distributed package (`archive.exclude`, `export-ignore`).

### Changed

- Configuration alias: `ambta_doctrine_encrypt` → `nowo_doctrine_encrypt`. See [UPGRADING.md](UPGRADING.md) when migrating from the old bundle.
- Bundle class: `AmbtaDoctrineEncryptBundle` → `NowoDoctrineEncryptBundle`; register `Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class` in `config/bundles.php`.
- Single-encryptor config (legacy) still supported; recipe and docs recommend `configs` for new setups.

### Fixed

- (None in this release.)

For upgrade steps from `ambta/doctrine-encrypt-bundle` or `hec-franco/doctrine-encrypt-bundle`, see [UPGRADING.md](UPGRADING.md).
