# Security

## Reporting a vulnerability

If you discover a security issue in this bundle, please report it responsibly:

- **Do not** open a public GitHub issue.
- Email the maintainers (e.g. via the address in `composer.json` or the repository’s “Security” / “About” section) with a description of the issue and steps to reproduce.
- We will respond as soon as possible and work with you on a fix and disclosure.

## Best practices

- **Secret keys:** Never commit `.Halite.key`, `.Defuse.key`, or any custom key files to version control. Add them to `.gitignore`.
- **Encryptors:** Prefer the built-in Halite or Defuse encryptors; they use well-audited libraries. If you implement a custom encryptor, use secure key derivation and authenticated encryption.
- **Configuration:** Keep `secret_directory_path` outside the web root and with restricted filesystem permissions when possible.
- **Key rotation:** When rotating keys, follow a safe procedure: decrypt with the old key, then re-encrypt with the new key (e.g. using the bundle’s decrypt/encrypt commands in a controlled way).

Thank you for helping keep this project and its users safe.
