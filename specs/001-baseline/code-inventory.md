# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/doctrine-encrypt-bundle`  
**Last audited**: 2026-11-22 (sweep: 2026-10-13 baseline + later sources; `DbalTypeLookup` added for 2.15.1)

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
| `Command/ChunkedRun.php` | State of one database-command run: lock timeout, attempts, committed counters of the locked chunks ([CONCURRENCY](../../docs/CONCURRENCY.md)) | FR-CLI-002 |
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

## Sources added after the last baseline review (sweep 2026-11-16)

These 21 `src/` files were added by releases after the last review. Each row maps the file to the requirement already used for its neighbours in the same directory; the description comes from the class docblock. With them, every file under `src/` is listed in this inventory.

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Doctrine/Types/AbstractEncryptedType.php` | Base class of the encrypted DBAL types: the value is encrypted when Doctrine converts it for the database and decrypt... | FR-ORM-001 |
| `Doctrine/Types/DbalTypeLookup.php` | Finds a registered DBAL type by name through the connection's type provider (doctrine/dbal >= 4.6) or the static registry on older versions, without the deprecated static calls; stateless, safe in a kept-alive worker. | FR-ORM-001 |
| `Doctrine/Types/EncryptedJsonType.php` | `encrypted_json` (CLOB/TEXT, not a native JSON column: the stored value is ciphertext): a value JSON-encoded, then en... | FR-ORM-001 |
| `Doctrine/Types/EncryptedStringType.php` | `encrypted_string` (VARCHAR): a string encrypted with the profile's encryptor. | FR-ORM-001 |
| `Doctrine/Types/EncryptedTextType.php` | `encrypted_text` (CLOB/TEXT): like `encrypted_string` for long values. | FR-ORM-001 |
| `Doctrine/Types/EncryptedTypeRuntime.php` | Where the encrypted DBAL types (AbstractEncryptedType) get their encryptors from. | FR-ORM-001 |
| `Doctrine/Types/EncryptedTypeRuntimeConfigurator.php` | Hands the container's encryptors to EncryptedTypeRuntime when the kernel boots and takes them back when it shuts down. | FR-ORM-001 |
| `Encryptors/EncryptorRegistryInterface.php` | Port: registry of named encryptors (one per `encryptors` config alias, e.g. personal_data, financial_data). | FR-ENC-002 |
| `EventListener/EncryptedMappingValidator.php` | Checks the encryption selection against Doctrine metadata when a class's metadata is loaded (first use, or cache:warm... | FR-ORM-001 |
| `Exception/DecryptionFailedException.php` | A value carries the encryption marker but cannot be decrypted (wrong or rotated key, corrupted or foreign ciphertext). | FR-EXCEPTION-001 |
| `Exception/ChunkLockConflictException.php` | A database-command chunk stayed in a lock conflict after its attempts (CONFLICT_001 / CONFLICT_002). | FR-EXCEPTION-001, FR-CLI-002 |
| `Exception/ExceptionInterface.php` | Marker interface for the exceptions thrown by this bundle (catch it to handle every bundle failure at once). | FR-EXCEPTION-001 |
| `Exception/MissingEncryptionKeyException.php` | The key file of a file-based profile does not exist and the bundle is not allowed to generate one (`generate_missing_... | FR-EXCEPTION-001 |
| `Exception/ValueNotEncryptedException.php` | A value that must be ciphertext does not carry the encryption marker (or has an empty payload). | FR-EXCEPTION-001 |
| `Mapping/EncryptedFieldMap.php` | Encrypted fields selected in the bundle configuration (`nowo_doctrine_encrypt.encrypted_fields`) instead of the `#[En... | FR-ATTR-002 |
| `Mapping/ListenerFieldResolver.php` | Which mapped fields the listener encrypts, seen from Doctrine metadata: the same selection as DoctrineEncryptSubscrib... | FR-ATTR-002 |
| `Resources/translations/NowoDoctrineEncryptBundle.de.yaml` | Configuration `NowoDoctrineEncryptBundle.de.yaml` | FR-FORM-005 |
| `Resources/translations/NowoDoctrineEncryptBundle.fr.yaml` | Configuration `NowoDoctrineEncryptBundle.fr.yaml` | FR-FORM-005 |
| `Resources/translations/NowoDoctrineEncryptBundle.it.yaml` | Configuration `NowoDoctrineEncryptBundle.it.yaml` | FR-FORM-005 |
| `Resources/translations/NowoDoctrineEncryptBundle.nl.yaml` | Configuration `NowoDoctrineEncryptBundle.nl.yaml` | FR-FORM-005 |
| `Resources/translations/NowoDoctrineEncryptBundle.pt.yaml` | Configuration `NowoDoctrineEncryptBundle.pt.yaml` | FR-FORM-005 |
| `Util/EncryptUtilInterface.php` | Port: encrypt and decrypt single values outside Doctrine (marker `<ENC>` appended to the ciphertext). | FR-ENC-003 |
| `Util/EncryptedValue.php` | Helpers for stored values in the "ciphertext + EncryptUtil::ENCRYPTION_MARKER" format. | FR-ENC-003 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 46 | 46 |
| YAML config / translations | 8 | 8 |
| Repository metadata | 1 | 1 |
| **Total production sources** | **55** | **55** |
