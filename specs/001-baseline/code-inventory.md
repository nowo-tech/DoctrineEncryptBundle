# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/doctrine-encrypt-bundle`  
**Last audited**: 2026-10-13

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
| `Util/MaskUtil.php` | Masking helpers, saved-secret hint | FR-ENC-003, FR-SEC-002 |
| `Twig/DecryptExtension.php` | Twig decrypt filter | FR-TWIG-001 |
| `Twig/MaskExtension.php` | Twig mask / secret_hint filters | FR-TWIG-001, FR-SEC-002 |
| `Security/SecretKeyPermissions.php` | Key dir/file mode helpers | FR-SEC-001 |
| `EventListener/ClosedEntityManagerRecoveryListener.php` | Resets a closed entity manager between worker requests (FrankenPHP) | FR-ORM-001 |
| `EventListener/SecretKeyPermissionsListener.php` | Key permission hardening (console + throttled HTTP) | FR-SEC-001 |
| `Command/AbstractCommand.php` | CLI base | FR-CLI-001 |
| `Command/DoctrineEncryptDatabaseCommand.php` | Bulk encrypt CLI | FR-CLI-002 |
| `Command/DoctrineDecryptDatabaseCommand.php` | Bulk decrypt CLI | FR-CLI-002 |
| `Command/DoctrineEncryptStatusCommand.php` | Encryption status CLI | FR-CLI-002 |
| `Command/GenerateSecretKeyCommand.php` | Secret key generator CLI | FR-CLI-003 |
| `Command/RotateKeysCommand.php` | Key rotation CLI | FR-CLI-003 |
| `Form/SecretFieldApplier.php` | "Leave blank to keep" secret applier ([spec 002](../002-secret-field-applier/spec.md)) | FR-FORM-001, FR-FORM-002 |
| `Form/Type/SecretType.php` | Optional secret form field ([spec 002](../002-secret-field-applier/spec.md)) | FR-FORM-003, FR-FORM-004 |

## Symfony config and translations (`src/Resources/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yml` | Service wiring | FR-DI-001 |
| `Resources/translations/NowoDoctrineEncryptBundle.en.yaml` | SecretType labels (en) | FR-FORM-005 |
| `Resources/translations/NowoDoctrineEncryptBundle.es.yaml` | SecretType labels (es) | FR-FORM-005 |

## Repository metadata (`src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `.gitignore` | Ignore generated encryptor artifacts | FR-REPO-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 25 | 25 |
| YAML config / translations | 3 | 3 |
| Repository metadata | 1 | 1 |
| **Total production sources** | **29** | **29** |
