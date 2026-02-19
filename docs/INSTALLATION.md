# Installation

This guide covers installing Doctrine Encrypt Bundle in a Symfony application.

## Requirements

The bundle is **compatible with Symfony 7 and 8** (and PHP 8.1+).

- **PHP** >= 8.1
- **Symfony** ^7.0 || ^8.0
- **Doctrine ORM** ^2.15 || ^3.0
- **paragonie/halite** (included) and **paragonie/sodium_compat** (included); for Defuse, you must require `defuse/php-encryption` yourself.
- **ext-sodium** is required for Halite (or the bundle can rely on sodium_compat where applicable).

## Install with Composer

```bash
composer require nowo-tech/doctrine-encrypt-bundle
```

Use a constraint such as `^1.0` to stay on the current major version.

## Register the bundle

### With Symfony Flex

If you use Symfony Flex and the bundle is installed from Packagist, the recipe (once merged in [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib)) will register the bundle and create `config/packages/nowo_doctrine_encrypt.yaml` automatically. The recipe source is in the bundle repo under `Recipe/`. Until the recipe is on the Flex server, register the bundle and config manually as below.

### Manual registration

1. **Register the bundle** in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class => ['all' => true],
];
```

2. **Create configuration** (optional). Create `config/packages/nowo_doctrine_encrypt.yaml`:

```yaml
nowo_doctrine_encrypt:
    encryptor_class: Halite   # or Defuse
    secret_directory_path: '%kernel.project_dir%'
```

All keys are optional; defaults are applied if the file is omitted.

## Using Defuse

If you want to use Defuse instead of Halite:

```bash
composer require defuse/php-encryption ^2.1
```

Then set in config:

```yaml
nowo_doctrine_encrypt:
    encryptor_class: Defuse
```

## Secret key

The bundle stores a secret key in a file under `secret_directory_path` (e.g. `.Halite.key` or `.Defuse.key`). You can generate it with:

```bash
php bin/console doctrine:encrypt:generate-secret-key
```

(Command name may vary; list commands with `php bin/console list doctrine`.)

**Important:** Add the key file to `.gitignore`:

```gitignore
.Halite.key
.Defuse.key
```

## Next steps

- [Configuration](CONFIGURATION.md) — all options explained.
- [Usage](USAGE.md) — how to mark entity properties as encrypted.
- [Commands](COMMANDS.md) — encrypt/decrypt database and status.
