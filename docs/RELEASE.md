# Release process

## Creating a new version (e.g. v1.1.0)

1. **Ensure everything is ready**
   - [CHANGELOG.md](CHANGELOG.md) has the target version (e.g. `[1.1.0]`) with date and full entry; `[Unreleased]` is at the top and empty or updated for the next cycle.
   - [UPGRADING.md](UPGRADING.md) has a section “Upgrading to X.Y.Z” with what’s new, breaking changes (if any), and upgrade steps.
   - Tests pass: `make test` or `composer test`.
   - Code style: `make cs-check` or `composer cs-check`.

2. **Commit and push** any last changes to your default branch (e.g. `main` or `master`):
   ```bash
   git add -A
   git commit -m "Prepare v1.1.0 release"
   git push origin HEAD
   ```

3. **Create and push the tag**
   ```bash
   git tag -a v1.1.0 -m "Release v1.1.0"
   git push origin v1.1.0
   ```

4. **GitHub Actions** (if configured) may create the GitHub Release from the tag.

5. **Packagist** (if the package is registered) will pick up the new tag; users can then `composer require nowo-tech/doctrine-encrypt-bundle`.

**Important:** Never move or force-push a tag that Packagist already published. Stable versions are immutable; publish fixes as a **new patch tag** (e.g. `v2.2.1`) and restore the old tag to its original commit if it was moved by mistake.

## After releasing

- Keep `## [Unreleased]` at the top of [CHANGELOG.md](CHANGELOG.md) for the next version; add new changes there.
- Optionally bump a dev version in `composer.json` for development.

---

## v2.2.3 (2026-07-09)

- **Scope:** GitHub Spec Kit (`.specify/`, baseline `specs/001-baseline/`, Cursor skills, SPEC-KIT.md); CodeRabbit CI; Twig `|decrypt` auto-escape fix; MysqlAes PHPDoc `@deprecated`; composer.lock sync (root + demos).
- **Checklist:** CHANGELOG and UPGRADING updated; `make test`; commit release notes; tag `v2.2.3`; push branch and tag.

---

## v2.2.2 (2026-06-11)

- **Scope:** Functional tests — native lazy objects on PHP 8.4+ for Symfony 8 CI matrix (LazyGhost unavailable in `symfony/var-exporter` 8.x).
- **Checklist:** CHANGELOG and UPGRADING updated; `make test`; commit release notes; tag `v2.2.2`; push branch and tag. **Do not move** existing `v2.2.0` / `v2.2.1` tags.

---

## v2.2.1 (2026-06-11)

- **Scope:** Fix `composer.lock` platform PHP 8.2 sync; CI `symfony/var-exporter` for Symfony 8.x matrix.
- **Packagist:** `v2.2.0` must stay on commit `6cde672` (immutable). Tag `v2.2.1` on the release-notes commit; restore `v2.2.0` with `git tag -f v2.2.0 6cde672 && git push origin v2.2.0 --force` if it was moved.
- **Checklist:** CHANGELOG and UPGRADING updated; `make test`; `composer validate --strict`; commit; tag `v2.2.1`; push branch and tag.

---

## v2.2.0 (2026-06-01)

- **Scope:** Align with Nowo bundle specs (**REQ-SF-001/002**): PHP **8.2+**; `symfony/*` **^7.0 \|\| ^8.0** (drop `^6.0` from `composer.json`); CI matrix Symfony **7.4**, **8.0**, **8.1** on PHP 8.2–8.5; docs (README, INSTALLATION, ROADMAP, UPGRADING); Rector `php82`.
- **Checklist:** CHANGELOG and UPGRADING updated; run `make qa`; commit; tag `v2.2.0`; push branch and tag.

---

## v2.1.0 (2026-06-01)

- **Scope:** (1) New **MysqlAes** encryptor (MySQL AES_ENCRYPT/AES_DECRYPT compatible). (2) Docs: MYSQL_AES.md, PERFORMANCE.md; CONFIGURATION/USAGE/DEMO/README updates. (3) Demos: MySQL AES CRUD, LIKE filters, fixtures, DB raw vs decrypted views; secret-message db-values; Docker MySQL optional. (4) generate-secret-key for MysqlAes; unit tests MysqlAesEncryptorTest.
- **Checklist:** CHANGELOG and UPGRADING updated; run `make qa`; remove stray demo files; commit; tag `v2.1.0`; push branch and tag.

