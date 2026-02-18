# Configuration

The bundle is configured under the root key `nowo_doctrine_encrypt`. All options are optional.

## Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `encryptor_class` | `string` | `Halite` | Encryptor to use: `Halite` or `Defuse`. You can also use a custom class (see [Custom encryptor](custom_encryptor.md)). |
| `secret_directory_path` | `string` | `%kernel.project_dir%` | Directory where the secret key file is stored (e.g. `.Halite.key` or `.Defuse.key`). |

## YAML example

```yaml
# config/packages/nowo_doctrine_encrypt.yaml
nowo_doctrine_encrypt:
    encryptor_class: Halite   # or Defuse
    secret_directory_path: '%kernel.project_dir%'
```

## Encryptors

- **Halite**  
  Uses [paragonie/halite](https://github.com/paragonie/halite). Requires `ext-sodium` (or works with `paragonie/sodium_compat`). Included as a dependency of the bundle.

- **Defuse**  
  Uses [defuse/php-encryption](https://github.com/defuse/php-encryption). You must require it yourself:
  ```bash
  composer require defuse/php-encryption ^2.1
  ```

## Secret key files

The secret key is stored in a file inside `secret_directory_path`:

- Halite: `.{secret_directory_path}/.Halite.key`
- Defuse: `.{secret_directory_path}/.Defuse.key`

**Important:** Add these files to `.gitignore` so they are never committed:

```gitignore
.Halite.key
.Defuse.key
```

If no key file exists, the bundle can generate one (e.g. via the `doctrine:encrypt:generate-secret-key` command, if available). See [Commands](commands.md).

## Loading configuration

Symfony loads configuration from:

- `config/packages/nowo_doctrine_encrypt.yaml` (all environments)
- `config/packages/{env}/nowo_doctrine_encrypt.yaml` (per environment)

If no config file exists, defaults from the bundle’s `Configuration` class are used.
