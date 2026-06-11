# Upgrade Guide

This guide explains how to upgrade the Doctrine Encrypt Bundle between versions. For a list of changes in each version, see [CHANGELOG.md](CHANGELOG.md).

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
