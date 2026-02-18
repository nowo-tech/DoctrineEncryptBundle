# Doctrine Encrypt Bundle – Symfony 7 Demo

Minimal Symfony 7 application demonstrating `nowo-tech/doctrine-encrypt-bundle`.

## Setup

From this directory:

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
php bin/console doctrine:encrypt:generate-secret-key
```

## Commands

- `php bin/console doctrine:encrypt:status`
- `php bin/console doctrine:encrypt:database`
- `php bin/console doctrine:decrypt:database`
