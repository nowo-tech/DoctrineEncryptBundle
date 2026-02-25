# Commands

The bundle provides console commands to inspect encryption status, encrypt or decrypt the database, generate a secret key, and rotate keys.

## List commands

```bash
php bin/console list doctrine
```

Look for commands such as:

- `doctrine:encrypt:status`
- `doctrine:encrypt:database`
- `doctrine:decrypt:database`
- `doctrine:encrypt:generate-secret-key`
- `doctrine:encrypt:rotate-keys`

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
- **batchSize** — Number of entities to flush per batch (default from config: 5; see [Configuration](CONFIGURATION.md)).

Options:

- **`--force`** — Do not ask for confirmation (useful with `--no-interaction` or when called from scripts).

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
- **batchSize** — Number of entities to flush per batch (default from config: 5; see [Configuration](CONFIGURATION.md)).

Options:

- **`--force`** — Do not ask for confirmation (useful with `--no-interaction` or when called from scripts).

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

**With config argument:** For a path-based config: creates or overwrites the key file (asks for confirmation if it exists). Use **`--force`** to overwrite without asking. For a config without path: only outputs the generated key value.

```bash
php bin/console doctrine:encrypt:generate-secret-key default
php bin/console doctrine:encrypt:generate-secret-key personal_data
php bin/console doctrine:encrypt:generate-secret-key personal_data --force
```

Run this before first use if no key file exists, or when rotating keys. When using `%env(APP_ENCRYPT_KEY)%` in config, run the command to get the key value and set it in your environment. Configs using a custom encryptor class are skipped (only Halite and Defuse keys are generated).

## Rotate keys

Runs the full key rotation in one go: optional backup (database + key files), full decrypt, key change (new key files or .env update), then re-encrypt. Each step asks for confirmation unless you use **`--no-interaction`**.

```bash
php bin/console doctrine:encrypt:rotate-keys
php bin/console doctrine:encrypt:rotate-keys --backup
php bin/console doctrine:encrypt:rotate-keys --backup --backup-db-cmd=app:backup-db --no-interaction
```

- **`--backup`** — Before rotating, back up the **database** and **current key files** into a timestamped directory `var/encrypt_rotation_backup_YYYY-MM-DD_HHmmss/`.  
  - **SQLite:** the database file is copied and gzip-compressed to `database.sqlite.gz` in that directory.  
  - **MySQL / PostgreSQL:** use **`--backup-db-cmd=your:command`** to run a Symfony command that dumps the DB (e.g. a custom `app:backup-db` that runs `mysqldump` or `pg_dump`). Without it, the command only backs up key files and reminds you to back up the DB manually.
- **`--backup-db-cmd=COMMAND`** — Symfony command name to run for database backup (only used when `--backup` is set). Your command should dump the database to a file (e.g. under `var/`).
- **`--no-interaction`** — Do not ask for confirmation; run all steps (backup if `--backup`, then decrypt, change keys, re-encrypt).

Inside the backup directory you get: `database.sqlite.gz` (for SQLite, gzip-compressed) and a `keys/` subdirectory with the key files (e.g. `keys/personal_data_.Halite.personal_data.key`).

Configs that use **file-based keys**: the command generates new key files (overwrites). Configs that use **`secret_key_env_var`** (e.g. `%env(APP_ENCRYPT_KEY)%`): the command prompts you to update `.env` and press Enter; with `--no-interaction` it only reminds you to update `.env` before re-encryption.

For a manual step-by-step procedure, see [Key rotation](KEY_ROTATION.md).
