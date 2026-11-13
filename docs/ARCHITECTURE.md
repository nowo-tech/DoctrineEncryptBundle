# Architecture

`nowo-tech/doctrine-encrypt-bundle` is an infrastructure bundle: it ships no entities, tables or events. It encrypts
selected fields through a Doctrine listener or opt-in DBAL types, and exposes a small set of ports.

## Table of contents

- [Public ports](#public-ports)
- [Extension points (SPI)](#extension-points-spi)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)

## Public ports

| Port | Default implementation | Purpose |
| --- | --- | --- |
| `Encryptors\EncryptorInterface` | `HaliteEncryptor`, `DefuseEncryptor`, `MysqlAesEncryptor` | Encrypts and decrypts one string. |
| `Encryptors\EncryptorRegistryInterface` | `Encryptors\EncryptorRegistry` | Named encryptors (one per `profiles` entry); `get()`, `getDefault()`, `has()`, `getConfigNames()`. |
| `Util\EncryptUtilInterface` | `Util\EncryptUtil` | Encrypt / decrypt single values outside Doctrine (`<ENC>` marker): `encrypt()`, `decrypt()`, `decryptStrict()`. |

The ports take and return scalars, strings and bundle exceptions; no Doctrine, Symfony or HTTP type appears in them.
Each interface is aliased to its default service (`nowo_doctrine_encrypt.encryptor_registry`, `EncryptUtil`), and the
old class ids still resolve. The listener, the encrypted DBAL types, the commands, the Twig `decrypt` filter and
`EncryptUtil` all depend on `EncryptorRegistryInterface`, so one alias replaces the registry everywhere:

```yaml
services:
  App\Security\VaultEncryptorRegistry: ~
  Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface:
    alias: App\Security\VaultEncryptorRegistry
```

`EncryptUtil::ENCRYPTION_MARKER` and `EncryptedValue` helpers stay on their classes. Implementations must keep no
request or entity state between calls (worker mode, kernel reset false).

## Extension points (SPI)

- `EncryptorInterface`: a custom encryptor class (`encryptor_class`), see [custom_encryptor.md](custom_encryptor.md).
- `EncryptorRegistryInterface`: a custom source of named encryptors.
- `EncryptUtilInterface`: replace the value helper used by application code.
- Field selection: `#[Encrypted]`, or `encrypted_fields` in the bundle configuration (no attribute on the entity).

## Standard Symfony and DDD hosts

- **Standard Symfony host:** put `#[Encrypted]` on entity properties, or encrypt with the DBAL types. Autowire
  `EncryptUtilInterface` (or the legacy `EncryptUtil`) for ad-hoc values. Nothing to map.
- **DDD / hexagonal host:** keep the domain attribute-free: select fields with `encrypted_fields` (or use the encrypted
  DBAL types with XML mapping), set `property_access: reflection` so domain setters never see ciphertext, and wrap
  `EncryptUtilInterface` in an infrastructure adapter behind your own domain port. See
  [USAGE.md](USAGE.md#using-it-from-a-ddd--hexagonal-application).
