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

Run `php bin/console list doctrine` to see the exact command names.

## Status

Get the current database and encryption information:

```bash
php bin/console doctrine:encrypt:status
```

This returns the number of entities and the number of properties marked with the `Encrypted` attribute per entity.

## Encrypt database

Encrypt all currently unencrypted values in the database:

```bash
php bin/console doctrine:encrypt:database
```

Optional arguments:

- **config** — Config alias to use (e.g. `default`, `personal_data`, `financial_data`). If omitted, all configs are processed in turn.
- **batchSize** — Number of entities to flush per batch (default: 20).

Examples:

```bash
php bin/console doctrine:encrypt:database
php bin/console doctrine:encrypt:database default 50
php bin/console doctrine:encrypt:database personal_data
```

## Decrypt database

Decrypt all encrypted values:

```bash
php bin/console doctrine:decrypt:database
```

Optional arguments:

- **config** — Config alias to use (e.g. `default`, `personal_data`). If omitted, all configs are processed in turn.
- **batchSize** — Number of entities to flush per batch (default: 20).

Examples:

```bash
php bin/console doctrine:decrypt:database
php bin/console doctrine:decrypt:database default 50
```

## Generate secret key

Generates encryption key files for Halite and Defuse configs.

**Without argument:** Checks all configs; creates a key file in each `secret_directory_path` when missing (Halite and Defuse only). Skips configs that already have a key file.

```bash
php bin/console doctrine:encrypt:generate-secret-key
```

**With config argument:** Creates or overwrites the key for that config only. If the key file already exists, the command asks for confirmation before overwriting.

```bash
php bin/console doctrine:encrypt:generate-secret-key default
php bin/console doctrine:encrypt:generate-secret-key personal_data
```

Run this before first use if no key file exists, or when rotating keys. Configs using a custom encryptor class are skipped (only Halite and Defuse keys are generated).

For a full **key rotation** procedure (backup → decrypt → change keys → re-encrypt), see [Key rotation](KEY_ROTATION.md).
