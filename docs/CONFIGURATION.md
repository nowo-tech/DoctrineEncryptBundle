# Configuration

The bundle is configured under the root key `nowo_doctrine_encrypt`. The only supported structure is **`default_config`** plus **`configs`** (a map of config name ⇒ options). There is no root-level `encryptor_class` or `secret_directory_path`; use a single entry under `configs` (e.g. `default`) for one encryptor. When `#[Encrypted]` has no alias (or uses `"default"`), the encryptor for `default_config` is used.

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `configs` | `array` | `[]` (normalized to one `default` config) | Map of alias => options. Each alias (e.g. `default`, `personal_data`, `financial_data`) has `encryptor_class` and `secret_directory_path`. |
| `default_config` | `string` | `default` | Which config to use when the attribute has no alias or uses `"default"`. |

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

## Secret key files

Key file per config: `.{encryptor_class}.{alias}.key` (e.g. `.Halite.default.key`, `.Halite.personal_data.key`, `.Defuse.financial_data.key`) inside that config’s `secret_directory_path`.

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
