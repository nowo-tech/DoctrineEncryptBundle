# Doctrine Encrypt Bundle

[![CI](https://github.com/nowo-tech/DoctrineEncryptBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/DoctrineEncryptBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/doctrine-encrypt-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/doctrine-encrypt-bundle.svg)](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7%20%7C%208-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/doctrine-encrypt-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/DoctrineEncryptBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

**Symfony bundle to encrypt Doctrine entity fields at rest** using [Halite](https://github.com/paragonie/halite) or [Defuse](https://github.com/defuse/php-encryption)—audited libraries, no custom crypto. For **Symfony 7 and 8** · PHP 8.1+. Suits **GDPR** and compliance (e.g. Art. 32); supports key rotation and [Nowo\AnonymizedBundle](https://github.com/nowo-tech/AnonymizedBundle) for anonymization and erasure.

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/DoctrineEncryptBundle) so more developers can find it.

## Table of contents

- [Quick search terms](#quick-search-terms)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Documentation](#documentation)
- [Requirements](#requirements)
- [Demo](#demo)
- [Development](#development)
- [License & author](#license--author)

## Quick search terms

Looking for **Doctrine encryption**, **encrypt entity fields**, **Halite Symfony**, **Defuse encryption**, **field-level encryption**, **encrypt database column**, **Symfony encrypt attribute**, **Doctrine Encrypted**? You're in the right place.

## Features

- ✅ Encrypt and decrypt entity properties with a single attribute
- ✅ **Multiple encryptor configs** — e.g. `personal_data` (Halite) and `financial_data` (Defuse) in the same app, each with its own key
- ✅ **Halite** and **Defuse** — audited crypto libraries, no custom algorithms
- ✅ Transparent: encrypt on persist/update, decrypt on load
- ✅ **EncryptUtil** — programmatic `encrypt()` / `decrypt()` with optional config name (default or e.g. `financial_data`)
- ✅ **MaskUtil** — mask sensitive values in PHP (e.g. show only last N chars); usable in services
- ✅ **Twig filters** — `|decrypt` (decrypt in templates; optional config: `{{ value|decrypt }}` or `{{ value|decrypt('financial_data') }}`) and `|mask` (mask for display: `{{ value|mask(4) }}` or `{{ value|decrypt|mask(4) }}`)
- ✅ Works with **embedded entities** and **inheritance**
- ✅ Console commands: status, generate secret key, encrypt/decrypt database, **rotate keys** (backup, decrypt, change keys, re-encrypt with confirmations)
- ✅ **Key rotation** — one command or manual steps; combinable with [Nowo\AnonymizedBundle](https://github.com/nowo-tech/AnonymizedBundle) for GDPR-compliant anonymization and erasure
- ✅ **Symfony Flex** recipe (register bundle + config; see [docs/INSTALLATION.md](docs/INSTALLATION.md))
- ✅ Compatible with **Symfony 7 and 8** and **Doctrine ORM 2.x and 3.x**
- ✅ Compatible with **FrankenPHP** (HTTP runtime and optional **worker** mode; demos default to **`APP_ENV=dev`** with **Caddyfile.dev**, i.e. no PHP worker — see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md) and [Installation → FrankenPHP](docs/INSTALLATION.md#frankenphp-runtime-and-worker-mode))

## Installation

```bash
composer require nowo-tech/doctrine-encrypt-bundle
```

[![Install from Packagist](https://img.shields.io/badge/Packagist-install-777BB4?logo=composer)](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle)

With **Symfony Flex**, the recipe (when enabled) registers the bundle and creates the config file automatically. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md) for manual steps.

**Manual registration** in `config/bundles.php`:

```php
<?php

return [
  // ...
  Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/nowo_doctrine_encrypt.yaml`. You can use **one encryptor** (legacy) or **multiple named configs** (recommended).

### Multiple configs (recommended)

Use different encryptors and keys per kind of data (e.g. personal vs financial):

```yaml
nowo_doctrine_encrypt:
  default_config: personal_data  # used when attribute has no config or uses "default"
  configs:
    personal_data:
      encryptor_class: Halite
      secret_directory_path: '%kernel.project_dir%'
    financial_data:
      encryptor_class: Defuse
      secret_directory_path: '%kernel.project_dir%'
```

**Defuse:** `composer require defuse/php-encryption ^2.1`

Key files: one per config, e.g. `.Halite.personal_data.key`, `.Defuse.financial_data.key` in the config’s `secret_directory_path`. Add to `.gitignore`:

```gitignore
.Halite.key
.Defuse.key
.Halite.*.key
.Defuse.*.key
```

Generate keys: `php bin/console doctrine:encrypt:generate-secret-key` (creates missing Halite/Defuse keys for all configs, or pass a config alias). See [docs/CONFIGURATION.md](docs/CONFIGURATION.md) and [docs/COMMANDS.md](docs/COMMANDS.md).

### Single encryptor (one config)

Use one entry under `configs` (e.g. `default`):

```yaml
nowo_doctrine_encrypt:
  default_config: default
  configs:
    default:
      encryptor_class: Halite  # or Defuse
      secret_directory_path: '%kernel.project_dir%'
```

Key file: `.Halite.default.key` (or `.Defuse.default.key`). Full options: [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Usage

Mark entity properties with the `Encrypted` attribute. Use no argument (or `"default"`) for the default config, or the config name when using multiple configs:

```php
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
class User
{
  #[ORM\Column(type: 'string')]
  #[Encrypted]  // or #[Encrypted('default')] — uses default_config
  private ?string $email = null;
}
```

With **multiple configs**, pass the config alias per property:

```php
#[ORM\Column(type: 'string')]
#[Encrypted('personal_data')]
private ?string $email = null;

#[ORM\Column(type: 'string')]
#[Encrypted('financial_data')]
private ?string $iban = null;
```

Values are encrypted on persist/update and decrypted on load. For **programmatic** use: **EncryptUtil** (encrypt/decrypt) and **MaskUtil** (mask for display). In Twig use the **`|decrypt`** and **`|mask`** filters. See [docs/USAGE.md](docs/USAGE.md) for EncryptUtil, MaskUtil, Twig filters, embedded entities, and inheritance.

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Roadmap](docs/ROADMAP.md)

### Additional documentation

- [Demo with FrankenPHP (development and production)](docs/DEMO-FRANKENPHP.md)
- [Example](docs/EXAMPLE.md)
- [Commands](docs/COMMANDS.md)
- [Key rotation](docs/KEY_ROTATION.md)
- [Demo](docs/DEMO.md)
- [Custom encryptor](docs/custom_encryptor.md)

## Requirements

- PHP >= 8.1
- **Symfony 7 or 8** (^7.0 \|\| ^8.0). Current releases of this package require Symfony 7 or 8 (see `composer.json`).
- Doctrine ORM ^2.15 \|\| ^3.0
- paragonie/halite (included); for Defuse: `defuse/php-encryption ^2.1`
- ext-sodium recommended for Halite (or sodium_compat)

See [docs/INSTALLATION.md](docs/INSTALLATION.md#requirements) and [docs/UPGRADING.md](docs/UPGRADING.md) for compatibility notes.

## Demo

Demos for Symfony 7 and 8 are in `demo/symfony7`, `demo/symfony8`. Each runs with **FrankenPHP** and **Caddy** (HTTP on port 80 in the container). **`docker-compose`** defaults to **`APP_ENV=dev`**, so the entrypoint uses **Caddyfile.dev** (no PHP worker; changes visible on refresh). **Worker mode** is for a production-style setup — [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md). Default host ports: **8007** (symfony7), **8008** (symfony8) via `PORT`. Quick start: [docs/DEMO.md](docs/DEMO.md).

## Development

Run tests and QA with Docker: `make up && make install && make test` (or `make test-coverage`, `make qa`). Without Docker: `composer install && composer test`. See [Makefile](Makefile) for all targets.

## Tests and coverage

- Tests: PHPUnit (unit and functional suites)
- PHP: 100%
- TS/JS: N/A
- Python: N/A

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)
