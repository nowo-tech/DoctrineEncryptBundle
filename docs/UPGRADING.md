# Upgrade Guide

This guide explains how to upgrade the Doctrine Encrypt Bundle between versions. For a list of changes in each version, see [CHANGELOG.md](CHANGELOG.md).

## Table of contents

- [General upgrade process](#general-upgrade-process)
- [Upgrading to 2.3.6](#upgrading-to-236)
  - [Optional notes](#optional-notes)
- [Upgrading to 2.3.5](#upgrading-to-235)
  - [Optional notes](#optional-notes)
- [Upgrading to 2.3.4](#upgrading-to-234)
  - [Optional notes](#optional-notes)
- [Upgrading to 2.3.3](#upgrading-to-233)
  - [Optional notes](#optional-notes)
- [Upgrading to 2.3.2](#upgrading-to-232)
  - [Optional notes](#optional-notes)
- [Upgrading to 2.3.1](#upgrading-to-231)
  - [Demo apps (maintainers / contributors only)](#demo-apps-maintainers--contributors-only)
  - [Maintainer tooling](#maintainer-tooling)
- [Upgrading to 2.3.0](#upgrading-to-230)
- [Upgrading to 2.2.3](#upgrading-to-223)
- [Upgrading to 2.2.2](#upgrading-to-222)
- [Upgrading to 2.2.1](#upgrading-to-221)
- [Upgrading to 2.2.0](#upgrading-to-220)
- [Upgrading to 2.1.0](#upgrading-to-210)
  - [Optional: MySQL AES interoperability](#optional-mysql-aes-interoperability)
- [Upgrading to 2.0.10](#upgrading-to-2010)
- [Upgrading to 2.0.9](#upgrading-to-209)
- [Upgrading to 2.0.8](#upgrading-to-208)
- [Upgrading to 2.0.7](#upgrading-to-207)
- [Upgrading to 2.0.6](#upgrading-to-206)
- [Upgrading to 2.0.5](#upgrading-to-205)
- [Upgrading to 2.0.4](#upgrading-to-204)
- [Upgrading to 2.0.3](#upgrading-to-203)
- [Upgrading to 2.0.2](#upgrading-to-202)
- [Upgrading to 2.0.1](#upgrading-to-201)
- [Upgrading to 2.0.0](#upgrading-to-200)
- [Upgrading to 1.0.0](#upgrading-to-100)
- [Upgrading from ambta/doctrine-encrypt-bundle or hec-franco/doctrine-encrypt-bundle](#upgrading-from-ambtadoctrine-encrypt-bundle-or-hec-francodoctrine-encrypt-bundle)

## General upgrade process

1. **Back up configuration**  
   Back up `config/packages/nowo_doctrine_encrypt.yaml` (or wherever you configure the bundle) before upgrading.

2. **Check the changelog**  
   Review [CHANGELOG.md](CHANGELOG.md) for the target version to see new features, changes, and breaking changes.

3. **Update the package**  
   Run:
   ```bash
   composer update nowo-tech/doctrine-encrypt-bundle
   ```

4. **Apply configuration and code changes**  
   If the new version introduces or changes config options or namespaces, update your config and PHP code (see version-specific sections below).

5. **Clear cache**  
   ```bash
   php bin/console cache:clear
   ```

6. **Test**  
   Verify that encrypted entities are still read/written correctly and that console commands work.

---

## Upgrading to 2.3.10

From **2.3.9** — No application upgrade steps (MysqlAes prod-guard regression tests + SECURITY.md sync).

```bash
composer update nowo-tech/doctrine-encrypt-bundle
```

## Upgrading to 2.3.9

From **2.3.8** — If any `nowo_doctrine_encrypt.profiles.*.encryptor` is `MysqlAes` in production, switch to Halite/Defuse and re-encrypt data before deploying.

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear --env=prod
```

## Upgrading to 2.3.8

No application upgrade steps.

```bash
composer update nowo-tech/doctrine-encrypt-bundle
```

## Upgrading to 2.3.7

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`). Continue requiring `nowo-tech/doctrine-encrypt-bundle` as before.

## Upgrading to 2.3.6

No breaking changes for applications. Maintainer/CI Makefiles now tolerate Compose V2 and standalone checkouts without monorepo `.scripts/`.

### Optional notes

- Prefer Docker Compose V2 (`docker compose`). `make update-deps` remains available only when the monorepo helper Makefiles are present.

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.3.5

No breaking changes for applications that use the bundle via Composer with Halite, Defuse, or MysqlAes as documented.

### Optional notes

- **Maintainers:** PHPStan baseline is empty; CI runs PHPStan on every push. Use `make demo-smoke` (or the `demo-smoke` workflow on tags) to verify the Symfony 8 demo boots with HTTP 200. See [PHPSTAN.md](PHPSTAN.md) and [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.3.4

No breaking changes and no Composer/runtime changes for applications.

### Optional notes

- **Maintainers:** GitHub repository **About** (Description, Website, Topics) was filled per REQ-DOCS-018. Re-check with:
  ```bash
  gh repo view nowo-tech/DoctrineEncryptBundle --json description,homepageUrl,repositoryTopics
  ```

Update as usual (optional; no code delta vs 2.3.3):

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.3.3

No breaking changes for applications that use the bundle via Composer with Halite, Defuse, or MysqlAes as documented.

### Optional notes

- **Twig `|decrypt`:** output remains subject to Twig auto-escaping (no `is_safe: html`). Prefer Halite/Defuse for new data; MysqlAes stays available but `@deprecated` for new production use.
- **Maintainers / demos:** Symfony 8 demo image is FrankenPHP **PHP 8.5** (`dunglas/frankenphp:1-php8.5-alpine`). See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) smoke check and [PHPSTAN.md](PHPSTAN.md) for baseline policy.

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.3.2

No breaking changes for applications that use the bundle via Composer with Halite, Defuse, or MysqlAes as documented.

### Optional notes

- **Custom subclasses:** most concrete encryptors, commands, Twig extensions, and DI classes are now **`final`**. Implement `EncryptorInterface` (or compose services) instead of extending built-in encryptor/command classes. `DoctrineEncryptSubscriber` remains non-final for command/test collaboration.
- **Maintainers:** `make down-dev`, `make check-open-prs`, FrankenPHP `FRANKENPHP_MODE`, and PHPStan FrankenPHP rules are available; see [GITHUB_CI.md](GITHUB_CI.md) and [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).
- **Coverage:** see [COVERAGE.md](COVERAGE.md) for justified PHPUnit exclusions (includable `src/` is at 100%).

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.3.1

No breaking changes for applications using the bundle via Composer.

### Demo apps (maintainers / contributors only)

- The **`demo/symfony7`** project was removed. Use **`demo/symfony8`** (default port **8008**).
- If you still need a Symfony 7 sample, clone an older tag (e.g. `v2.3.0`) or keep a local copy.

### Maintainer tooling

- Optional: `make setup-hooks` and `make check-no-cursor-coauthor` (REQ-GIT-001). See [GITHUB_CI.md](GITHUB_CI.md).

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.3.0

Configuration keys were renamed to match AuditKit-style naming:

| Old (still accepted) | New |
|----------------------|-----|
| `default_config` | `default_profile` |
| `configs` | `profiles` |

Container parameters: prefer `nowo_doctrine_encrypt.default_profile` and `nowo_doctrine_encrypt.profiles`. Legacy `nowo_doctrine_encrypt.default_config` and `nowo_doctrine_encrypt.configs` are still set to the same values during transition.

**Validation:** `default_profile` must be a key in `profiles`. An unknown default now throws `InvalidArgumentException` (previously fell back to the first profile).

**Action:** Update `config/packages/nowo_doctrine_encrypt.yaml` when convenient. Legacy YAML keys still work via `beforeNormalization`. The `#[Encrypted('alias')]` attribute is unchanged (alias still maps to a profile name).

```yaml
# Before
nowo_doctrine_encrypt:
    default_config: personal_data
    configs:
        personal_data:
            encryptor_class: Halite
            secret_directory_path: '%kernel.project_dir%'

# After
nowo_doctrine_encrypt:
    default_profile: personal_data
    profiles:
        personal_data:
            encryptor_class: Halite
            secret_directory_path: '%kernel.project_dir%'
```

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.2.3

No breaking changes. Patch release (Spec Kit maintainer tooling, Twig `|decrypt` auto-escape fix, MysqlAes PHPDoc deprecation, composer.lock sync).

If your Twig templates relied on the `|decrypt` filter **not** being escaped (previous incorrect `isSafe: ['html']` marking), apply `|raw` only when the decrypted content is trusted HTML:

```twig
{# Before (implicit unescaped output — incorrect) #}
{{ value|decrypt }}

{# After — default: escaped (recommended) #}
{{ value|decrypt }}

{# Only if decrypted HTML is trusted #}
{{ value|decrypt|raw }}
```

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.2.2

No breaking changes. Patch release only (functional test / CI fix for PHP 8.4 + Symfony 8). **No changes required** in your application.

```bash
composer update nowo-tech/doctrine-encrypt-bundle
```

---

## Upgrading to 2.2.1

No breaking changes. Patch release only (`composer.lock` sync + CI matrix fix). Recommended if you installed **2.2.0** and hit CI/`composer validate` issues, or want the Symfony **8.0** / **8.1** CI fix from upstream.

```bash
composer update nowo-tech/doctrine-encrypt-bundle
```

Or pin explicitly:

```bash
composer require nowo-tech/doctrine-encrypt-bundle:^2.2.1
```

---

## Upgrading to 2.2.0

**Breaking changes for legacy environments:**

- **PHP 8.2+** is now required (`require.php` was `>=8.1`). Upgrade PHP before updating the bundle.
- **Symfony 6.x** is no longer declared in `composer.json` (`symfony/*` are **^7.0 \|\| ^8.0** only). If you still run Symfony 6 or PHP 8.1, pin the previous line:

  ```bash
  composer require nowo-tech/doctrine-encrypt-bundle:">=2.0,<2.2"
  ```

  Or upgrade the application to **PHP 8.2+** and Symfony **7.4+** or **8.x**, then:

  ```bash
  composer require nowo-tech/doctrine-encrypt-bundle:^2.2
  ```

No configuration or encryptor changes are required when you already run PHP 8.2+ and Symfony 7.4+ / 8.x.

---

## Upgrading to 2.1.0

No breaking changes for existing applications using Halite or Defuse.

### Optional: MySQL AES interoperability

To use the new **`MysqlAes`** encryptor (compatible with MySQL `AES_ENCRYPT` / `AES_DECRYPT`):

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    configs:
        mysql_aes:
            encryptor_class: MysqlAes
            secret_key_env_var: '%env(MYSQL_AES_KEY)%'
```

Generate a passphrase:

```bash
php bin/console doctrine:encrypt:generate-secret-key mysql_aes
```

Use on entity properties: `#[Encrypted('mysql_aes')]`. For **native SQL** on BLOB columns (no `<ENC>` marker), see [MYSQL_AES.md](MYSQL_AES.md).

Requires PHP **ext-openssl** (typically enabled). Prefer Halite/Defuse for new application-level encryption unless you must interoperate with MySQL functions.

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.10

No breaking changes. This release fixes the Symfony 7.1+ deprecation (Extension class) and corrects COMMANDS.md (batchSize default). No changes required in your application.

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.9

No breaking changes. This release applies code style (PHP-CS-Fixer), internal refactors, and demo/CI updates; no changes required in your application.

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.8

No breaking changes. This release fixes unit tests for Symfony Console 7.0/8.0 compatibility (RotateKeysCommandTest); no changes required in your application.

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.7

No breaking changes. New features:

- **doctrine:encrypt:status** now lists each encrypted property and its config per entity, and shows configured encryptor configs at the end.
- **doctrine:encrypt:rotate-keys** runs the full key rotation (optional backup, decrypt, change keys, re-encrypt) with step-by-step confirmation; use `--backup` and/or `--no-interaction` as needed.
- **doctrine:encrypt:generate-secret-key** accepts **`--force`** to overwrite existing key files without asking.
- **doctrine:decrypt:database** and **doctrine:encrypt:database** accept **`--force`** to skip the confirmation prompt.
- **Config:** Optional `nowo_doctrine_encrypt.batch_size` (default `5`) for encrypt/decrypt database batch size. FrankenPHP is supported (see INSTALLATION.md); no config changes needed.

Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.6

No breaking changes. Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.5

No breaking changes. Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

If you run the test suite from the bundle source, ensure your autoload and PHPUnit config use the `tests/` directory (lowercase); the package already ships with this layout.

---

## Upgrading to 2.0.4

No breaking changes. Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.3

No breaking changes. Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.2

No breaking changes. Update as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.1

No breaking changes. Update and run tests as usual:

```bash
composer update nowo-tech/doctrine-encrypt-bundle
php bin/console cache:clear
```

---

## Upgrading to 2.0.0

**Breaking change:** Symfony 6.x is no longer supported. The bundle requires **Symfony ^7.0 || ^8.0**.

- If your application runs on **Symfony 6.x**, either:
  - Stay on `nowo-tech/doctrine-encrypt-bundle` **^1.0** (e.g. `composer require nowo-tech/doctrine-encrypt-bundle:^1.0`), or
  - Upgrade your application to Symfony 7 or 8, then upgrade the bundle to ^2.0.
- If you are already on **Symfony 7 or 8**, you can upgrade to 2.0 with:
  ```bash
  composer update nowo-tech/doctrine-encrypt-bundle
  ```
  No configuration or code changes are required; only the dropped Symfony 6 support and the removal of the Symfony 6 demo affect this release.

---

## Upgrading to 1.0.0

This is the first release of `nowo-tech/doctrine-encrypt-bundle`. If you are migrating from `ambta/doctrine-encrypt-bundle` or `hec-franco/doctrine-encrypt-bundle`, see the section below.

If you are already on this package (e.g. from dev), ensure you use the new config root `nowo_doctrine_encrypt` and register `NowoDoctrineEncryptBundle`. Use `default_config` and `configs` (one entry for a single encryptor, or several for multiple encryptors); see [CONFIGURATION.md](CONFIGURATION.md).

---

## Upgrading from ambta/doctrine-encrypt-bundle or hec-franco/doctrine-encrypt-bundle

**Breaking changes when moving to nowo-tech/doctrine-encrypt-bundle:**

1. **Package name**  
   - Old: `hec-franco/doctrine-encrypt-bundle` or `ambta/doctrine-encrypt-bundle`  
   - New: `nowo-tech/doctrine-encrypt-bundle`

2. **Namespace**  
   - Old: `Ambta\DoctrineEncryptBundle`  
   - New: `Nowo\DoctrineEncryptBundle`  
   Update any `use` statements and references (e.g. in entities using the `Encrypted` attribute/annotation: `Nowo\DoctrineEncryptBundle\Configuration\Encrypted`).

3. **Bundle registration**  
   - Old: `Ambta\DoctrineEncryptBundle\AmbtaDoctrineEncryptBundle::class`  
   - New: `Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class`  
   Update `config/bundles.php` accordingly.

4. **Configuration key**  
   - Old: `ambta_doctrine_encrypt`  
   - New: `nowo_doctrine_encrypt`  
   Rename your config file from `ambta_doctrine_encrypt.yaml` to `nowo_doctrine_encrypt.yaml` and change the root key inside the file:

   ```yaml
   # Before
   ambta_doctrine_encrypt:
       encryptor_class: Halite
       secret_directory_path: '%kernel.project_dir%'

   # After (single encryptor = one config named "default")
   nowo_doctrine_encrypt:
       default_config: default
       configs:
           default:
               encryptor_class: Halite
               secret_directory_path: '%kernel.project_dir%'
   ```

5. **Secret key files**  
   With the single config above, the key file is `.Halite.default.key`. Each config uses `.{Encryptor}.{alias}.key` (e.g. `.Halite.personal_data.key`). See [CONFIGURATION.md](CONFIGURATION.md).

**Optional:** You can add more entries under `configs` and set `default_config` to choose which one is used for `#[Encrypted]` without an alias.

After making these changes, run your test suite and the bundle’s console commands to ensure everything works.
