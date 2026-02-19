# Configuration

The bundle is configured under the root key `nowo_doctrine_encrypt`. All options are optional.

You can either use a **single encryptor** (legacy style) or **multiple named configs** (e.g. one for personal data, another for financial data).

## Single encryptor (default)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `encryptor_class` | `string` | `Halite` | Encryptor: `Halite` or `Defuse`, or a custom class (see [Custom encryptor](custom_encryptor.md)). |
| `secret_directory_path` | `string` | `%kernel.project_dir%` | Directory where the secret key file is stored (e.g. `.Halite.key` or `.Defuse.key`). |

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    encryptor_class: Halite   # or Defuse
    secret_directory_path: '%kernel.project_dir%'
```

## Multiple encryptors (configs)

To use different encryptors for different entity properties (e.g. `personal_data` with Halite, `financial_data` with Defuse), define **configs** and optionally a **default_config**:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `configs` | `array` | `[]` | Map of alias => options. Each alias (e.g. `personal_data`, `financial_data`) has `encryptor_class` and `secret_directory_path`. |
| `default_config` | `string` | first key in `configs` | Which config to use when the attribute has no config or uses `"default"`. |

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

Each config gets its own key file: `.{encryptor_class}.{alias}.key` (e.g. `.Halite.personal_data.key`, `.Defuse.financial_data.key`). Add them to `.gitignore`. Then in entities, use the attribute with the config name: `#[Encrypted('personal_data')]` or `#[Encrypted('financial_data')]`. See [Usage](USAGE.md#multiple-encryptors).

## Encryptors

- **Halite**  
  Uses [paragonie/halite](https://github.com/paragonie/halite). Requires `ext-sodium` (or works with `paragonie/sodium_compat`). Included as a dependency of the bundle.

- **Defuse**  
  Uses [defuse/php-encryption](https://github.com/defuse/php-encryption). You must require it yourself:
  ```bash
  composer require defuse/php-encryption ^2.1
  ```

## Secret key files

- **Single encryptor:** key file is `.{encryptor_class}.key` (e.g. `.Halite.key`, `.Defuse.key`) inside `secret_directory_path`.
- **With configs:** key file per config is `.{encryptor_class}.{alias}.key` (e.g. `.Halite.personal_data.key`, `.Defuse.financial_data.key`) inside that config’s `secret_directory_path`.

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

If no config file exists, defaults from the bundle’s `Configuration` class are used.
