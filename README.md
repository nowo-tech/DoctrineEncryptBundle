# Doctrine Encrypt Bundle

[![CI](https://github.com/nowo-tech/doctrine-encrypt-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/doctrine-encrypt-bundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/doctrine-encrypt-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/doctrine-encrypt-bundle.svg)](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%20%7C%207%20%7C%208-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/doctrine-encrypt-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/doctrine-encrypt-bundle)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/doctrine-encrypt-bundle) so more developers can find it.

**Doctrine Encrypt Bundle** — Encrypt Doctrine entity fields with [Halite](https://github.com/paragonie/halite) or [Defuse](https://github.com/defuse/php-encryption). Uses verified, standardized libraries (no custom crypto). For Symfony 6, 7 and 8 · PHP 8.1+.

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
- ✅ **Halite** (default) and **Defuse** — audited crypto libraries, no custom algorithms
- ✅ Transparent: encrypt on persist/update, decrypt on load
- ✅ Works with **embedded entities** and **inheritance**
- ✅ Console commands: status, generate secret key, encrypt/decrypt database
- ✅ **Symfony Flex** recipe (register bundle + config; see [Recipe/](Recipe/README.md))
- ✅ Compatible with **Doctrine ORM 2.x and 3.x**

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

Create `config/packages/nowo_doctrine_encrypt.yaml` (all keys optional):

```yaml
nowo_doctrine_encrypt:
    encryptor_class: Halite   # or Defuse
    secret_directory_path: '%kernel.project_dir%'
```

**Using Defuse:** `composer require defuse/php-encryption ^2.1`

**Secret key:** Add `.Halite.key` and `.Defuse.key` to `.gitignore`. Generate with:

```bash
php bin/console doctrine:encrypt:generate-secret-key
```

Full options: [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Usage

Mark entity properties with the `Encrypted` attribute:

```php
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
class User
{
    #[ORM\Column(type: 'string')]
    #[Encrypted]
    private ?string $email = null;
}
```

Values are encrypted on persist/update and decrypted on load. See [docs/USAGE.md](docs/USAGE.md) for embedded entities, inheritance, and examples.

## Documentation

| Document | Description |
|----------|-------------|
| [**Installation**](docs/INSTALLATION.md) | Requirements, Flex and manual install, secret key, IDE (optional) |
| [**Configuration**](docs/CONFIGURATION.md) | All options and defaults |
| [**Usage**](docs/USAGE.md) | Encrypted attribute, embedded entities, inheritance |
| [**Commands**](docs/COMMANDS.md) | Status, generate key, encrypt/decrypt database |
| [**Demo**](docs/DEMO.md) | Demo projects (Symfony 6/7/8) and how to run them |
| [**Changelog**](docs/CHANGELOG.md) | Version history |
| [**Upgrading**](docs/UPGRADING.md) | Upgrade notes between versions |
| [**Roadmap**](docs/ROADMAP.md) | Vision and future ideas |
| [**Security**](docs/SECURITY.md) | Reporting vulnerabilities |
| [**Contributing**](docs/CONTRIBUTING.md) | How to contribute and code style |
| [**Release**](docs/RELEASE.md) | Release checklist (for maintainers) |
| [**Custom encryptor**](docs/custom_encryptor.md) | Implement your own encryptor |

## Requirements

- PHP >= 8.1
- Symfony ^6.0 \|\| ^7.0 \|\| ^8.0
- Doctrine ORM ^2.15 \|\| ^3.0
- paragonie/halite (included); for Defuse: `defuse/php-encryption ^2.1`
- ext-sodium recommended for Halite (or sodium_compat)

See [docs/INSTALLATION.md](docs/INSTALLATION.md#requirements) and [docs/UPGRADING.md](docs/UPGRADING.md) for compatibility notes.

## Demo

Demos for Symfony 6, 7 and 8 are in `demo/symfony6`, `demo/symfony7`, `demo/symfony8`. Each runs with **FrankenPHP** and **Caddy**. Quick start: [docs/DEMO.md](docs/DEMO.md).

## Development

Run tests and QA with Docker: `make up && make install && make test` (or `make test-coverage`, `make qa`). Without Docker: `composer install && composer test`. See [Makefile](Makefile) for all targets.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)
