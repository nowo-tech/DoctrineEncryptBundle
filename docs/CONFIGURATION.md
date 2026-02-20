# Configuration

The bundle is configured under the root key `nowo_doctrine_encrypt`. The only supported structure is **`default_config`** plus **`configs`** (a map of config name ⇒ options). There is no root-level `encryptor_class` or `secret_directory_path`; use a single entry under `configs` (e.g. `default`) for one encryptor. When `#[Encrypted]` has no alias (or uses `"default"`), the encryptor for `default_config` is used.

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `configs` | `array` | `[]` (normalized to one `default` config) | Map of alias => options. Each alias has `encryptor_class` and either `secret_directory_path` or `secret_key_env_var`. Optional: `secret_key_filename`. |
| `default_config` | `string` | `default` | Which config to use when the attribute has no alias or uses `"default"`. |

Per-config options (under each entry in `configs`):

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `encryptor_class` | `string` | `Halite` | `Halite`, `Defuse`, or a custom class. |
| `secret_directory_path` | `string` | `%kernel.project_dir%` | Directory for the key file. **Required** unless `secret_key_env_var` is set. Cannot be set together with `secret_key_env_var`. |
| `secret_key_filename` | `string` | *(pattern)* | Optional custom key filename (e.g. `.my_app.key`). Only used when `secret_directory_path` is set; default pattern is `.{encryptor_class}.{alias}.key`. |
| `secret_key_env_var` | `string` | `null` | Use `%env(APP_ENCRYPT_KEY)%`. Symfony resolves it at config load and the bundle receives the key value. When set, **must not** set `secret_directory_path` or `secret_key_filename`. |

If `configs` is empty, the bundle behaves as if you had defined a single config named `default` with Halite and `%kernel.project_dir%`.

## Example: single encryptor (default)

Omit the config file or define only the default config:

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    default_config: default
    configs:
        default:
            encryptor_class: Halite   # or Defuse
            secret_directory_path: '%kernel.project_dir%'
```

Then use `#[Encrypted]` or `#[Encrypted('default')]` on properties; they will use this encryptor.

## Example: multiple encryptors

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    default_config: personal_data
    configs:
        personal_data:
            encryptor_class: Halite
            secret_directory_path: '%kernel.project_dir%'
        financial_data:
            encryptor_class: Defuse
            secret_directory_path: '%kernel.project_dir%/var/secrets'
```

- Properties with `#[Encrypted]` or `#[Encrypted('default')]` use the encryptor for **personal_data** (default_config).
- Properties with `#[Encrypted('personal_data')]` or `#[Encrypted('financial_data')]` use the corresponding config.

Each config has its own key file: `.{encryptor_class}.{alias}.key` (e.g. `.Halite.personal_data.key`, `.Defuse.financial_data.key`). Add them to `.gitignore`. See [Usage](USAGE.md#multiple-encryptors).

## Encryptors

- **Halite**  
  Uses [paragonie/halite](https://github.com/paragonie/halite). Requires `ext-sodium` (or works with `paragonie/sodium_compat`). Included as a dependency of the bundle.

- **Defuse**  
  Uses [defuse/php-encryption](https://github.com/defuse/php-encryption). You must require it yourself:
  ```bash
  composer require defuse/php-encryption ^2.1
  ```

## Secret key: file or env

For each config you either use a **key file** or a **env var**:

- **Key file:** Set `secret_directory_path` (and optionally `secret_key_filename`). The key file path is `{secret_directory_path}/{secret_key_filename}` or, if `secret_key_filename` is omitted, `{secret_directory_path}/.{encryptor_class}.{alias}.key` (e.g. `.Halite.default.key`, `.Defuse.financial_data.key`).
- **Env var:** Set `secret_key_env_var` to `%env(APP_ENCRYPT_KEY)%`. Symfony resolves it when loading config and the bundle receives the key value. Do **not** set `secret_directory_path` or `secret_key_filename`. The value must be the same format as the key file content (for Halite: hex-encoded key; for Defuse: the password string). Run `doctrine:encrypt:generate-secret-key` for configs without path to get the key value, then set it in your `.env` or environment.

## Secret key files (when using path)

Key file per config: `.{encryptor_class}.{alias}.key` (or your `secret_key_filename`) inside that config’s `secret_directory_path`.

**Important:** Add key files to `.gitignore` so they are never committed:

```gitignore
.Halite.key
.Defuse.key
.Halite.*.key
.Defuse.*.key
```

If no key file exists, the bundle can generate one (e.g. via the `doctrine:encrypt:generate-secret-key` command, if available). See [Commands](COMMANDS.md).

## Loading configuration

Symfony loads configuration from:

- `config/packages/nowo_doctrine_encrypt.yaml` (all environments)
- `config/packages/{env}/nowo_doctrine_encrypt.yaml` (per environment)

If no config file exists, the bundle uses one default config (Halite, `%kernel.project_dir%`).
