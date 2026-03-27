# Installation

This guide covers installing Doctrine Encrypt Bundle in a Symfony application.

## Table of contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Register the bundle](#register-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Manual registration](#manual-registration)
- [Using Defuse](#using-defuse)
- [Secret key](#secret-key)
- [FrankenPHP (runtime and worker mode)](#frankenphp-runtime-and-worker-mode)
- [Next steps](#next-steps)

## Requirements

The bundle is **compatible with Symfony 7 and 8** (and PHP 8.1+).

- **PHP** >= 8.1
- **Symfony** ^7.0 || ^8.0
- **Doctrine ORM** ^2.15 || ^3.0
- **paragonie/halite** (included) and **paragonie/sodium_compat** (included); for Defuse, you must require `defuse/php-encryption` yourself.
- **ext-sodium** is recommended for Halite (performance); **paragonie/sodium_compat** is required by Halite and provides a fallback when the extension is missing.

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
    default_config: default
    configs:
        default:
            encryptor_class: Halite   # or Defuse
            secret_directory_path: '%kernel.project_dir%'
```

If the file is omitted, the bundle uses a single default config (Halite, `%kernel.project_dir%`). See [Configuration](CONFIGURATION.md) for multiple configs.

## Using Defuse

If you want to use Defuse instead of (or in addition to) Halite:

```bash
composer require defuse/php-encryption ^2.1
```

Then set in config under the relevant config entry:

```yaml
nowo_doctrine_encrypt:
    default_config: default
    configs:
        default:
            encryptor_class: Defuse
            secret_directory_path: '%kernel.project_dir%'
```

## Secret key

The bundle stores a secret key per config, e.g. `.Halite.default.key` or `.Defuse.default.key` under `secret_directory_path`. You can generate the default key with:

```bash
php bin/console doctrine:encrypt:generate-secret-key
```

(Command name may vary; list commands with `php bin/console list doctrine`.)

**Important:** Add key files to `.gitignore`:

```gitignore
.Halite.key
.Defuse.key
.Halite.*.key
.Defuse.*.key
```

## FrankenPHP (runtime and worker mode)

The bundle is **compatible with FrankenPHP** in both modes:

- **Runtime (HTTP)**  
  Works like with PHP-FPM or any other SAPI: encryption/decryption runs on Doctrine lifecycle events (postLoad, preFlush, etc.). No request or session state is used; encryptors are stateless beyond loading the key (file or env) at first use.

- **Worker mode**  
  Also supported. The bundle does not rely on `$_GET`/`$_POST`/session or on per-request globals. The subscriber’s internal decryption cache is cleared on `postFlush`, so it does not grow across requests. Encryptors load the key once and keep it in memory, which is suitable for long-lived workers.

**Recommendations for worker mode:**

- Follow Symfony/FrankenPHP good practice: limit the number of requests per worker (e.g. `frankenphp_loop_max` or `MAX_REQUESTS`) so workers are recycled and memory is released.
- If you run Messenger (or other job consumers) in the same worker process, keep resetting or clearing the EntityManager between messages to avoid leaking entity state; the bundle does not add extra requirements beyond that.

No extra configuration or code is required for FrankenPHP.

## Next steps

- [Configuration](CONFIGURATION.md) — all options explained.
- [Usage](USAGE.md) — mark entity properties as encrypted; **EncryptUtil** and **MaskUtil** (PHP); Twig filters **`|decrypt`** and **`|mask`**; embedded entities.
- [Commands](COMMANDS.md) — status, encrypt/decrypt database, generate secret key, rotate keys.
