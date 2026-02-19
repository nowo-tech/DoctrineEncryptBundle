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

## Upgrading to 1.0.0

This is the first release of `nowo-tech/doctrine-encrypt-bundle`. If you are migrating from `ambta/doctrine-encrypt-bundle` or `hec-franco/doctrine-encrypt-bundle`, see the section below.

If you are already on this package (e.g. from dev), ensure you use the new config root `nowo_doctrine_encrypt` and register `NowoDoctrineEncryptBundle`. You can keep a single-encryptor config or switch to **multiple encryptors** via `configs` and `default_config`; see [CONFIGURATION.md](CONFIGURATION.md).

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

   # After
   nowo_doctrine_encrypt:
       encryptor_class: Halite
       secret_directory_path: '%kernel.project_dir%'
   ```

5. **Secret key files**  
   With a single-encryptor config, key file names are unchanged (e.g. `.Halite.key`, `.Defuse.key`). If you later switch to `configs`, each config uses a key file like `.{Encryptor}.{alias}.key` (e.g. `.Halite.personal_data.key`). See [CONFIGURATION.md](CONFIGURATION.md).

**Optional:** You can adopt the multi-encryptor setup (`configs` + `default_config`) to use different encryptors per entity property; the bundle remains backward compatible with the single-encryptor format above.

After making these changes, run your test suite and the bundle’s console commands to ensure everything works.
