# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[2.3.5] - 2026-07-29](#235-2026-07-29)
  - [Added](#added)
  - [Changed](#changed)
  - [Documentation](#documentation)
- [[2.3.4] - 2026-07-28](#234-2026-07-28)
  - [Changed](#changed)
- [[2.3.3] - 2026-07-28](#233-2026-07-28)
  - [Added](#added)
  - [Changed](#changed)
  - [Removed](#removed)
- [[2.3.2] - 2026-07-28](#232-2026-07-28)
  - [Added](#added)
  - [Changed](#changed)
- [[2.3.1] - 2026-07-20](#231-2026-07-20)
  - [Removed](#removed)
  - [Added](#added)
  - [Changed](#changed)
- [[2.3.0] - 2026-07-18](#230-2026-07-18)
  - [Changed](#changed)
- [[2.2.3] - 2026-07-09](#223-2026-07-09)
  - [Added](#added)
  - [Changed](#changed)
- [[2.2.2] - 2026-06-11](#222-2026-06-11)
  - [Fixed](#fixed)
- [[2.2.1] - 2026-06-11](#221-2026-06-11)
  - [Fixed](#fixed)
- [[2.2.0] - 2026-06-01](#220-2026-06-01)
  - [Fixed](#fixed)
  - [Changed](#changed)
  - [Removed](#removed)
- [[2.1.0] - 2026-06-01](#210-2026-06-01)
  - [Added](#added)
  - [Changed](#changed)
- [[2.0.10] - 2026-02-21](#2010-2026-02-21)
  - [Fixed](#fixed)
  - [Changed](#changed)
- [[2.0.9] - 2026-02-21](#209-2026-02-21)
  - [Changed](#changed)
  - [Fixed](#fixed)
- [[2.0.8] - 2026-02-20](#208-2026-02-20)
  - [Fixed](#fixed)
- [[2.0.7] - 2026-02-20](#207-2026-02-20)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
  - [Tests](#tests)
- [[2.0.6] - 2026-02-20](#206-2026-02-20)
  - [Added](#added)
  - [Fixed](#fixed)
- [[2.0.5] - 2026-02-20](#205-2026-02-20)
  - [Added](#added)
  - [Changed](#changed)
- [[2.0.4] - 2026-02-19](#204-2026-02-19)
  - [Fixed](#fixed)
- [[2.0.3] - 2026-02-19](#203-2026-02-19)
  - [Changed](#changed)
- [[2.0.2] - 2026-02-19](#202-2026-02-19)
  - [Fixed](#fixed)
- [[2.0.1] - 2026-02-19](#201-2026-02-19)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)
- [[2.0.0] - 2026-02-19](#200-2026-02-19)
  - [Removed](#removed)
  - [Changed](#changed)
- [[1.0.0] - 2026-02-19](#100-2026-02-19)
  - [Added](#added)
  - [Changed](#changed)
  - [Fixed](#fixed)

## [Unreleased]

---

## [2.3.5] - 2026-07-29

### Added

- **`make demo-smoke`** + `.github/workflows/demo-smoke.yml`: boot `demo/symfony8` and assert HTTP 200 (REQ-TEST-011).
- PHPStan bootstrap (`phpstan/bootstrap.php` + stubs) for optional `AsDoctrineListener` when doctrine-bundle is absent.
- CI job **PHPStan** in `.github/workflows/ci.yml` (REQ-CS-006).

### Changed

- **REQ-CS-006:** `phpstan-baseline.neon` emptied (`ignoreErrors: []`); analyse clean at level 8 with `treatPhpDocTypesAsCertain: false`. Types/PHPDoc tightened across `src/` and tests.
- `composer.json` keywords: add `php`, `frankenphp`.
- README FrankenPHP banner sentence uses the canonical short form (REQ-DOCS-017).
- [docs/PHPSTAN.md](PHPSTAN.md) updated for empty baseline policy.

### Documentation

- Table of contents on long docs (REQ-DOCS-005).
- INSTALLATION: recipe path corrected to `.symfony/recipe/`.
- DEMO-FRANKENPHP: `make demo-smoke` (REQ-TEST-011).

No application configuration changes required. See [UPGRADING.md](UPGRADING.md#upgrading-to-235).

---

## [2.3.4] - 2026-07-28

### Changed

- **REQ-DOCS-018:** GitHub About panel set — plain-text Description (aligned with `composer.json`), Website `https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle`, Topics (`php`, `symfony`, `symfony-bundle`, `frankenphp`, `doctrine`, `encryption`, `halite`, `defuse`, `gdpr`, `key-rotation`). Metadata is on GitHub only (not in the git tree).

No application configuration or runtime changes. See [UPGRADING.md](UPGRADING.md#upgrading-to-234).

---

## [2.3.3] - 2026-07-28

### Added

- **REQ-CS-006:** [docs/PHPSTAN.md](PHPSTAN.md) documents level 8 + justified baseline categories (no blanket `ignoreErrors`).
- **REQ-DOCS-004:** README badges for GitHub stars and Coverage.
- **REQ-SEC-002:** Release security checklist includes AI audit (REQ-SEC-004).

### Changed

- **REQ-DEMO-010:** Demo FrankenPHP image bumped to `dunglas/frankenphp:1-php8.5-alpine`; docs/smoke notes updated.
- **REQ-PKG-004:** Shorter Packagist `description` and focused `keywords`.
- **Security:** Twig `|decrypt` dead `is_safe html` legacy code removed; SECURITY.md documents auto-escape + MysqlAes deprecation.
- **Demo Compose:** removed Flex leftover `database_data` volume fragment.
- **PHPStan:** baseline regenerated to absorb `missingType.iterableValue` (replacing blanket ignore).

### Removed

- Legacy `.travis.yml` (CI is GitHub Actions only).

No application configuration changes required. See [UPGRADING.md](UPGRADING.md#upgrading-to-233).

---

## [2.3.2] - 2026-07-28

### Added

- **REQ-CS-005:** `nowo-tech/phpstan-frankenphp` (require-dev) with classic + worker rulesets; PHPStan baseline for justified findings.
- **REQ-DOCS-017:** FrankenPHP Friendly Worker Mode banner in README (`docs/images/frankenphp-friendly.png`).
- **REQ-MAKE-007:** `make down-dev` (alias of `down --remove-orphans`).
- **REQ-REL-003:** `.scripts/check-open-prs.sh` wired into `release-check`.
- **REQ-DEMO-010:** `FRANKENPHP_MODE` (`classic` \| `worker`) in demo `.env.example`, Compose, and entrypoint.
- **REQ-TEST-003:** [docs/COVERAGE.md](COVERAGE.md) documents justified PHPUnit exclusions; includable `src/` at **100%** line coverage.

### Changed

- **REQ-DOCS-016:** `demo/README.md` translated to English.
- **REQ-DOCS-002:** README `## Documentation` uses canonical base-link order; `GITHUB_CI.md` under Additional documentation.
- **REQ-SF-005:** `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in `phpunit.xml.dist`.
- **REQ-PHP-001:** concrete services marked `final` (subscriber intentionally non-final for command/test collaboration). Prefer `EncryptorInterface` / composition for custom encryptors.
- **Documentation:** README demo section uses `FRANKENPHP_MODE`; coverage section reports 100.00%.
- **Dev dependencies / CI:** php-cs-fixer, rector, phpstan, and `actions/cache` bumps (Dependabot).

No application configuration changes required for typical Halite/Defuse/MysqlAes setups. See [UPGRADING.md](UPGRADING.md#upgrading-to-232).

---

## [2.3.1] - 2026-07-20

### Removed

- **Demo Symfony 7:** removed `demo/symfony7`. Use `demo/symfony8` (port **8008**) as the reference demo app. Docs (`DEMO.md`, `DEMO-FRANKENPHP.md`, README, etc.) updated accordingly.

### Added

- **REQ-GIT-001:** CI job `git-hygiene`, `.scripts/check-no-cursor-coauthor.sh`, `.scripts/strip-cursor-coauthor-from-history.sh`, `.githooks/commit-msg`, Cursor rule `01-git-commits.mdc`, and [GITHUB_CI.md](GITHUB_CI.md). Makefile targets: `setup-hooks`, `check-no-cursor-coauthor`, `strip-cursor-coauthor-from-history` (wired into `release-check`).
- **Code of Conduct:** [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) (Contributor Covenant); linked from README and [CONTRIBUTING.md](CONTRIBUTING.md).

### Changed

- **Documentation:** README wording aligned with `default_profile` / `profiles` (2.3.0 rename).
- **PHPUnit coverage:** exclude high-branch CLI/subscriber/Halite/Configuration paths covered by functional tests (aggregate coverage focus).
- **`#[Encrypted]`:** removed obsolete doctrine/annotations `@Annotation` / `@Target` PHPDoc (attribute-only).

No application configuration changes required. See [UPGRADING.md](UPGRADING.md#upgrading-to-231).

---

## [2.3.0] - 2026-07-18

### Changed

- **Config naming (AuditKit-style):** `default_config` / `configs` renamed to `default_profile` / `profiles`. Legacy YAML keys (`default_config`, `configs`) and container parameters (`nowo_doctrine_encrypt.default_config`, `nowo_doctrine_encrypt.configs`) remain accepted during transition. Docs, Flex recipe, and demos use the new keys.
- **Validation:** `default_profile` must be a key in `profiles`. An unknown default now throws `InvalidArgumentException` (previously fell back to the first profile).

The `#[Encrypted('alias')]` attribute is unchanged. See [UPGRADING.md](UPGRADING.md#upgrading-to-230).

---

## [2.2.3] - 2026-07-09

### Added

- **GitHub Spec Kit:** `.specify/` tooling, Cursor Agent skills (`.cursor/skills/speckit-*`), and baseline spec in `specs/001-baseline/` (100% `src/` inventory). See [SPEC-KIT.md](SPEC-KIT.md) and [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md).
- **CodeRabbit:** review configuration (`.coderabbit.yaml`) and GitHub Actions workflow for automated PR review.

### Changed

- **Documentation:** [SPEC-KIT.md](SPEC-KIT.md) (install, structure, Cursor usage); README link under Documentation.
- **MysqlAes:** PHPDoc `@deprecated since 2.1.0` — not recommended for new production deployments (AES-128-ECB); prefer Halite or Defuse. No runtime change.
- **Twig `|decrypt` filter:** removed `isSafe: ['html']` so decrypted output is auto-escaped by Twig (security fix). Use `|raw` only when the decrypted value is trusted HTML.
- **composer.lock:** dependency sync for the bundle root and demo apps (`symfony7`, `symfony8`).

No configuration changes required. See [UPGRADING.md](UPGRADING.md#upgrading-to-223).

---

## [2.2.2] - 2026-06-11

### Fixed

- **Functional tests (CI):** enable Doctrine ORM **native lazy objects** on PHP **8.4+** when running the test suite with Symfony **8.x**. Symfony 8 `var-exporter` no longer ships LazyGhost helpers; fixes 14 functional test errors on the PHP 8.4 × Symfony 8.0 / 8.1 matrix cells.

No application or configuration changes. See [UPGRADING.md](UPGRADING.md#upgrading-to-222).

---

## [2.2.1] - 2026-06-11

### Fixed

- **composer.lock:** `platform.php` and `content-hash` synced with `composer.json` (`>=8.2`); fixes `composer validate --strict` in CI.
- **CI:** Symfony **8.0** / **8.1** matrix cells use matching `symfony/var-exporter` (`^8.0` / `^8.1`) instead of `^7.4`, which blocked dependency resolution.

No application changes required. See [UPGRADING.md](UPGRADING.md#upgrading-to-221).

---

## [2.2.0] - 2026-06-01

### Fixed

- **GenerateSecretKeyCommandTest:** assertions updated for MysqlAes in supported-encryptor messages (2.1.0 gap).

### Changed

- **Requirements (Nowo bundle specs):** minimum **PHP 8.2+**; `symfony/*` constraints **^7.0 \|\| ^8.0** (Symfony 6 removed from `composer.json`, aligned with 2.0 release notes). CI matrix tests Symfony **7.4**, **8.0**, and **8.1** on PHP 8.2–8.5.
- **Documentation:** README badges and requirements, [INSTALLATION.md](INSTALLATION.md), [ROADMAP.md](ROADMAP.md) — PHP 8.2+ and Symfony 7.4 \| 8.0 \| 8.1+.

### Removed

- **Symfony 6** from declared `symfony/*` Composer constraints (was still present in `composer.json` after 2.0).

**Breaking:** PHP **8.2+** is required; Symfony 6 is no longer installable with current constraints. See [UPGRADING.md](UPGRADING.md#upgrading-to-220).

---

## [2.1.0] - 2026-06-01

### Added

- **`MysqlAes` encryptor** — AES-128-ECB compatible with MySQL [`AES_ENCRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) / [`AES_DECRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) (default `block_encryption_mode`). Configure with `encryptor_class: MysqlAes` and a passphrase (env or key file). See [MYSQL_AES.md](MYSQL_AES.md).
- **`doctrine:encrypt:generate-secret-key`** — generates a passphrase for `MysqlAes` configs.
- **Documentation:** [MYSQL_AES.md](MYSQL_AES.md) (native SQL, LIKE demos, preset fixtures); [PERFORMANCE.md](PERFORMANCE.md) (encryptors and query patterns compared).
- **Demos (Symfony 7 & 8):** `/mysql-aes-note` — Doctrine + repository SQL paths, LIKE filters, preset fixtures, **DB values** view (encrypted vs decrypted). Secret messages: `/secret-message/db-values` with the same raw/decrypted tabs. Demo Docker: optional MySQL 8 service, `pdo_mysql`, Bootstrap 5 form theme.

### Changed

- **Documentation:** [CONFIGURATION.md](CONFIGURATION.md), [USAGE.md](USAGE.md), [DEMO.md](DEMO.md), [ROADMAP.md](ROADMAP.md), README — MysqlAes, performance notes, demo troubleshooting (Composer advisories, Symfony 7.4).
- **Demos:** Symfony 7 demo targets **7.4.***; `composer.lock` synced (e.g. twig-inspector-bundle); entrypoint avoids restart loop on failed `composer install`.

No breaking changes for existing Halite/Defuse configs. See [UPGRADING.md](UPGRADING.md#upgrading-to-210).

---

## [2.0.10] - 2026-02-21

### Fixed

- **Symfony 7.1+:** `DoctrineEncryptExtension` now extends `Symfony\Component\DependencyInjection\Extension\Extension` instead of `Symfony\Component\HttpKernel\DependencyInjection\Extension`. The latter is considered internal since Symfony 7.1 and will be deprecated in 8.1; this change removes the deprecation notice.

### Changed

- **Documentation:** [COMMANDS.md](COMMANDS.md) — batchSize default for encrypt/decrypt database is documented as 5 (from config); link to CONFIGURATION added.

No upgrade steps required from 2.0.9. See [UPGRADING.md](UPGRADING.md#upgrading-to-2010).

---

## [2.0.9] - 2026-02-21

### Changed

- **Code style:** PHP-CS-Fixer configuration updated and applied across the codebase (src, tests, demos).
- **Internal:** Refactors in commands, encryptors, subscriber, mapping, Twig extensions and utilities; tests aligned with updated style and structure.
- **Demos:** Symfony 7 and 8 demo apps updated (config, controllers, entities, forms, fixtures, bootstrap); added `demo/Makefile` for running demos.
- **CI & tooling:** GitHub Actions workflow, root and demo Makefiles, and docker-compose adjustments.

### Fixed

- **PHP 8.1+:** `DefuseEncryptorTest::testGenerateKey` and `HaliteEncryptorTest::testGenerateKey` no longer pass an int to `md5()`; use `md5((string) time())` so the test suite passes with strict types.

No upgrade steps required from 2.0.8. See [UPGRADING.md](UPGRADING.md#upgrading-to-209).

---

## [2.0.8] - 2026-02-20

### Fixed

- **Symfony Console 7.0 / 8.0:** `RotateKeysCommandTest` no longer uses `Application::add()` (removed in Symfony Console 8.0). Tests now register commands via `FactoryCommandLoader` and `setCommandLoader()`, and set the application on the rotate command with `setApplication()` so subcommands resolve correctly. This fixes the 8 failing unit tests when running on PHP 8.4 with Symfony 7.0 (e.g. in CI).

No upgrade steps required from 2.0.7. See [UPGRADING.md](UPGRADING.md#upgrading-to-208).

---

## [2.0.7] - 2026-02-20

### Added

- **doctrine:encrypt:status:** For each entity, lists which properties are encrypted and their **config** (e.g. `email (config: personal_data)`). At the end, shows **configured encryptor configs** (config name, encryptor class Halite/Defuse, and which is default).
- **doctrine:encrypt:rotate-keys:** New command to run the full key rotation: optional backup of key files (`--backup`), full decrypt, key change (generate new key files or prompt for .env update), then re-encrypt. Each step asks for confirmation unless `--no-interaction` is used. See [COMMANDS.md](COMMANDS.md#rotate-keys) and [KEY_ROTATION.md](KEY_ROTATION.md).
- **doctrine:encrypt:generate-secret-key:** Option **`--force`** to overwrite existing key file(s) without asking.
- **doctrine:decrypt:database** and **doctrine:encrypt:database:** Option **`--force`** to skip the confirmation prompt (useful when calling from scripts or from `rotate-keys` with `--no-interaction`).
- **Configuration:** `nowo_doctrine_encrypt.batch_size` (default `5`) for the default batch size of encrypt/decrypt database commands; overridable per run via the `batchSize` argument.
- **Documentation:** [INSTALLATION.md](INSTALLATION.md) — new section **FrankenPHP (runtime and worker mode)**: compatibility with FrankenPHP in both modes, no extra config; recommendations (e.g. limit requests per worker). README and DEMO.md reference FrankenPHP compatibility; demo Caddyfiles include a short comment.

### Changed

- **doctrine:decrypt:database** and **doctrine:encrypt:database:** Use **raw SQL** for encrypt/decrypt (no Doctrine lifecycle events); default batch size is **5** (configurable via `nowo_doctrine_encrypt.batch_size`).
- **Documentation:** COMMANDS.md documents status output (per-entity properties and configs, configured configs), rotate-keys command, and `--force` on generate-secret-key, decrypt and encrypt. KEY_ROTATION.md and SECURITY.md reference the new rotate-keys command. README and INSTALLATION next steps mention rotate keys. ROADMAP updated. DEMO.md has a FrankenPHP subsection; demo Caddyfiles reference INSTALLATION § FrankenPHP.

### Fixed

- **RotateKeysCommandTest:** Test for “no configs” now builds the command with `DoctrineEncryptSubscriber(null)` and `registry = null` so that an empty registry is never passed to the subscriber (which would call `getDefault()` and throw).

### Tests

- **Coverage:** Unit tests added for `RotateKeysCommand` (full rotation, backup with SQLite, backup-db-cmd, env configs, paused step, no configs), for `AbstractCommand` (`getEncryptedTableInfo`, `getColumnNameFromMetadata`, `getRowValue`), for decrypt/encrypt database commands (executeStatement path, row with null id, decrypt throw fallback), for `DoctrineEncryptStatusCommand` (registry null, configured configs, none), and for `GenerateSecretKeyCommand` (config argument with unsupported encryptor). Code coverage for **lines** is ≥ 95%.

No upgrade steps required from 2.0.6. See [UPGRADING.md](UPGRADING.md#upgrading-to-207).

---

## [2.0.6] - 2026-02-20

### Added

- **Tests:** Unit test for `HaliteEncryptor` when the key path is a directory (covers `normalizeKeyFile()` early return). Unit test for `GenerateSecretKeyCommand` when the key file is created in a non-existent directory (covers `createKey()` with `mkdir(..., true)`). Improves code coverage (lines ≥ 95%).

### Fixed

- **PHPUnit:** The unit test that uses a directory as key path no longer triggers a PHP Notice (Halite emits a notice when reading a path that is a directory); the test suppresses it with `@` on the call that triggers it so the test suite runs without notices.

No upgrade steps required from 2.0.5. See [UPGRADING.md](UPGRADING.md#upgrading-to-206).

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

## [1.0.0] - 2026-02-19

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
