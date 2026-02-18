# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **nowo-tech fork:** Bundle moved to namespace `Nowo\DoctrineEncryptBundle` and package `nowo-tech/doctrine-encrypt-bundle`.
- Support for Symfony 6, 7 and 8.
- PHP 8.1+ requirement.
- Documentation in `docs/` (CHANGELOG, UPGRADING, RELEASE, CONFIGURATION, ROADMAP, INSTALLATION, etc.).
- Makefile and Docker setup for development (test, cs-check, cs-fix, qa).
- Demo applications for Symfony 6, 7 and 8.

### Changed

- Configuration alias: `ambta_doctrine_encrypt` → `nowo_doctrine_encrypt`. If you were using the old bundle, see [UPGRADING.md](UPGRADING.md).
- Bundle class: `AmbtaDoctrineEncryptBundle` → `NowoDoctrineEncryptBundle`; register `Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class` in `config/bundles.php`.

### Fixed

- (None yet.)

---

## [1.0.0] - YYYY-MM-DD

Initial release under nowo-tech. Based on the previous fork (hec-franco/doctrine-encrypt-bundle) with Halite and Defuse encryptors, Doctrine ORM 2.x/3.x, and attribute/annotation support.

For upgrade steps from `ambta/doctrine-encrypt-bundle` or `hec-franco/doctrine-encrypt-bundle`, see [UPGRADING.md](UPGRADING.md).
