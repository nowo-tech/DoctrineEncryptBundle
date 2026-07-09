# Feature Specification: DoctrineEncryptBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  

**Package**: `nowo-tech/doctrine-encrypt-bundle`  
**Configuration root**: `doctrine_encrypt`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Encrypt and decrypt Doctrine entity field values at rest using pluggable encryptors (Halite, Defuse, MySQL AES). Supports PHP 8 attributes, bulk CLI tools, key generation/rotation, and Twig helpers for decrypt/mask in templates.

---

## User Scenarios

### US-01 — Transparent field encryption (P1)

**Given** an entity property marked `@Encrypted`, **When** persisted and loaded via Doctrine ORM, **Then** ciphertext is stored in the database and plaintext is available in the entity at runtime.

### US-02 — Multiple encryptors (P2)

**Given** encryptors registered in config, **When** an entity specifies an encryptor name, **Then** `EncryptorRegistry` resolves the correct implementation.

### US-03 — Bulk maintenance CLI (P2)

**Given** existing plaintext or ciphertext in the database, **When** maintainer runs encrypt/decrypt/status commands, **Then** schema-wide migration proceeds without breaking unrelated columns.

### US-04 — Template-safe display (P3)

**Given** encrypted values in Twig templates, **When** integrator uses decrypt/mask filters, **Then** output respects configured keys and masking rules.

---

## Requirements

- **FR-BUNDLE-001**: `NowoDoctrineEncryptBundle` exposes alias `doctrine_encrypt`.
- **FR-CFG-001 / FR-CFG-002**: Configuration defines encryptor list, default encryptor, and secret references; extension wires subscriber and registry.
- **FR-ATTR-001 / FR-ATTR-002**: `Encrypted` attribute (and legacy annotation) mark fields; `AttributeReader` discovers encrypted mappings.
- **FR-ORM-001**: `DoctrineEncryptSubscriber` encrypts on flush and decrypts on load.
- **FR-ENC-001–003**: Encryptor interface, registry, concrete backends, and util helpers.
- **FR-TWIG-001**: `DecryptExtension` and `MaskExtension` for safe template output.
- **FR-CLI-001–003**: Abstract command base plus encrypt/decrypt/status and key management commands.
- **FR-DI-001**: `services.yml` wires public services documented in [`docs/USAGE.md`](../../docs/USAGE.md).

---

## Success Criteria

- **SC-001**: **24/24** production files mapped in inventory.
- **SC-002**: Config keys in [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md) match `Configuration.php`.
- **SC-003**: `composer qa` passes; PHPUnit covers subscriber and encryptors.

---

## Explicit non-goals

- Full-database transparent encryption (TDE) or column-level DB-native encryption outside Doctrine.
- Encrypting non-Doctrine persistence layers unless documented extensions are added.

---

## Validation

`composer qa`, `make release-check`, PHPUnit, PHPStan. Update spec + inventory when adding `src/` files.
