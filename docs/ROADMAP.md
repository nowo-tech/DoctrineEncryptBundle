# Roadmap

This document outlines the direction of Doctrine Encrypt Bundle and helps contributors and users understand upcoming priorities.

## Vision

Doctrine Encrypt Bundle aims to provide **simple, secure field-level encryption** for Doctrine ORM entities in Symfony applications, using well-audited libraries (Halite, Defuse) and a clear upgrade path when moving from older or custom encryptors.

## Current focus (1.x)

- **Stability & compatibility:** Support Symfony 6.x, 7.x, 8.x and Doctrine ORM 2.x/3.x. Fix regressions and deprecations as they appear.
- **Documentation:** Clear install, config, upgrade, and command docs so new users can adopt the bundle quickly.
- **Testing:** Maintain test coverage and CI (PHP × Symfony matrix, code style).

No breaking changes are planned for the 1.x line; new options will be additive where possible.

## Possible future (ideas, not committed)

- **Additional encryptors:** Optional support for other audited libraries, behind optional dependencies.
- **Key rotation:** Documented workflow or optional tooling for rotating keys and re-encrypting data.
- **Performance:** Optional caching of decrypted values in the same request where safe.
- **Ecosystem:** Compatibility with future PHP and Symfony versions (e.g. PHP 8.4+, Symfony 9) when released.

A major version would only be considered if we introduce breaking changes (e.g. config structure, PHP/Symfony requirements).

## Community

- **Issues & PRs:** [GitHub Issues](https://github.com/nowo-tech/doctrine-encrypt-bundle/issues) and Pull Requests are welcome.
- **Security:** See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

If you rely on Doctrine Encrypt Bundle, consider giving it a **star** on GitHub so others can discover it more easily.
