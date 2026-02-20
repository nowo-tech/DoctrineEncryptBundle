# Key rotation strategy

This guide describes a safe procedure to **rotate encryption keys**: back up the current state, decrypt data with the old key, replace the key file(s), then re-encrypt with the new key(s). Use it when you need to change the secret key (e.g. for compliance, after a suspected compromise, or periodic rotation).

**Important:** Key rotation touches all encrypted data. Plan the steps, run them in a maintenance window, and verify backups before starting.

## Overview

1. **Back up** the database and the current key file(s).
2. **Decrypt** the database so that encrypted columns hold plain text (using the current key).
3. **Replace** the key file(s) with new one(s) (e.g. generate new keys, update config if paths change).
4. **Re-encrypt** the database with the new key(s).

The bundle does not support “in-place” re-encryption with a new key; decrypt-then-encrypt is the supported approach.

## Prerequisites

- Console commands: [Commands](COMMANDS.md) (`doctrine:encrypt:status`, `doctrine:decrypt:database`, `doctrine:encrypt:database`, `doctrine:encrypt:generate-secret-key`).
- Configuration: [Configuration](CONFIGURATION.md) (single encryptor or multiple configs and key file paths).

## Step 1: Back up the current state

1. **Database**  
   Create a full backup of the database (dump or your usual backup method). Ensure you can restore from it if something goes wrong.

2. **Key file(s)**  
   Copy the current secret key file(s) to a secure, offline location (e.g. encrypted volume or vault). You need them to decrypt in step 2.  
   - Single config (e.g. `default`): e.g. `.Halite.default.key` or `.Defuse.default.key` in that config’s `secret_directory_path`.  
   - Multiple configs: one file per config, e.g. `.Halite.personal_data.key`, `.Defuse.financial_data.key`.

3. **Optional:** Put the application in maintenance mode or read-only so no new encrypted data is written during the rotation.

## Step 2: Decrypt the database

Decrypt all encrypted columns using the **current** key (the one still configured and present on disk).

```bash
# Use the default configured encryptor
php bin/console doctrine:decrypt:database

# Or specify the encryptor if needed
php bin/console doctrine:decrypt:database Halite
php bin/console doctrine:decrypt:database Defuse
```

With **multiple encryptor configs**, the subscriber uses the registry to pick the right encryptor per property (`#[Encrypted('personal_data')]`, etc.). A single run of `doctrine:decrypt:database` therefore decrypts all entities using each config’s current key. Ensure every encrypted column is decrypted (all old keys still in place) before replacing any key file.

Verify that data is decrypted (e.g. check a few rows or run `doctrine:encrypt:status` and then inspect values). The stored values will no longer end with the encryption marker used by the bundle.

## Step 3: Change the key(s)

1. **Rename or remove** the old key file(s) from the `secret_directory_path` (or per-config path) so the application no longer uses them.

2. **Generate new key(s)** for the encryptor(s) you use:

   ```bash
   # Single encryptor: generates the default key file (e.g. .Halite.key)
   php bin/console doctrine:encrypt:generate-secret-key
   ```

   With **multiple configs**, the bundle typically has one key file per config (e.g. `.Halite.personal_data.key`). Generate a new key for each config you are rotating; you may need to run the generate command with the appropriate config/encryptor, or create the key file(s) in the expected paths. See [Configuration](CONFIGURATION.md) for key file names and paths.

3. **Update configuration** only if you changed key file paths or names (e.g. a new `secret_directory_path` or new config entries). Ensure `config/packages/nowo_doctrine_encrypt.yaml` (or your env-specific config) points to the new key file(s).

4. **Restrict access** to the new key file(s) (permissions, secrets manager, etc.) and add them to `.gitignore` if not already.

## Step 4: Re-encrypt the database

Re-encrypt all previously decrypted columns with the **new** key(s).

```bash
# Use the default configured encryptor (now using the new key)
php bin/console doctrine:encrypt:database

# Or specify the encryptor if needed
php bin/console doctrine:encrypt:database Halite
php bin/console doctrine:encrypt:database Defuse
```

With **multiple configs**, a single run of `doctrine:encrypt:database` re-encrypts all entities using each config’s encryptor from the registry. Ensure every key file has been replaced with the new key so all columns are re-encrypted with the correct new keys.

Confirm that encryption succeeded (e.g. run the status command and spot-check that values are encrypted again). Then disable maintenance mode if you enabled it.

## Step 5: After rotation

- **Securely destroy or archive** the old key backup when you no longer need it (follow your retention policy).
- **Update any other systems** that held a copy of the old key (e.g. deployment or secrets management).
- Keep the new key(s) only in secure storage and in the configured path(s) used by the application.

## Summary checklist

| Step | Action |
|------|--------|
| 1 | Back up database and current key file(s); optional maintenance mode |
| 2 | Run `doctrine:decrypt:database` (with correct encryptor/config) so all data is decrypted with the old key |
| 3 | Remove/rename old key(s); generate new key(s); update config if paths changed |
| 4 | Run `doctrine:encrypt:database` (with correct encryptor/config) to re-encrypt with the new key(s) |
| 5 | Verify data; disable maintenance mode; securely dispose of old key backup |

If anything fails, restore the database from the backup and the old key file(s) so the application can decrypt again; then fix the issue and repeat the procedure.
