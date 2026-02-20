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

The command lists, for each entity, which properties are encrypted and under which **config** (e.g. `personal_data`, `financial_data`). It then shows a summary (how many entities have encrypted properties and the total encrypted property count) and, at the end, the **configured encryptor configs** in the project (config name, encryptor class such as Halite/Defuse, and which config is the default).

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

**Without argument:** For configs with a key file path: creates the key file when missing (Halite and Defuse only) and skips when it already exists. For configs **without path** (they use `secret_key_env_var` with `%env(APP_ENCRYPT_KEY)%`): the command only **outputs the generated key** (one per config) so you can set it in your `.env` or environment.

```bash
php bin/console doctrine:encrypt:generate-secret-key
```

**With config argument:** For a path-based config: creates or overwrites the key file (asks for confirmation if it exists). For a config without path: only outputs the generated key value.

```bash
php bin/console doctrine:encrypt:generate-secret-key default
php bin/console doctrine:encrypt:generate-secret-key personal_data
```

Run this before first use if no key file exists, or when rotating keys. When using `%env(APP_ENCRYPT_KEY)%` in config, run the command to get the key value and set it in your environment. Configs using a custom encryptor class are skipped (only Halite and Defuse keys are generated).

For a full **key rotation** procedure (backup → decrypt → change keys → re-encrypt), see [Key rotation](KEY_ROTATION.md).
