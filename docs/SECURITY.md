# Security

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
| CLI | Privilege / operator error | Commands can mass-decrypt or re-encrypt; run only with appropriate access and backups. |
| Twig | XSS / information disclosure | Decrypted values in HTML without escaping can leak data; follow Symfony escaping rules. |
| Dependencies | Vulnerable crypto or Symfony libs | Rely on audited Halite/Defuse and keep dependencies updated (`composer audit`). |

## Mitigations

- Use **Halite** or **Defuse** as provided; for custom encryptors, use authenticated encryption and secure key handling.
- Store keys **outside the web root**, restrict filesystem permissions, and prefer **secrets management** in production.
- Keep **`secret_directory_path`** and env-based keys out of version control; use `.gitignore` for key files.
- Run **key rotation** during maintenance windows with backups; use `doctrine:encrypt:rotate-keys` or the documented manual flow.
- In Twig, only decrypt what you must display; escape output appropriately for your context (HTML, JSON, etc.).
- Run **`composer audit`** before releases and apply security updates promptly.

## Cryptography and secrets

Encryption uses **Paragonie Halite** or **Defuse php-encryption** (authenticated encryption). Keys are never hardcoded in bundle sources; they come from your configuration (files or env). Do not log key material or full plaintext of encrypted fields at INFO/DEBUG in production.

## Logging

Avoid logging decrypted personal data, key paths with sensitive contents, or encryption exceptions that embed user data. Prefer generic error messages in production logs.

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
| **Cryptography** | If used: keys from secure config; never hardcoded. |
| **Permissions / exposure** | Routes and admin features documented; roles configured for production. |
| **Limits / DoS** | Timeouts, size limits, rate limits where applicable. |

Record confirmation in the release PR or tag notes.

For the public GitHub security policy, see [.github/SECURITY.md](../.github/SECURITY.md).
