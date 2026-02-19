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

## [1.0.0] - 2025-02-19

First release under **nowo-tech**. Fork from `hec-franco/doctrine-encrypt-bundle` with Halite and Defuse encryptors, Doctrine ORM 2.x/3.x, and attribute/annotation support.

### Added

- **nowo-tech fork:** Bundle in namespace `Nowo\DoctrineEncryptBundle` and package `nowo-tech/doctrine-encrypt-bundle`.
- Support for Symfony 6, 7 and 8; PHP 8.1+.
- **Multiple encryptors:** Config key `configs` (e.g. `personal_data`, `financial_data`) with `default_config`; each config has its own encryptor and key file (`.{Encryptor}.{alias}.key`, e.g. `.Halite.personal_data.key`).
- Attribute `#[Encrypted('alias')]` to assign a field to a named config; use `"default"` for the default config.
- Documentation in `docs/` (CHANGELOG, UPGRADING, RELEASE, CONFIGURATION, INSTALLATION, USAGE, COMMANDS, DEMO, custom_encryptor, etc.).
- Makefile and Docker setup for development (`test`, `cs-check`, `cs-fix`, `qa`).
- Demo applications for Symfony 6, 7 and 8 (entity `SensitiveRecord`, CRUD, fixtures, Halite + Defuse).
- Symfony Flex recipe (config with `configs` only); demo apps excluded from the distributed package (`archive.exclude`, `export-ignore`).

### Changed

- Configuration alias: `ambta_doctrine_encrypt` → `nowo_doctrine_encrypt`. See [UPGRADING.md](UPGRADING.md) when migrating from the old bundle.
- Bundle class: `AmbtaDoctrineEncryptBundle` → `NowoDoctrineEncryptBundle`; register `Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class` in `config/bundles.php`.
- Single-encryptor config (legacy) still supported; recipe and docs recommend `configs` for new setups.

### Fixed

- (None in this release.)

For upgrade steps from `ambta/doctrine-encrypt-bundle` or `hec-franco/doctrine-encrypt-bundle`, see [UPGRADING.md](UPGRADING.md).
