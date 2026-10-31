# Configuration

The bundle is configured under the root key `nowo_doctrine_encrypt`. The only supported structure is **`default_profile`** plus **`profiles`** (a map of profile name ⇒ options). There is no root-level `encryptor_class` or `secret_directory_path`; use a single entry under `profiles` (e.g. `default`) for one encryptor. When `#[Encrypted]` has no alias (or uses `"default"`), the encryptor for `default_profile` is used.

## Table of contents

- [Options](#options)
  - [Several connections / entity managers](#several-connections--entity-managers)
- [Profiles](#profiles)
- [Example: single encryptor (default)](#example-single-encryptor-default)
- [Example: multiple encryptors](#example-multiple-encryptors)
- [Encryptors](#encryptors)
  - [Encryptor and query performance (overview)](#encryptor-and-query-performance-overview)
- [Secret key: file or env](#secret-key-file-or-env)
- [Secret key files (when using path)](#secret-key-files-when-using-path)
- [generate_missing_key (missing key files)](#generate_missing_key-missing-key-files)
- [on_decrypt_failure](#on_decrypt_failure)
- [secret_permissions (key file hardening)](#secret_permissions-key-file-hardening)
- [Loading configuration](#loading-configuration)
- [SecretType form options](#secrettype-form-options)

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `connections` | `list<string>` | `[]` (every connection) | Doctrine DBAL connections whose entity managers get the encrypt/decrypt listener. Empty = all connections (DoctrineBundle registers an untagged listener on each one). List names (e.g. `[default, customer]`) to restrict it; entities on a connection that is **not** listed are neither encrypted nor decrypted. A single string is accepted. |
| `default_profile` | `string` | `default` | Which profile to use when the attribute has no alias or uses `"default"`. |
| `on_decrypt_failure` | `keep` \| `null` \| `throw` | `keep` | What an `#[Encrypted]` property gets when its stored value ends with `<ENC>` but cannot be decrypted (wrong or rotated key, corrupted ciphertext). `keep` = the raw ciphertext (marker stripped) and a warning — callers may take it for the secret. **`null` (recommended)** = `null` and a warning; the stored ciphertext is written back unchanged on flush, so an unrelated update never destroys it (a non-nullable property throws instead). **`throw` (recommended)** = `Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException` (no value in the message). See [On decrypt failure](#on_decrypt_failure). |
| `generate_missing_key` | `?bool` | `null` (= `kernel.debug`) | Whether the Halite / Defuse encryptors may create a **missing** key file on first use. `null` = only in debug; outside debug a missing key file throws `MissingEncryptionKeyException` (a wrong path or a lost volume would otherwise encrypt new data with a throwaway key). An existing key file is never replaced, also when it is unreadable. See [Missing key files](#generate_missing_key-missing-key-files). |
| `batch_size` | `int` | `5` | Default batch size for `doctrine:decrypt:database` and `doctrine:encrypt:database` (raw SQL). Overridable per run via the `batchSize` argument. Minimum: 1. |
| `secret_permissions` | `array` | see below | Filesystem hardening for file-based keys. See [secret_permissions](#secret_permissions-key-file-hardening). |
| `profiles` | `array` | `[]` (normalized to one `default` profile) | Map of alias => options. Each alias has `encryptor_class` and either `secret_directory_path` or `secret_key_env_var`. Optional: `secret_key_filename`. |

Per-profile options (under each entry in `profiles`):

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `encryptor_class` | `string` | `Halite` | `Halite`, `Defuse`, `MysqlAes`, or a custom class. |
| `secret_directory_path` | `string` | `%kernel.project_dir%` | Directory for the key file. **Required** unless `secret_key_env_var` is set. Cannot be set together with `secret_key_env_var`. |
| `secret_key_filename` | `string` | *(pattern)* | Optional custom key filename (e.g. `.my_app.key`). Only used when `secret_directory_path` is set; default pattern is `.{encryptor_class}.{alias}.key`. |
| `secret_key_env_var` | `string` | `null` | Use `%env(APP_ENCRYPT_KEY)%`. Symfony resolves it at config load and the bundle receives the key value. When set, **must not** set `secret_directory_path` or `secret_key_filename`. |

If `profiles` is empty, the bundle behaves as if you had defined a single profile named `default` with Halite and `%kernel.project_dir%`.

### Several connections / entity managers

Up to 2.6.x the listener was tagged for the `default` connection only, so `#[Encrypted]` properties of entities managed through another connection were stored in **plaintext** without any warning. Since 2.7.0 the listener is attached to every connection unless you restrict it:

```yaml
nowo_doctrine_encrypt:
    connections: [default, customer]   # omit (or []) for all connections
```

One listener instance serves all listed connections; its per-entity decryption cache is scoped to the entity manager being flushed.

## Profiles

- **Canonical keys:** `default_profile` (string) and `profiles` (map of profile name ⇒ `encryptor_class`, `secret_directory_path` / `secret_key_filename` or `secret_key_env_var`).
- **Legacy synonyms (still accepted):** `default_config` ⇒ `default_profile` and `configs` ⇒ `profiles`. They are renamed before normalization; the legacy container parameters `nowo_doctrine_encrypt.default_config` and `nowo_doctrine_encrypt.configs` are still set with the same values.
- **Defaults:** without `default_profile`, the first profile name is used (`default` when `profiles` is empty, which yields one Halite profile in `%kernel.project_dir%`).
- **Validation:** the extension fails at container build when `default_profile` is not a key of `profiles` (`nowo_doctrine_encrypt.default_profile "x" must be a key in nowo_doctrine_encrypt.profiles`).
- **Runtime selection:** per property, through the attribute alias — `#[Encrypted]` or `#[Encrypted('default')]` use `default_profile`; `#[Encrypted('financial_data')]` uses that profile. The `doctrine:encrypt:database` / `doctrine:decrypt:database` commands use the same attribute mapping.

## Example: single encryptor (default)

Omit the config file or define only the default profile:

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    default_profile: default
    profiles:
        default:
            encryptor_class: Halite   # or Defuse
            secret_directory_path: '%kernel.project_dir%'
```

Then use `#[Encrypted]` or `#[Encrypted('default')]` on properties; they will use this encryptor.

## Example: multiple encryptors

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    default_profile: personal_data
    profiles:
        personal_data:
            encryptor_class: Halite
            secret_directory_path: '%kernel.project_dir%'
        financial_data:
            encryptor_class: Defuse
            secret_directory_path: '%kernel.project_dir%/var/secrets'
```

- Properties with `#[Encrypted]` or `#[Encrypted('default')]` use the encryptor for **personal_data** (default_profile).
- Properties with `#[Encrypted('personal_data')]` or `#[Encrypted('financial_data')]` use the corresponding profile.

Each profile has its own key file: `.{encryptor_class}.{alias}.key` (e.g. `.Halite.personal_data.key`, `.Defuse.financial_data.key`). Add them to `.gitignore`. See [Usage](USAGE.md#multiple-encryptors).

## Encryptors

- **Halite**  
  Uses [paragonie/halite](https://github.com/paragonie/halite). Requires `ext-sodium` (or works with `paragonie/sodium_compat`). Included as a dependency of the bundle.

- **Defuse**  
  Uses [defuse/php-encryption](https://github.com/defuse/php-encryption). You must require it yourself:
  ```bash
  composer require defuse/php-encryption ^2.1
  ```

- **MysqlAes**  
  Compatible with MySQL [`AES_ENCRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) / [`AES_DECRYPT()`](https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html) (AES-128-ECB, default `block_encryption_mode`). The passphrase is a plain string (file or env). **Deprecated** (AES-128-ECB; rejected in `prod`). Its ciphertext is **raw binary**, so ORM-managed columns must be binary (`VARBINARY` via `type: 'binary'`, or `TEXT` with `charset: binary`): a `utf8mb4` `string`/`text` column fails with *1366 Incorrect string value* (or silently corrupts the ciphertext in non-strict `sql_mode`), and the `TEXT` + binary charset mapping leaves a permanent no-op diff in `doctrine:schema:update` / `migrations:diff`. Prefer Halite or Defuse, whose output fits any text column — see [MYSQL_AES.md › Column type](MYSQL_AES.md#column-type-for-orm-managed-fields-utf8mb4-limitation). Use a **BLOB** column when encrypting in SQL. See [MYSQL_AES.md](MYSQL_AES.md) (INSERT examples, repository pattern, demo `/mysql-aes-note`) and the symfony8 demo routes under `/mysql-aes-note`. **Performance:** PHP OpenSSL on every ORM read/write; SQL `AES_ENCRYPT` only avoids PHP on insert/update for columns you manage in raw SQL. Searching secrets via `LIKE` on decrypted values in SQL is among the most expensive patterns — see [PERFORMANCE.md](PERFORMANCE.md).

### Encryptor and query performance (overview)

| Encryptor | Persist/load (Doctrine) | Typical use |
|-----------|-------------------------|-------------|
| **Halite** | Fastest in PHP (especially with `ext-sodium`) | Default; best security/performance balance |
| **Defuse** | Moderate | When Defuse is required |
| **MysqlAes** | Moderate; weaker algorithm | MySQL legacy interop only |

Searching or filtering encrypted fields is **not index-friendly** on plaintext. Compare all options (including `AES_DECRYPT(...) LIKE`, PHP filters, and `LIKE` on ciphertext) in **[PERFORMANCE.md](PERFORMANCE.md)**.

## Secret key: file or env

For each profile you either use a **key file** or a **env var**:

- **Key file:** Set `secret_directory_path` (and optionally `secret_key_filename`). The key file path is `{secret_directory_path}/{secret_key_filename}` or, if `secret_key_filename` is omitted, `{secret_directory_path}/.{encryptor_class}.{alias}.key` (e.g. `.Halite.default.key`, `.Defuse.financial_data.key`).
- **Env var:** Set `secret_key_env_var` to `%env(APP_ENCRYPT_KEY)%`. Symfony resolves it when loading config and the bundle receives the key value. Do **not** set `secret_directory_path` or `secret_key_filename`. The value must be the same format as the key file content (for Halite: hex-encoded key; for Defuse or MysqlAes: the password/passphrase string). Run `doctrine:encrypt:generate-secret-key` for profiles without path to get the key value, then set it in your `.env` or environment.

## Secret key files (when using path)

Key file per profile: `.{encryptor_class}.{alias}.key` (or your `secret_key_filename`) inside that profile’s `secret_directory_path`.

**Important:** Add key files to `.gitignore` so they are never committed:

```gitignore
.Halite.key
.Defuse.key
.Halite.*.key
.Defuse.*.key
```

Create key files explicitly with `php bin/console doctrine:encrypt:generate-secret-key` (no argument: creates every **missing** key and never overwrites an existing one). See [Commands](COMMANDS.md) and [generate_missing_key](#generate_missing_key-missing-key-files).

## generate_missing_key (missing key files)

Up to 2.9.x the Halite and Defuse encryptors silently generated a **new** key when the key file did not exist (wrong `secret_directory_path`, lost volume, new container). Every stored value then became undecryptable and new data was encrypted with a throwaway key, without any error. Since 2.10.0:

| `generate_missing_key` | Missing key file | Existing but unreadable key file |
|------------------------|------------------|----------------------------------|
| `null` (default) | generated when `kernel.debug` is true; `MissingEncryptionKeyException` otherwise | `MissingEncryptionKeyException` (never replaced) |
| `true` | generated (2.9.x behaviour) | `MissingEncryptionKeyException` (never replaced) |
| `false` | `MissingEncryptionKeyException` | `MissingEncryptionKeyException` (never replaced) |

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    generate_missing_key: false   # also refuse in dev; create keys with doctrine:encrypt:generate-secret-key
```

Deployment: create the key once (`php bin/console doctrine:encrypt:generate-secret-key`), back it up, and mount it (or use `secret_key_env_var`). Profiles using `secret_key_env_var` and custom encryptors are not affected; `MysqlAes` already refused a missing key file.

## on_decrypt_failure

When a stored value ends with `<ENC>` but its ciphertext cannot be decrypted, the listener applies `on_decrypt_failure`:

```yaml
nowo_doctrine_encrypt:
    on_decrypt_failure: throw   # or null; keep = 2.x default
```

- **`keep`** (default in 2.x): the property gets the raw ciphertext (marker stripped) and a warning is logged. Consumers may treat that ciphertext as the secret (send it as a password, export it, show "a secret is set"). Planned to change in 3.0 (see [ROADMAP](ROADMAP.md)).
- **`null`**: the property is set to `null` and a warning is logged. If the entity is flushed and the property is still `null`, the original ciphertext is written back (nothing is lost while a key is missing); assigning a new value replaces it. A non-nullable property cannot hold `null`: `DecryptionFailedException` is thrown instead.
- **`throw`**: `DecryptionFailedException` (implements `Nowo\DoctrineEncryptBundle\Exception\ExceptionInterface`) is thrown from `postLoad` / `postFlush`; the message names the entity, property and profile, never the value.

Outside Doctrine use `EncryptUtil::decryptStrict()` (see [USAGE.md](USAGE.md#encryptutil-programmatic-encryptdecrypt)).

## secret_permissions (key file hardening)

Anyone who can read a key file can decrypt every column encrypted with it. The bundle hardens file-based keys (profiles using `secret_key_env_var` are skipped):

- **Missing key directories** (`dirname` of each key path) are created with `directory_mode` (default `0770`). **Existing directories are never chmod-ed** — the default `secret_directory_path` is the project root.
- **Key files** are chmod-ed to `file_mode` (default `0600`) when their mode differs.
- **When:** on every console command (`console.command`, priority 1024) and on HTTP main requests **at most once per `http_check_interval` seconds per worker** (`kernel.request`, priority 1024). The throttle lives in the listener instance and is deliberately kept across requests, so FrankenPHP worker mode / long-running workers do not `stat()` key files on every request, while a key generated lazily by the first encryption still gets hardened within a minute.
- `doctrine:encrypt:generate-secret-key`, the Halite auto-generated key, and the `rotate-keys` backups (`var/encrypt_rotation_backup_*`, now `0700` dirs / `0600` key copies) apply the same modes right after writing.

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    secret_permissions:
        enabled: true             # false = no listener (manage permissions yourself)
        directory_mode: '0770'    # or '0700' when PHP and the CLI run as the same user
        file_mode: '0600'
        http_check_interval: 60   # seconds; 0 = every main request
```

Modes accept octal strings (`'0770'`, `'0o770'`, `'770'`) or integers (`0o770` in PHP config). If a missing key directory cannot be created, a `RuntimeException` is thrown (console and HTTP). Recommended layout: `secret_directory_path: '%kernel.project_dir%/var/secrets'` (outside the web root, ignored by Git).

## Loading configuration

Symfony loads configuration from:

- `config/packages/nowo_doctrine_encrypt.yaml` (all environments)
- `config/packages/{env}/nowo_doctrine_encrypt.yaml` (per environment)

If no config file exists, the bundle uses one default config (Halite, `%kernel.project_dir%`).

## SecretType form options

`Nowo\DoctrineEncryptBundle\Form\Type\SecretType` (since 2.8.0, requires `symfony/form`) needs no bundle configuration; it is configured per field. See [USAGE.md](USAGE.md#leave-blank-to-keep-secret-fields-secrettype--secretfieldapplier).

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `current_value` | `?string` | `null` | Stored plain secret. Only used to build the `MaskUtil::secretHint()` hint (help + placeholder); never rendered. `null` / `''` = nothing stored. |
| `hint_visible_last` | `int` | `0` | Trailing characters revealed by the hint (`<= 0` shows only the mask `••••`). Was `4` up to 2.9.x; pass `4` to get `•••• 1234` back. |
| `allow_clear` | `?bool` | `null` | Add the `clear` checkbox: `null` = only when `current_value` is set, `true` = always, `false` = never. |
| `value_options` | `array` | `[]` | Merged (recursively) over the `value` child options (`PasswordType`: `always_empty`, `trim: true`, `required: false`, label/help in the bundle domain). Use it for `constraints`, `label`, `attr`. |
| `clear_options` | `array` | `[]` | Merged over the `clear` child options (`CheckboxType`). |
| `mapped` | `bool` | `false` | Symfony option; kept `false` so the stored secret is only changed through `SecretFieldApplier`. |
| `error_bubbling` | `bool` | `false` | Symfony option; `false` (since 2.10.1) keeps errors on the field so they render next to it instead of at the top of the form. |

Submitted data: `['value' => ?string, 'clear' => bool]` (`clear` absent when the checkbox is not rendered). Translation domain of the default labels: `NowoDoctrineEncryptBundle` (`en`, `es`).
