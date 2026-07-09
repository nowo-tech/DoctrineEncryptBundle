# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/doctrine-encrypt-bundle`  
**Last audited**: 2026-07-07

Every production artifact under `src/` is listed below (including `src/.gitignore`, which excludes generated paths from VCS).

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoDoctrineEncryptBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/DoctrineEncryptExtension.php` | DI extension | FR-CFG-002 |
| `Configuration/Encrypted.php` | PHP attribute marker | FR-ATTR-001 |
| `Configuration/Annotation.php` | Legacy annotation marker | FR-ATTR-001 |
| `Mapping/AttributeReader.php` | Encrypted field discovery | FR-ATTR-002 |
| `Subscribers/DoctrineEncryptSubscriber.php` | ORM encrypt/decrypt lifecycle | FR-ORM-001 |
| `Encryptors/EncryptorInterface.php` | Encryptor contract | FR-ENC-001 |
| `Encryptors/EncryptorRegistry.php` | Named encryptor registry | FR-ENC-001 |
| `Encryptors/DefuseEncryptor.php` | Defuse implementation | FR-ENC-002 |
| `Encryptors/HaliteEncryptor.php` | Halite implementation | FR-ENC-002 |
| `Encryptors/MysqlAesEncryptor.php` | MySQL AES implementation | FR-ENC-002 |
| `Util/EncryptUtil.php` | Encryption helpers | FR-ENC-003 |
| `Util/MaskUtil.php` | Masking helpers | FR-ENC-003 |
| `Twig/DecryptExtension.php` | Twig decrypt filter | FR-TWIG-001 |
| `Twig/MaskExtension.php` | Twig mask filter | FR-TWIG-001 |
| `Command/AbstractCommand.php` | CLI base | FR-CLI-001 |
| `Command/DoctrineEncryptDatabaseCommand.php` | Bulk encrypt CLI | FR-CLI-002 |
| `Command/DoctrineDecryptDatabaseCommand.php` | Bulk decrypt CLI | FR-CLI-002 |
| `Command/DoctrineEncryptStatusCommand.php` | Encryption status CLI | FR-CLI-002 |
| `Command/GenerateSecretKeyCommand.php` | Secret key generator CLI | FR-CLI-003 |
| `Command/RotateKeysCommand.php` | Key rotation CLI | FR-CLI-003 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yml` | Service wiring | FR-DI-001 |

## Repository metadata (`src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `.gitignore` | Ignore generated encryptor artifacts | FR-REPO-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 22 | 22 |
| YAML config | 1 | 1 |
| Repository metadata | 1 | 1 |
| **Total production sources** | **24** | **24** |
