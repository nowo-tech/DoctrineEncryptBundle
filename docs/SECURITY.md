# Security

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model and risks](#threat-model-and-risks)
- [Mitigations](#mitigations)
- [Cryptography and secrets](#cryptography-and-secrets)
  - [MysqlAes (legacy — do not use in new production)](#mysqlaes-legacy--do-not-use-in-new-production)
  - [Marker, decrypt failures and missing keys (since 2.10.0)](#marker-decrypt-failures-and-missing-keys-since-2100)
  - [Associated data / context binding](#associated-data--context-binding)
- [Logging](#logging)
- [Dependencies and updates](#dependencies-and-updates)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Best practices](#best-practices)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

This document covers **Nowo Doctrine Encrypt Bundle** (`nowo-tech/doctrine-encrypt-bundle`): field-level encryption for Doctrine entities using Halite or Defuse, console commands for status, database encrypt/decrypt, key generation, and key rotation. It does **not** replace transport security (HTTPS), authentication, or authorization in your application.

## Attack surface

The bundle interacts with:

- **Configuration** — YAML (`nowo_doctrine_encrypt`), environment variables for keys (`secret_key_env_var`), filesystem paths to key files.
- **Doctrine lifecycle** — entity load/save: encrypted values in the database and in memory within the PHP process.
- **Console** — Symfony commands run in CLI context with the same privileges as the deploy user; they can read/write the database and key paths configured for the app.
- **Twig** — `|decrypt` and `|mask` filters process values rendered in templates; misuse could expose decrypted data if templates are unsafe.
- **Programmatic API** — `EncryptUtil` / encryptors in application code.

There is no separate HTTP API exposed by the bundle itself beyond what your app exposes.

## Threat model and risks

| Area | Risk | Notes |
|------|------|--------|
| Key material | Disclosure of secret keys or env vars | Keys grant decrypt access to all data encrypted with that config. |
| Database | Stolen DB without keys | Ciphertext remains confidential if keys are not leaked. |
| Path / env | Misconfiguration | Wrong paths or env vars can point to world-readable key files or test keys in production. |
| CLI | Privilege / operator error | Commands can mass-decrypt or re-encrypt; `doctrine:decrypt:database` refuses `prod` unless `--allow-prod` is set; run only with appropriate access and backups. |
| Twig | XSS / information disclosure | `|decrypt`, `|mask` and `|secret_hint` do **not** mark output as safe HTML — Twig auto-escaping applies. Still only decrypt what you must display. |
| Dependencies | Vulnerable crypto or Symfony libs | Rely on audited Halite/Defuse and keep dependencies updated (`composer audit`). |

## Mitigations

- Use **Halite** or **Defuse** as provided; for custom encryptors, use authenticated encryption and secure key handling.
- With several Doctrine connections, keep the encrypt listener on every connection that stores `#[Encrypted]` entities. Since **2.7.0** it is attached to all connections by default (up to 2.6.x it ran on `default` only and other connections stored plaintext); if you restrict it with `connections`, entities on an unlisted connection are stored **unencrypted**.
- Store keys **outside the web root**, restrict filesystem permissions, and prefer **secrets management** in production. The bundle creates missing key directories `0770` and keeps key files at `0600` (`secret_permissions`, see [CONFIGURATION.md](CONFIGURATION.md#secret_permissions-key-file-hardening)); existing directories (e.g. the project root) are not changed.
- Show saved secrets with **`MaskUtil::secretHint()`** / **`|secret_hint`** instead of echoing them back into forms or HTML. `SecretType` shows only `••••` by default since 2.10.0 (`hint_visible_last: 0`); revealing trailing characters is opt-in.
- Set **`on_decrypt_failure: throw`** (or `null`) so a wrong or rotated key never hands ciphertext to callers as if it were the secret, and keep **`generate_missing_key`** off in production (default) so a missing key file is an error, not a new key.
- Keep **`secret_directory_path`** and env-based keys out of version control; use `.gitignore` for key files.
- Run **key rotation** during maintenance windows with backups; use `doctrine:encrypt:rotate-keys` or the documented manual flow.
- In Twig, only decrypt what you must display; `|decrypt`, `|mask` and `|secret_hint` are subject to auto-escaping (no `is_safe: html`). Escape explicitly when rendering outside HTML contexts if needed.
- Prefer **Halite** or **Defuse** for new data. **Do not use MysqlAes for production.** Treat **MysqlAes** as legacy MySQL interop only (`@deprecated` since 2.1.0). Since **2.3.9**, container compilation **hard-fails** in `prod` if any profile selects MysqlAes — migrate to Halite/Defuse before deploying.
- Run **`composer audit`** before releases and apply security updates promptly.

## Cryptography and secrets

Encryption uses **Paragonie Halite** or **Defuse php-encryption** (authenticated encryption). Keys are never hardcoded in bundle sources; they come from your configuration (files or env). Do not log key material or full plaintext of encrypted fields at INFO/DEBUG in production.

### MysqlAes (legacy — do not use in new production)

`MysqlAesEncryptor` exists only for interoperability with MySQL `AES_ENCRYPT` / `AES_DECRYPT` (AES-128-ECB). It is **deprecated since 2.1.0**, **insecure** (AES-128-ECB without authentication: malleable, block swaps / truncation still decrypt, a wrong key passes the padding check about 1 time in 256) and triggers a deprecation notice on construction and, since 2.10.0, at container build for every profile that selects it. Removal is planned for 3.0. Since **2.3.9**, selecting MysqlAes (alias or FQCN) in **any** profile when `kernel.environment` is `prod` throws `InvalidArgumentException` at compile time.

- **Do not** select MysqlAes in production configs.
- **Migrate** existing MysqlAes ciphertext to Halite or Defuse in a non-prod environment first (decrypt with MysqlAes, re-encrypt with Halite/Defuse; see [MYSQL_AES.md](MYSQL_AES.md) and key-rotation docs).
- Prefer authenticated encryption (Halite/Defuse) for any new at-rest field encryption.
- Regression tests: `DoctrineEncryptExtensionTest` (`testMysqlAesBlockedInProduction`, FQCN / mixed-profile cases).

### Marker, decrypt failures and missing keys (since 2.10.0)

| Issue (≤ 2.9.x) | Behaviour since 2.10.0 |
|-----------------|------------------------|
| A plaintext ending with `<ENC>` was skipped by the listener and stored **in clear** (and read back without the marker). | A marked value is only kept as-is when it is ciphertext the listener wrote or restored, or when it decrypts with the property's profile (Halite / Defuse are authenticated, so a plaintext never does). Everything else is encrypted. |
| A decryption failure (wrong / rotated key, corrupted data) kept the raw ciphertext in the property; callers received ciphertext as if it were the secret. | `on_decrypt_failure`: `keep` (2.x default), `null` (stored ciphertext written back unchanged on flush) or `throw` (`DecryptionFailedException`). `null` / `throw` recommended; the default is planned to change in 3.0. |
| Halite / Defuse silently generated a new key when the key file was missing (Halite also when it was unreadable). | `generate_missing_key` (default: only in debug). Outside debug a missing key throws `MissingEncryptionKeyException`; an existing key file is never replaced. Create keys with `doctrine:encrypt:generate-secret-key`. |
| `EncryptUtil::decrypt()` returns unmarked input unchanged (plaintext pass-through). | Unchanged (lenient by design); use `EncryptUtil::decryptStrict()` for input that must be ciphertext. |
| `SecretType` revealed the last 4 characters of the stored secret. | `hint_visible_last` defaults to `0`. |

Log messages and exception messages name the entity, property and profile, never the value.

### Associated data / context binding

Ciphertext is **not bound to its context** (entity, row id, column): Halite / Defuse authenticate the ciphertext, but
the bundle passes no associated data. Someone with write access to the database (or to a sealed export) can move a
valid ciphertext from one row or column to another that uses the same profile, and it will decrypt there. Mitigations:

- use **separate profiles (keys)** for data of different sensitivity (`#[Encrypted('financial_data')]`), so ciphertext
  cannot be moved across them;
- when you seal values yourself (exports, portability), prepend the target context to the plaintext before
  `EncryptUtil::encrypt()` and verify it after `decryptStrict()`.

Optional associated data (Halite `encryptWithAD()`; Defuse has no AD support) is on the [ROADMAP](ROADMAP.md): row ids
are not known before `INSERT` for generated identifiers, so it needs a design that stays compatible with existing data.

## Logging

Avoid logging decrypted personal data, key paths with sensitive contents, or encryption exceptions that embed user data. Prefer generic error messages in production logs. The bundle's decrypt-failure warning and `DecryptionFailedException` contain the entity class, property, profile and the underlying exception class / message, never the stored value.

## Dependencies and updates

Run `composer audit` regularly. Pin or constrain versions per your policy; upgrade Halite, Defuse, Symfony, and Doctrine components when security advisories apply.

## Reporting a vulnerability

If you discover a security issue in this bundle, please report it responsibly:

- **Do not** open a public GitHub issue.
- Email the maintainers (e.g. via the address in `composer.json` or the repository’s “Security” / “About” section) with a description of the issue and steps to reproduce.
- We will respond as soon as possible and work with you on a fix and disclosure.

## Best practices

- **Secret keys:** Never commit `.Halite.key`, `.Defuse.key`, or any custom key files to version control. Add them to `.gitignore`.
- **Encryptors:** Prefer the built-in Halite or Defuse encryptors; they use well-audited libraries. If you implement a custom encryptor, use secure key derivation and authenticated encryption.
- **Configuration:** Keep `secret_directory_path` outside the web root and with restricted filesystem permissions when possible.
- **Key rotation:** When rotating keys, follow a safe procedure: run `doctrine:encrypt:rotate-keys` (optionally with `--backup`), or decrypt with the old key then re-encrypt with the new key using the bundle’s decrypt/encrypt commands. See [Key rotation](KEY_ROTATION.md) and [Commands – Rotate keys](COMMANDS.md#rotate-keys).
- **GDPR and compliance:** Encrypting personal data at rest supports data protection and security obligations (e.g. GDPR Art. 32). Use key rotation and, where needed, anonymization (e.g. with [Nowo\AnonymizedBundle](https://github.com/nowo-tech/AnonymizedBundle)) to support retention and data subject rights.

Thank you for helping keep this project and its users safe.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe or installer templates do not ship production secrets. |
| **Input / output** | Inputs validated; outputs escaped in Twig/templates where user-controlled. |
| **Dependencies** | `composer audit` run; issues triaged. |
| **Logging** | Logs do not print secrets, tokens, or session identifiers unnecessarily. |
| **Cryptography** | Prefer Halite/Defuse for all new prod data. `MysqlAes` is `@deprecated` since 2.1.0 and **blocked in `prod` since 2.3.9**; migrate legacy data — see [MYSQL_AES.md](MYSQL_AES.md). |
| **Permissions / exposure** | Routes and admin features documented; roles configured for production. |
| **Limits / DoS** | Timeouts, size limits, rate limits where applicable. |
| **AI security audit (REQ-SEC-004)** | Latest grade recorded in the org report `BUNDLES_SECURITY_ANALYSIS.md` (DoctrineEncryptBundle entry). Must be **Pass (good)** or **Pass (conditional)** before tagging. |

Record confirmation in the release PR or tag notes.

For the public GitHub security policy, see [.github/SECURITY.md](../.github/SECURITY.md).
