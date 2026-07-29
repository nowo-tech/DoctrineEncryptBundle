# Contributing

Thank you for considering contributing to Doctrine Encrypt Bundle.

## Table of contents

- [Code of Conduct](#code-of-conduct)
- [Development setup](#development-setup)
- [Code style](#code-style)
- [Tests](#tests)
- [Pull requests](#pull-requests)
  - [Local git hooks (maintainers)](#local-git-hooks-maintainers)
- [Reporting issues](#reporting-issues)

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Development setup

Requires **PHP 8.2+** and **ext-sodium** (see [INSTALLATION.md](INSTALLATION.md#requirements)).

1. Clone the repository and install dependencies:
   ```bash
   composer install
   ```

2. Run tests:
   ```bash
   composer test
   # or with Docker: make up && make test
   ```

3. Code style:
   ```bash
   composer cs-check
   composer cs-fix
   ```

4. Full QA:
   ```bash
   composer qa
   # or: make qa
   ```

## Code style

The project uses [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) with the rules in `.php-cs-fixer.dist.php`. Please run `composer cs-fix` before submitting a pull request.

## Tests

- Add or update unit and functional tests as needed.
- Run `composer test` (or `make test`) and ensure all tests pass.

## Pull requests

- Open an issue first to discuss larger changes.
- Branch from `main` (or the default branch), make your changes, and open a PR.
- Keep the scope focused; split unrelated changes into separate PRs when possible.
- Update the documentation under `docs/` if you change configuration, commands, or behavior.
- Do **not** add `Co-authored-by: Cursor` (or similar AI attribution) to commits or PRs.

### Local git hooks (maintainers)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks` (`commit-msg` / `prepare-commit-msg`) via `core.hooksPath`. Run it once per clone before your first commit.

Also disable agent attribution in Cursor: **Settings → Agent → Attribution** (IDE), and CLI `~/.cursor/cli-config.json` (`attributeCommitsToAgent` / `attributePRsToAgent`: `false`).

If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.

## Reporting issues

- Use the GitHub issue tracker.
- Include PHP, Symfony, and Doctrine ORM versions.
- Provide a minimal example or steps to reproduce when reporting bugs.

Thank you for contributing.