---

## v2.0.10 (2026-02-21)

- **Scope:** (1) DoctrineEncryptExtension extends `DependencyInjection\Extension\Extension` instead of `HttpKernel\DependencyInjection\Extension` (fixes Symfony 7.1+ internal/deprecation). (2) COMMANDS.md: batchSize default 5 and link to CONFIGURATION.
- **Checklist:** CHANGELOG and UPGRADING updated; run `make qa`; commit; tag `v2.0.10`; push branch and tag.

---

## v2.0.9 (2026-02-21)

- **Scope:** Code style (PHP-CS-Fixer) and internal refactors across src/tests/demos; demo apps and demo/Makefile updates; CI and Makefile/docker-compose adjustments. No API or config changes.
- **Checklist:** CHANGELOG and UPGRADING updated; run `make qa` (or `composer cs-check` and `composer test`); commit; tag `v2.0.9`; push branch and tag.

---

## v2.0.8 (2026-02-20)

- **Scope:** Fix RotateKeysCommandTest for Symfony Console 7.0/8.0: replace `Application::add()` with `FactoryCommandLoader` + `setCommandLoader()` and `setApplication()` on the rotate command so all 8 unit tests pass on PHP 8.4 with Symfony 7.0 (CI).
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.8` created and pushed.

---

## v2.0.7 (2026-02-20)

- **Scope:** (1) doctrine:encrypt:status: list encrypted properties and config per entity; show configured encryptor configs at end. (2) New command doctrine:encrypt:rotate-keys (optional backup, decrypt, change keys, re-encrypt; --backup, --no-interaction). (3) --force on generate-secret-key (overwrite without asking), decrypt:database and encrypt:database (skip confirmation). (4) Encrypt/decrypt database use raw SQL; batch_size config (default 5). (5) FrankenPHP docs (INSTALLATION, README, DEMO); tests and coverage ≥ 95%. (6) Docs: COMMANDS, KEY_ROTATION, SECURITY, INSTALLATION, ROADMAP, CHANGELOG, UPGRADING updated.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.7` created and pushed.

---

## v2.0.6 (2026-02-20)

- **Scope:** (1) New unit tests for coverage: HaliteEncryptor when key path is a directory; GenerateSecretKeyCommand when creating key in non-existent directory. (2) Fix PHP Notice in HaliteEncryptor test (suppress with `@` so suite runs without notices). Coverage lines ≥ 95%.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.6` created and pushed.

---

## v2.0.5 (2026-02-20)

- **Scope:** (1) GenerateSecretKeyCommand: support all configs (create missing Halite/Defuse keys); optional `config` argument with overwrite confirmation. (2) Rename test directory `Tests` → `tests`; update phpunit, composer autoload-dev, php-cs-fixer. (3) Keep symfony/var-exporter in require for LazyGhost; CI pin var-exporter ^7.4. (4) Makefile: validate and update targets; qa runs validate. (5) Docs: COMMANDS.md correct args (config) and generate-secret-key behaviour.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.5` created and pushed.

---

## v2.0.4 (2026-02-19)

- **Scope:** (1) Fix PHPUnit unit tests (EncryptUtil, DecryptExtension) so they no longer mock the final class `EncryptorRegistry`; use real registry with mocked `EncryptorInterface` instead. Fixes test-coverage errors with PHPUnit 10/11. (2) Make `doctrine:encrypt:database` and `doctrine:decrypt:database` command `configure()` idempotent so arguments are only added when not already present; avoids "An argument with name config already exists" with Symfony Console 7/8.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.4` created and pushed.

## v2.0.3 (2026-02-19)

- **Scope:** Allow paragonie/sodium_compat ^2.0 so the bundle installs in projects using sodium_compat 2.x.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.3` created and pushed.

## v2.0.2 (2026-02-19)

- **Scope:** Symfony Console 8.0 compatibility in command tests (setApplication); CI fix for symfony/var-exporter and functional tests (LazyGhost).
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.2` created and pushed.

## v2.0.1 (2026-02-19)

- **Scope:** Test fixes (encryptor unit tests), add `symfony/var-exporter` for Doctrine ORM LazyGhost in functional tests.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.1` created and pushed.

## v2.0.0 (2026-02-19)

- **Scope:** Drop Symfony 6 support and remove demo/symfony6; require Symfony ^7.0 || ^8.0.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v2.0.0` created and pushed.
