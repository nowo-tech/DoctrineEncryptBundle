# Commands

The bundle provides console commands to inspect encryption status, encrypt or decrypt the database, and generate a secret key.

## List commands

```bash
php bin/console list doctrine
```

Look for commands such as:

- `doctrine:encrypt:status`
- `doctrine:encrypt:database`
- `doctrine:decrypt:database`
- `doctrine:encrypt:generate-secret-key`

(Exact names may vary; run `list` to see the current names.)

## Status

Get the current database and encryption information:

```bash
php bin/console doctrine:encrypt:status
```

This returns the number of entities and the number of properties marked with `@Encrypted` (or the `Encrypted` attribute) per entity.

## Encrypt database

Encrypt all currently unencrypted values in the database:

```bash
php bin/console doctrine:encrypt:database
```

Optionally specify the encryptor:

```bash
php bin/console doctrine:encrypt:database Halite
php bin/console doctrine:encrypt:database Defuse
```

The command uses the configured encryptor by default.

## Decrypt database

Decrypt all encrypted values:

```bash
php bin/console doctrine:decrypt:database
```

Optionally specify the encryptor:

```bash
php bin/console doctrine:decrypt:database Halite
php bin/console doctrine:decrypt:database Defuse
```

## Generate secret key

Generate a new secret key file for the configured encryptor:

```bash
php bin/console doctrine:encrypt:generate-secret-key
```

Run this before first use if no key file exists, or when rotating keys.

For a full **key rotation** procedure (backup → decrypt → change keys → re-encrypt), see [Key rotation](KEY_ROTATION.md).
