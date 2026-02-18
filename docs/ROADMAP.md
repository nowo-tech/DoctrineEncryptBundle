# Roadmap

This document outlines the direction of Doctrine Encrypt Bundle and helps contributors and users understand upcoming priorities.

## Vision

Doctrine Encrypt Bundle aims to provide **simple, secure field-level encryption** for Doctrine ORM entities in Symfony applications, using well-audited libraries (Halite, Defuse) and a clear upgrade path when moving from older or custom encryptors.

## Current focus (1.x)

- **Stability & compatibility:** Support Symfony 6.x, 7.x, 8.x and Doctrine ORM 2.x/3.x. Fix regressions and deprecations as they appear.
- **Documentation:** Clear install, config, upgrade, and command docs so new users can adopt the bundle quickly.
- **Testing:** Maintain test coverage and CI (PHP × Symfony matrix, code style).
- **Recipe:** Get the Flex recipe merged in [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) so `composer require nowo-tech/doctrine-encrypt-bundle` registers the bundle and config automatically.

No breaking changes are planned for the 1.x line; new options will be additive where possible.

## Short term (next releases)

- **Key rotation:** Document a safe workflow (backup → decrypt with old key → re-encrypt with new key using existing commands, or a dedicated command) and add it to the docs.
- **Code coverage:** Keep or raise coverage (e.g. ≥90%) and ensure coverage runs in CI (e.g. PCOV in Docker/CI).
- **Demos:** Keep demos (Symfony 6/7/8) working and referenced from the docs.

## Possible future (ideas, not committed)

- **Additional encryptors:** Optional support for other audited libraries, behind optional dependencies.
- **Key rotation tooling:** Optional command or script to re-encrypt all data with a new key (building on current encrypt/decrypt commands).
- **Performance:** Optional in-request caching of decrypted values where safe (same entity, same request) to avoid repeated decrypt in loops.
- **Search / querying:** Document that encrypted fields cannot be searched or ordered at the DB level; optional “deterministic” or searchable encryption could be explored only if we can do it without weakening security (no commitment).
- **Ecosystem:** Compatibility with future PHP and Symfony versions (e.g. PHP 8.4+, Symfony 9) when released.

A major version would only be considered if we introduce breaking changes (e.g. config structure, PHP/Symfony requirements).

## Out of scope (for this bundle)

- **Full-disk or DB-level encryption:** This bundle encrypts selected entity fields only.
- **Search on encrypted fields:** By design, values are encrypted at rest; searching or ordering by encrypted columns is not supported. Use non-encrypted fields or external search indexes for search.
- **HSM / key vault integration:** Key storage is file-based; integration with vaults (e.g. AWS KMS, HashiCorp Vault) could be a separate recipe or doc, not a core feature for 1.x.
- **Encryption at rest for Doctrine DBAL:** Only ORM entities and configured properties are covered; raw SQL or other layers are out of scope.

## Community

- **Issues & PRs:** [GitHub Issues](https://github.com/nowo-tech/doctrine-encrypt-bundle/issues) and Pull Requests are welcome.
- **Security:** See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

If you rely on Doctrine Encrypt Bundle, consider giving it a **star** on GitHub so others can discover it more easily.
