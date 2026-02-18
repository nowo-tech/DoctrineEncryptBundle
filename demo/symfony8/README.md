# Doctrine Encrypt Bundle – Symfony 8 Demo

Minimal Symfony 8 application demonstrating `nowo-tech/doctrine-encrypt-bundle`.

## Setup

From this directory (or from the bundle root with path repo):

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
php bin/console doctrine:encrypt:generate-secret-key
```

## Commands

- `php bin/console doctrine:encrypt:status` – list entities and encrypted properties
- `php bin/console doctrine:encrypt:database` – encrypt existing data
- `php bin/console doctrine:decrypt:database` – decrypt existing data

## Entity

`App\Entity\SecretMessage` has an encrypted `message` property. Use Doctrine as usual; the bundle encrypts/decrypts automatically.
